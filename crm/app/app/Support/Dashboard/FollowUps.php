<?php

namespace App\Support\Dashboard;

use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quotations\FollowUpMessages;
use App\Support\Money;
use App\Support\Phone;
use App\Support\Quotations\ShopClock;
use App\Support\RegNo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * "Follow-ups today" for the Home screen and the badge (UX-FLOWS §2, §3.2 step 4).
 * The shell's HomeData can call these directly:
 *   FollowUps::countForUser($user)        → int (overdue + due today; badge)
 *   FollowUps::rowsForUser($user, 5)      → array of plain rows, overdue first
 * or render `<x-quotations.followups-today :user="$user" />`.
 * Sales see their own; Owner/Admin see everyone's.
 */
final class FollowUps
{
    public static function countForUser(User $user): int
    {
        return self::canSeeAny($user) ? self::scoped($user)->dueByToday()->count() : 0;
    }

    public static function overdueCountForUser(User $user): int
    {
        return self::canSeeAny($user) ? self::scoped($user)->overdue()->count() : 0;
    }

    private static function canSeeAny(User $user): bool
    {
        return $user->can('quotations.view') || $user->can('leads.manage');
    }

    /**
     * What this person may chase: quotation follow-ups need quotations.view, lead follow-ups
     * need leads.manage (owner rule: counter staff get leads per person). Sales see only their
     * own; Owner/Admin see everyone's.
     */
    private static function scoped(User $user, bool $mineOnly = false): Builder
    {
        $types = array_values(array_filter([
            $user->can('quotations.view') ? Quotation::class : null,
            $user->can('leads.manage') ? Lead::class : null,
        ]));

        return FollowUp::query()
            ->nextPerSubject()
            ->whereIn('subject_type', $types)
            ->when($mineOnly || ! Quotation::seesAll($user), fn ($q) => $q->where('assigned_to', $user->id));
    }

    /** Overdue (oldest first) then today's. */
    public static function rowsForUser(User $user, int $limit = 50, bool $mineOnly = false): array
    {
        if (! self::canSeeAny($user)) {
            return [];
        }

        return self::query($user, $mineOnly)->dueByToday()->limit($limit)->get()->map(fn ($f) => self::row($f))->all();
    }

    public static function upcomingForUser(User $user, int $days = 7, int $limit = 50, bool $mineOnly = false): array
    {
        if (! self::canSeeAny($user)) {
            return [];
        }

        return self::query($user, $mineOnly)->pending()
            ->whereDate('due_on', '>', ShopClock::today())
            ->whereDate('due_on', '<=', ShopClock::todayDate()->addDays($days)->toDateString())
            ->limit($limit)->get()->map(fn ($f) => self::row($f))->all();
    }

    private static function query(User $user, bool $mineOnly): Builder
    {
        return self::scoped($user, $mineOnly)
            ->with(['assignee:id,name', 'subject' => fn (MorphTo $m) => $m->morphWith([
                Quotation::class => ['customer', 'car', 'items'],
                Lead::class => ['customer.cars', 'product'],
            ])])
            ->orderBy('due_on')->orderBy('id');
    }

