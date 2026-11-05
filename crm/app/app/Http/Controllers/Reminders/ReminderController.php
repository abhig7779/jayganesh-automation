<?php

namespace App\Http\Controllers\Reminders;

use App\Http\Controllers\Controller;
use App\Models\ServiceReminder;
use App\Models\Setting;
use App\Services\Reminders\ReminderService;
use App\Support\Dashboard\Reminders;
use App\Support\Quotations\ShopClock;
use App\Support\RegNo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Reminders today": service check-ups and Google review requests in one list (reminders.manage). */
class ReminderController extends Controller
{
    public const VIEWS = [
        'today' => 'To do',
        'upcoming' => 'Next 30 days',
        'sent' => 'Sent',
        'closed' => 'Done & skipped',
    ];

    public function __construct(private readonly ReminderService $reminders) {}

    public function index(Request $request): View
    {
        $show = array_key_exists($request->query('show'), self::VIEWS) ? $request->query('show') : 'today';
        $type = in_array($request->query('type'), ['service', 'review'], true) ? $request->query('type') : null;
        $q = trim((string) $request->query('q', ''));

        $base = ServiceReminder::query()
            ->when($type, fn ($w) => $w->where('type', $type))
            ->when($q !== '', fn ($w) => $this->search($w, $q));

        $query = (clone $base)->with(['customer', 'car', 'rule', 'sender']);
        match ($show) {
            'upcoming' => $query->whereIn('status', ServiceReminder::OPEN)
                ->whereDate('due_on', '>', ShopClock::today())
                ->whereDate('due_on', '<=', ShopClock::todayDate()->addDays(30)->toDateString())
                ->orderBy('due_on')->orderBy('id'),
            'sent' => $query->where('status', ServiceReminder::SENT)->latest('sent_at')->latest('id'),
            'closed' => $query->whereIn('status', [ServiceReminder::DONE, ServiceReminder::SKIPPED])->latest('closed_at')->latest('id'),
            default => $query->dueByToday()->orderBy('due_on')->orderBy('id'),
        };

        $page = $query->paginate(50)->withQueryString();

        $counts = [
            'today' => (clone $base)->dueByToday()->count(),
            'overdue' => (clone $base)->overdue()->count(),
            'upcoming' => (clone $base)->whereIn('status', ServiceReminder::OPEN)->whereDate('due_on', '>', ShopClock::today())
                ->whereDate('due_on', '<=', ShopClock::todayDate()->addDays(30)->toDateString())->count(),
            'sent' => (clone $base)->where('status', ServiceReminder::SENT)->count(),
        ];

        return view('reminders.index', [
            'rows' => collect($page->items())->map(fn ($r) => Reminders::row($r))->all(),
            'page' => $page,
            'show' => $show,
            'type' => $type,
            'q' => $q,
            'counts' => $counts,
            'views' => self::VIEWS,
            'reviewLinkMissing' => (bool) Setting::get('reviews.enabled') && $this->reminders->reviewLink() === '',
        ]);
    }

    /** Manual mode: note who opened WhatsApp, then open wa.me with the message typed in. */
    public function whatsapp(Request $request, ServiceReminder $reminder): RedirectResponse
    {
        $link = $this->reminders->manualLink($reminder);
        if (! $link) {
            return back()->withErrors(['reminder' => 'This customer has no mobile number. Add one on their profile.']);
        }
        if ($reminder->isOpen()) {
            $this->reminders->markSent($reminder, $request->user(), 'whatsapp');
        }

        return redirect()->away($link);
    }

    /** Cloud API mode with an approved template: send now. */
    public function send(Request $request, ServiceReminder $reminder): RedirectResponse
    {
        if (! $this->reminders->sendsAutomatically($reminder)) {
            return redirect()->route('reminders.whatsapp', $reminder);
        }
        if (! $reminder->customer?->mobile) {
            return back()->withErrors(['reminder' => 'This customer has no mobile number. Add one on their profile.']);
        }

        $log = $this->reminders->sendAutomatic($reminder, $request->user());
        if ($log->status === 'failed') {
            return back()->withErrors(['reminder' => 'Not sent: '.($log->error_text ?: 'WhatsApp refused the message').'. Try Call instead.']);
        }

        return back()->with('status', 'Sent to '.$reminder->customer->name.' on WhatsApp.');
    }

    public function done(Request $request, ServiceReminder $reminder): RedirectResponse
    {
        abort_if($reminder->status === ServiceReminder::CANCELLED, 422, 'This reminder was cancelled with its bill.');
        $this->reminders->close($reminder, $request->user(), ServiceReminder::DONE);

        return back()->with('status', 'Done: '.$reminder->customer?->name.' · '.$reminder->whatLabel());
    }

    public function skip(Request $request, ServiceReminder $reminder): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:200']]);
        abort_unless($reminder->isOpen() || $reminder->status === ServiceReminder::SENT, 422, 'This reminder is already closed.');
        $this->reminders->close($reminder, $request->user(), ServiceReminder::SKIPPED, $data['note'] ?? 'Skipped');

        return back()->with('status', 'Skipped: '.$reminder->customer?->name.' · '.$reminder->whatLabel());
    }

    private function search(Builder $w, string $q): void
    {
        $digits = preg_replace('/\D+/', '', $q);
        $plate = RegNo::normalize($q);
        $w->where(function (Builder $s) use ($q, $digits, $plate) {
            $s->whereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$q.'%')
                ->when(strlen($digits) >= 4, fn ($c) => $c->orWhere('mobile', 'like', '%'.substr($digits, -10).'%')))
                ->orWhere('service_name', 'like', '%'.$q.'%');
            if ($plate) {
                $s->orWhereHas('car', fn ($c) => $c->where('reg_no', 'like', '%'.$plate.'%'));
            }
        });
    }
}
