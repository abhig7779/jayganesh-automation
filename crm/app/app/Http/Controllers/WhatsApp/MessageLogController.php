<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\MessageLog;
use App\Services\WhatsApp\WebhookProcessor;
use App\Services\WhatsApp\WhatsApp;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class MessageLogController extends Controller
{
    public function __construct(private WhatsApp $whatsApp) {}

    /** WhatsApp log (Owner/Admin), with the setup card on top for the Owner. */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => 'nullable|in:'.implode(',', MessageLog::STATUSES),
            'template' => 'nullable|string|max:80',
            'direction' => 'nullable|in:out,in',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'q' => 'nullable|string|max:30',
        ]);

        $tz = 'Asia/Kolkata';
        $messages = MessageLog::query()
            ->with('customer:id,name,mobile')
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['template'] ?? null, fn ($q, $v) => $q->where('template', $v))
            ->when($filters['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v, $tz)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', Carbon::parse($v, $tz)->endOfDay()->utc()))
            ->when(strlen(preg_replace('/\D+/', '', $filters['q'] ?? '')) >= 4, fn ($q) => $q->where('to', 'like', '%'.preg_replace('/\D+/', '', $filters['q']).'%'))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $templates = MessageLog::query()->whereNotNull('template')->distinct()->orderBy('template')->pluck('template');
        $setup = $request->user()->hasRole('owner') ? $this->setupStatus() : null;

        return view('whatsapp.messages.index', compact('messages', 'templates', 'filters', 'setup'));
    }

    public function resend(MessageLog $log): RedirectResponse
    {
        abort_unless($log->isOutgoing(), 404);

        $new = $this->whatsApp->resend($log);

        if ($new->isFailed()) {
            return back()->with('error', 'Not sent: '.$new->reason());
        }
        if ($new->isManual()) {
            return $this->openManual($new);
        }

        return back()->with('status', 'Sending again on WhatsApp to '.Phone::pretty($new->to).'.');
    }

    /** Manual mode: note that staff opened it, then open WhatsApp with the message typed in. */
    public function manual(MessageLog $log): RedirectResponse
    {
        abort_unless($log->isOutgoing() && $log->isManual(), 404);

        return $this->openManual($log);
    }

    /** Owner only: one template to a test number, sent now, to check the setup works. */
    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'to' => 'required|string|max:20',
            'template' => 'required|alpha_dash|max:80',
            'language' => 'nullable|string|max:10',
            'params' => 'nullable|string|max:500',
        ]);

        $to = Phone::normalize($data['to']);
        if (! Phone::isValidIndianMobile($to)) {
            return back()->withInput()->withErrors(['to' => 'Mobile number needs 10 digits.']);
        }
        if (! $this->whatsApp->isAutomatic() || ! $this->whatsApp->isConfigured()) {
            return back()->withInput()->with('error', 'Switch Settings > WhatsApp > Sending mode to Automatic and fill the WHATSAPP_ values in .env first.');
        }

        $params = array_values(array_filter(array_map('trim', explode('|', (string) ($data['params'] ?? ''))), fn ($p) => $p !== ''));
        $log = $this->whatsApp->sendTemplate($to, $data['template'], $params, null, null, null, array_filter([
            'category' => 'utility',
            'language' => $data['language'] ?? null,
        ]));

        return $log->isFailed()
            ? back()->withInput()->with('error', 'Test not sent: '.$log->reason())
            : back()->with('status', 'Test template sent to '.Phone::pretty($to).'. Watch for the ticks below.');
    }

    private function openManual(MessageLog $log): RedirectResponse
    {
        if ($log->status === 'queued') {
            $log->forceFill(['status' => 'manual_opened', 'sent_at' => now()])->save();
        }

        return redirect()->away($this->whatsApp->manualLink($log->to, $log->previewText()));
    }

    /** Yes/no only. Values never leave .env. */
    private function setupStatus(): array
    {
        $last = Cache::get(WebhookProcessor::LAST_WEBHOOK_KEY);

        return [
            'automatic' => $this->whatsApp->isAutomatic(),
            'env' => [
                'WHATSAPP_TOKEN' => filled(config('whatsapp.token')),
                'WHATSAPP_PHONE_NUMBER_ID' => filled(config('whatsapp.phone_number_id')),
                'WHATSAPP_WABA_ID' => filled(config('whatsapp.waba_id')),
                'WHATSAPP_APP_SECRET' => filled(config('whatsapp.app_secret')),
                'WHATSAPP_VERIFY_TOKEN' => filled(config('whatsapp.verify_token')),
            ],
            'graph_version' => config('whatsapp.graph_version'),
            'webhook_url' => route('whatsapp.webhook'),
            'last_webhook_at' => $last ? Carbon::parse($last) : null,
        ];
    }
}
