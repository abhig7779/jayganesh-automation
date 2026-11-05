<?php

namespace App\Http\Controllers\Quotations;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quotations\FollowUpMessages;
use App\Services\Quotations\FollowUpScheduler;
use App\Support\Dashboard\FollowUps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** "Follow-ups today": the daily chase list (UX-FLOWS §3.2 steps 4–5). */
class FollowUpController extends Controller
{
    public function __construct(private readonly FollowUpScheduler $scheduler) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $mine = Quotation::seesAll($user) ? $request->query('who', 'all') === 'mine' : true;

        return view('followups.index', [
            'rows' => FollowUps::rowsForUser($user, 200, $mine),
            'upcoming' => FollowUps::upcomingForUser($user, 7, 100, $mine),
            'seesAll' => Quotation::seesAll($user),
            'mine' => $mine,
            'outcomes' => FollowUp::OUTCOMES,
        ]);
    }

    /**
     * Log result. htmx / fetch get 200 with an HX-Trigger "followup-logged" event (the row
     * animates out and the Undo toast shows); a plain form post redirects back.
     */
    public function log(Request $request, FollowUp $followUp): Response|JsonResponse|RedirectResponse
    {
        $this->authorizeFollowUp($request->user(), $followUp);
        $data = $request->validate([
            'outcome' => ['required', Rule::in(array_keys(FollowUp::OUTCOMES))],
            'date' => ['nullable', 'date_format:Y-m-d', Rule::requiredIf(in_array($request->input('outcome'), FollowUp::DATED_OUTCOMES, true))],
            'note' => ['nullable', 'string', 'max:500'],
            'channel' => ['nullable', Rule::in(['whatsapp', 'call'])],
        ], ['date.required' => 'Pick a date.']);

        $f = $this->scheduler->log($followUp, $data['outcome'], $request->user(), $data['date'] ?? null, $data['note'] ?? null, $data['channel'] ?? null);
        $toast = $this->toastText($f);

        if ($request->header('HX-Request') || $request->expectsJson()) {
            return response('', 200)->header('HX-Trigger', json_encode(['followup-logged' => [
                'id' => $f->id, 'message' => $toast, 'undo_url' => route('followups.undo', $f),
            ]]));
        }

        return back()->with('status', $toast);
    }

    public function undo(Request $request, FollowUp $followUp): Response|RedirectResponse
    {
        $this->authorizeFollowUp($request->user(), $followUp);
        $this->scheduler->undo($followUp);

        if ($request->header('HX-Request') || $request->expectsJson()) {
            return response('', 200)->header('HX-Trigger', json_encode(['followup-restored' => ['id' => $followUp->id]]));
        }

        return back()->with('status', 'Undone. The follow-up is back on the list.');
    }

    /** Records that staff opened WhatsApp, then opens wa.me with the message for this step. */
    public function whatsapp(Request $request, FollowUp $followUp): RedirectResponse
    {
        $this->authorizeFollowUp($request->user(), $followUp);
        $followUp->forceFill(['contacted_at' => now(), 'channel' => 'whatsapp'])->save();
        $subject = $followUp->subject;
        if ($subject instanceof Quotation && ! $subject->sent_at) {
            $subject->forceFill(['sent_at' => now()])->save();
        }

        // Staff may edit the preview on the work-queue screen before sending (?text=…).
        $text = trim((string) $request->query('text', ''));

        return redirect()->away(FollowUpMessages::waLink(
            FollowUpMessages::mobileFor($subject),
            $text !== '' ? mb_substr($text, 0, 2000) : app(FollowUpMessages::class)->forFollowUp($followUp),
        ));
    }

    public function call(Request $request, FollowUp $followUp): RedirectResponse
    {
        $this->authorizeFollowUp($request->user(), $followUp);
        $followUp->forceFill(['contacted_at' => now(), 'channel' => 'call'])->save();

        return redirect()->away(FollowUpMessages::telLink(FollowUpMessages::mobileFor($followUp->subject)));
    }

    private function toastText(FollowUp $f): string
    {
        $name = $f->subject instanceof Quotation ? ($f->subject->customer?->name ?? 'Customer') : $f->subject->displayName();
        $first = strtok((string) $name, ' ');

        return match ($f->outcome) {
            'coming_on' => "Visit booked: {$first} on ".$f->visit_on->format('D j M'),
            'call_later' => "Next follow-up with {$first} on ".FollowUp::query()->where('subject_type', $f->subject_type)->where('subject_id', $f->subject_id)->pending()->orderByDesc('id')->first()?->due_on?->format('D j M'),
            'wants_better_price' => 'Owner/Admin asked to approve a better price',
            'no_answer' => "No answer. Retry {$first} tomorrow",
            default => "Closed as lost: {$f->outcomeLabel()}",
        };
    }

    /** Lead follow-ups need leads.manage; quotation follow-ups need quotations.manage. Sales only their own. */
    private function authorizeFollowUp(User $user, FollowUp $f): void
    {
        abort_unless($user->can($f->subject_type === Lead::class ? 'leads.manage' : 'quotations.manage'), 403);
        abort_unless(Quotation::seesAll($user) || $f->assigned_to === $user->id, 403);
    }
}
