<?php

namespace App\Services\Quotations;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\BetterPriceRequested;
use App\Support\Quotations\ShopClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The follow-up engine (UX-FLOWS §3.2 steps 3–5). It plans the contacts when a quotation or
 * lead is created, and decides the next step when staff log a result:
 *
 * | Outcome              | What happens                                                         |
 * |----------------------|----------------------------------------------------------------------|
 * | coming_on + date     | visit booked; other planned follow-ups dropped; reminder on that day |
 * | call_later + date    | next follow-up on that date; planned ones before it are dropped      |
 * | wants_better_price   | Owner/Admin notified; stays open; schedule carries on                |
 * | bought_elsewhere, not_interested, wrong_number | closed as lost with that reason            |
 * | no_answer            | retry tomorrow                                                       |
 *
 * Every log keeps an undo record, so the 10-second Undo can put everything back.
 */
class FollowUpScheduler
{
    /** Days after the quote for each follow-up, from Settings `followups.schedule_days` ("1, 3, 6, 10"). */
    public static function scheduleDays(): array
    {
        $raw = Setting::get('followups.schedule_days');
        $days = is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        $days = collect($days)->map(fn ($d) => (int) $d)->filter(fn ($d) => $d >= 0 && $d <= 90)->unique()->sort()->values()->all();

        return $days ?: [1, 3, 6, 10];
    }

    /** Plans Day 1 / 3 / 6 / 10 (from Settings) counted from the day the quote was made. */
    public function scheduleQuotation(Quotation $q): void
    {
        $base = Carbon::parse($q->created_at ?? now())->timezone(ShopClock::TZ)->startOfDay();
        foreach (self::scheduleDays() as $day) {
            $this->create($q, $base->copy()->addDays($day), $day, 'scheduled');
        }
    }

    /** A lead is contacted the same day (Day 0), then follows the same schedule. */
    public function scheduleLead(Lead $lead): void
    {
        $base = Carbon::parse($lead->created_at ?? now())->timezone(ShopClock::TZ)->startOfDay();
        $days = array_values(array_unique([0, ...self::scheduleDays()]));
        foreach ($days as $day) {
            $this->create($lead, $base->copy()->addDays($day), $day, 'scheduled');
        }
    }

    /**
     * Log what the customer said. $date is required for coming_on / call_later (Y-m-d).
     *
     * @throws ValidationException
     */
    public function log(FollowUp $f, string $outcome, User $by, ?string $date = null, ?string $note = null, ?string $channel = null): FollowUp
    {
        if (! array_key_exists($outcome, FollowUp::OUTCOMES)) {
            throw ValidationException::withMessages(['outcome' => 'Pick what the customer said.']);
        }
        if (in_array($outcome, FollowUp::DATED_OUTCOMES, true)) {
            if (! $date) {
                throw ValidationException::withMessages(['date' => 'Pick a date.']);
            }
            if ($date < ShopClock::today()) {
                throw ValidationException::withMessages(['date' => 'Pick today or a later date.']);
            }
        }

        return DB::transaction(function () use ($f, $outcome, $by, $date, $note, $channel) {
            $f = FollowUp::query()->lockForUpdate()->findOrFail($f->id);
            if (! $f->isPending()) {
                throw ValidationException::withMessages(['outcome' => 'This follow-up was already logged.']);
            }
            $subject = $f->subject;
            $undo = ['subject' => $this->snapshot($subject), 'created' => [], 'skipped' => []];

            $f->fill([
                'status' => FollowUp::DONE,
                'outcome' => $outcome,
                'outcome_note' => $note ?: null,
                'visit_on' => $outcome === 'coming_on' ? $date : null,
                'channel' => $channel ?: $f->channel,
                'done_by' => $by->id,
                'done_at' => now(),
            ]);

            $tomorrow = ShopClock::todayDate()->addDay();

            switch ($outcome) {
                case 'coming_on':
                    $subject->status = $subject instanceof Quotation ? Quotation::VISIT_BOOKED : Lead::VISIT_BOOKED;
                    $subject->visit_on = $date;
                    $undo['skipped'] = $this->skipPending($subject, except: $f->id);
                    $undo['created'][] = $this->create($subject, Carbon::parse($date), $f->step, 'visit')->id;
                    break;

                case 'call_later':
                    $this->markContacted($subject);
                    $undo['skipped'] = $this->skipPending($subject, except: $f->id, onOrBefore: $date);
                    $undo['created'][] = $this->create($subject, Carbon::parse($date), $f->step, 'call_later')->id;
                    break;

                case 'wants_better_price':
                    $this->markContacted($subject);
                    // We just spoke to them: anything already overdue is answered by this call.
                    $undo['skipped'] = $this->skipPending($subject, except: $f->id, onOrBefore: ShopClock::today());
                    if (! $subject->followUps()->pending()->whereKeyNot($f->id)->exists()) {
                        $undo['created'][] = $this->create($subject, $tomorrow, $f->step, 'call_later')->id;
                    }
                    $this->notifyManagers($subject, $by, $note);
                    break;

                case 'no_answer':
                    $undo['skipped'] = $this->skipPending($subject, except: $f->id, onOrBefore: $tomorrow->toDateString());
                    $undo['created'][] = $this->create($subject, $tomorrow, $f->step, 'retry')->id;
                    break;

                default: // bought_elsewhere, not_interested, wrong_number → lost
                    $subject->status = $subject instanceof Quotation ? Quotation::LOST : Lead::LOST;
                    $subject->lost_reason = $outcome;
                    $subject->lost_note = $note ?: null;
                    if ($subject instanceof Quotation) {
                        $subject->lost_at = now();
                    }
                    $undo['skipped'] = $this->skipPending($subject, except: $f->id);
            }

            $subject->save();
            $f->undo = $undo;
            $f->save();

            return $f;
        });
    }

