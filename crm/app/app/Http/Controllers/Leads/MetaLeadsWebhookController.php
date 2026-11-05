<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\LeadIntake;
use App\Services\Leads\LeadIntakeService;
use App\Services\Leads\MetaLeadFetcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Meta lead ads → Leads. Public, CSRF-exempt (routes/public/meta-leads.php), off unless
 * META_LEADS_ENABLED=true. Same signature check as the WhatsApp webhook.
 */
class MetaLeadsWebhookController extends Controller
{
    /** GET: Meta's one-time subscription check. */
    public function verify(Request $request): Response
    {
        abort_unless(config('leads.meta.enabled'), 404);
        $token = (string) config('leads.meta.verify_token');

        if ($request->query('hub_mode') === 'subscribe'
            && $token !== ''
            && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /** POST: {"object":"page","entry":[{"changes":[{"field":"leadgen","value":{"leadgen_id":…}}]}]} */
    public function receive(Request $request, MetaLeadFetcher $fetcher, LeadIntakeService $intake): Response
    {
        abort_unless(config('leads.meta.enabled'), 404);

        $secret = (string) config('leads.meta.app_secret');
        $raw = $request->getContent();
        $header = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $raw, $secret);

        if ($secret === '' || $header === '' || ! hash_equals($expected, $header)) {
            return response('Invalid signature', 403);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload) || ($payload['object'] ?? null) !== 'page') {
            return response('OK', 200);
        }

        $failed = false;
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $leadgenId = (string) ($change['value']['leadgen_id'] ?? '');
                if (($change['field'] ?? null) !== 'leadgen' || $leadgenId === '') {
                    continue;
                }
                if (LeadIntake::query()->where('external_id', 'meta:'.$leadgenId)->exists()) {
                    continue; // Meta re-sent one we already have
                }

                try {
                    ['data' => $data, 'raw' => $lead] = $fetcher->fetch($leadgenId);
                    $intake->receive($data, [
                        'channel' => 'meta_webhook',
                        'external_id' => 'meta:'.$leadgenId,
                        'payload' => ['leadgen' => $change['value'], 'lead' => $lead],
                    ]);
                } catch (ValidationException $e) {
                    // No usable mobile number in the form: nothing staff could call. Log and move on.
                    Log::warning('Meta lead skipped: '.$leadgenId.' '.json_encode($e->errors()));
                } catch (Throwable $e) {
                    report($e);
                    $failed = true;
                }
            }
        }

        // 500 makes Meta retry later (fetch failed); repeats are skipped by leadgen_id.
        return $failed ? response('Retry later', 500) : response('OK', 200);
    }
}