    /** One plain row for a list, the Home block or the desktop work queue. */
    public static function row(FollowUp $f): array
    {
        $s = $f->subject;
        $isQuote = $s instanceof Quotation;
        $mobile = FollowUpMessages::mobileFor($s);
        $today = ShopClock::todayDate();
        $due = Carbon::parse($f->due_on->toDateString(), ShopClock::TZ);
        $late = (int) $due->diffInDays($today, false);
        $last = FollowUp::query()->where('subject_type', $f->subject_type)->where('subject_id', $f->subject_id)
            ->where('status', FollowUp::DONE)->latest('done_at')->first(['outcome', 'done_at', 'outcome_note']);

        if ($isQuote) {
            $name = $s->customer?->name ?? 'Customer';
            $car = $s->carLabel();
            $asked = $s->itemsSummary(2);
            $value = $s->total_paise;
            $since = 'quoted '.$s->created_at->timezone(ShopClock::TZ)->format('j M');
            $lines = $s->items->map(fn ($i) => [
                'name' => $i->name, 'qty' => $i->qty, 'unit' => Money::format($i->unit_price_paise),
                'mrp' => $i->mrp_paise && $i->mrp_paise > $i->unit_price_paise ? Money::format($i->mrp_paise * $i->qty) : null,
                'total' => Money::format($i->line_total_paise),
            ])->all();
        } else {
            $name = $s->displayName();
            $car = $s->customer?->cars?->first()?->model ?? '';
            $asked = $s->interest ?: ($s->product?->name ?? 'Enquiry');
            $value = $s->price_told_paise ?? 0;
            $since = ($s->type === Lead::TYPE_PRICE ? 'asked price ' : 'enquired ').$s->created_at->timezone(ShopClock::TZ)->format('j M');
            $lines = [];
        }
        $message = app(FollowUpMessages::class)->forFollowUp($f);
        $carModel = $isQuote ? $s->car : $s->customer?->cars?->first();
        $assigneeName = (string) $f->assignee?->name;
        $validLeft = $isQuote ? (int) ShopClock::todayDate()->diffInDays(Carbon::parse($s->valid_until->toDateString(), ShopClock::TZ), false) : null;

        return [
            'kind_label' => $isQuote ? 'Quote' : 'Enquiry',
            'assigned_to' => $f->assigned_to,
            'plate' => $carModel?->reg_no ? RegNo::pretty($carModel->reg_no) : null,
            'car_model' => $carModel ? trim($carModel->make.' '.$carModel->model) : '',
            'area' => $s->customer?->area,
            'initials' => collect(preg_split('/\s+/', trim($assigneeName)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''),
            'created_label' => $s->created_at->timezone(ShopClock::TZ)->format('j M'),
            'valid_label' => $isQuote ? 'Valid until '.$s->valid_until->format('j M').($validLeft > 0 ? " ({$validLeft} day".($validLeft > 1 ? 's' : '').' left)' : ($validLeft === 0 ? ' (last day)' : ' (expired)')) : null,
            'savings_pct' => $isQuote && $s->savings_paise > 0 ? (int) round($s->savings_paise * 100 / ($s->total_paise + $s->savings_paise)) : null,
            'template_label' => match (true) {
                ! $isQuote => 'Enquiry follow-up',
                $f->kind === 'visit' => 'Visit day reminder',
                default => 'Day '.$f->step.' quote follow-up',
            },
            'convert_url' => $isQuote ? route('quotations.convert', $s) : null,
            'quote_url' => ! $isQuote ? route('quotations.create', ['lead' => $s->id]) : null,
            'id' => $f->id,
            'type' => $isQuote ? 'quotation' : 'lead',
            'subject_id' => $s->id,
            'number' => $isQuote ? $s->number : null,
            'status' => $s->status,
            'status_label' => $s->statusLabel(),
            'name' => $name,
            'mobile' => Phone::pretty($mobile),
            'car' => $car,
            'asked' => $asked,
            'lines' => $lines,
            'value_paise' => $value,
            'value' => $value ? Money::format($value) : '',
            'savings' => $isQuote && $s->savings_paise > 0 ? Money::format($s->savings_paise) : null,
            'step_label' => $f->stepLabel(),
            'since' => $since,
            'meta' => $f->stepLabel().' · '.$since,
            'due_on' => $f->due_on->toDateString(),
            'due_label' => match (true) {
                $late > 1 => $late.' days late',
                $late === 1 => '1 day late',
                $late === 0 => 'Today',
                $late === -1 => 'Tomorrow',
                default => $due->format('D j M'),
            },
            'overdue' => $late > 0,
            'bucket' => $late > 0 ? 'overdue' : ($late === 0 ? 'today' : 'upcoming'),
            'last_outcome' => $last ? (FollowUp::OUTCOMES[$last->outcome] ?? $last->outcome) : null,
            'last_note' => $last?->outcome_note,
            'visit_on' => $isQuote || $s instanceof Lead ? $s->visit_on?->format('D j M') : null,
            'assignee' => $f->assignee?->name,
            'message' => $message,
            'wa_url' => route('followups.whatsapp', $f),
            'tel' => FollowUpMessages::telLink($mobile),
            'call_url' => route('followups.call', $f),
            'log_url' => route('followups.log', $f),
            'undo_url' => route('followups.undo', $f),
            'show_url' => $isQuote ? route('quotations.show', $s) : route('leads.index', ['highlight' => $s->id]),
            'contacted' => (bool) $f->contacted_at,
        ];
    }
}