    /** Puts back everything a log changed. Allowed for a short while after logging. */
    public function undo(FollowUp $f): FollowUp
    {
        return DB::transaction(function () use ($f) {
            $f = FollowUp::query()->lockForUpdate()->findOrFail($f->id);
            if ($f->status !== FollowUp::DONE || ! $f->undo || $f->done_at?->lt(now()->subMinutes(2))) {
                throw ValidationException::withMessages(['undo' => 'Too late to undo this one.']);
            }
            $undo = $f->undo;
            FollowUp::query()->whereKey($undo['created'] ?? [])->where('status', FollowUp::PENDING)->delete();
            FollowUp::query()->whereKey($undo['skipped'] ?? [])->update(['status' => FollowUp::PENDING, 'outcome_note' => null]);

            $subject = $f->subject;
            $subject->forceFill($undo['subject'] ?? [])->save();

            $f->forceFill([
                'status' => FollowUp::PENDING, 'outcome' => null, 'outcome_note' => null, 'visit_on' => null,
                'done_by' => null, 'done_at' => null, 'undo' => null,
            ])->save();

            return $f;
        });
    }

    /** Stops all pending follow-ups of a quote/lead (won, lost, revised). Returns the ids. */
    public function skipPending(Model $subject, ?int $except = null, ?string $onOrBefore = null, ?string $note = null): array
    {
        $q = FollowUp::query()->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
            ->pending()
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->when($onOrBefore, fn ($q) => $q->whereDate('due_on', '<=', $onOrBefore));
        $ids = $q->pluck('id')->all();
        if ($ids) {
            FollowUp::query()->whereKey($ids)->update(['status' => FollowUp::SKIPPED, 'outcome_note' => $note]);
        }

        return $ids;
    }

    private function create(Model $subject, Carbon $due, int $step, string $kind): FollowUp
    {
        return FollowUp::create([
            'branch_id' => $subject->branch_id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'customer_id' => $subject->customer_id,
            'assigned_to' => $subject->assigned_to,
            'due_on' => $due->toDateString(),
            'step' => $step,
            'kind' => $kind,
        ]);
    }

    private function snapshot(Model $subject): array
    {
        $fields = ['status', 'visit_on', 'lost_reason', 'lost_note'];
        if ($subject instanceof Quotation) {
            $fields[] = 'lost_at';
        }
        $out = [];
        foreach ($fields as $k) {
            $v = $subject->getAttributes()[$k] ?? null;
            $out[$k] = $v;
        }

        return $out;
    }

    private function markContacted(Model $subject): void
    {
        if ($subject instanceof Lead && $subject->status === Lead::NEW) {
            $subject->status = Lead::CONTACTED;
        }
    }

    private function notifyManagers(Model $subject, User $by, ?string $note): void
    {
        $managers = User::role(['owner', 'admin'])->where('is_active', true)->get();
        if ($managers->isNotEmpty()) {
            Notification::send($managers, new BetterPriceRequested($subject, $by, $note));
        }
    }
}
