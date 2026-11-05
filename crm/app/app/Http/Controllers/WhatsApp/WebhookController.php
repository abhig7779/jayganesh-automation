<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WebhookProcessor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Meta → us. Public, CSRF-exempt (routes/public/whatsapp.php). */
class WebhookController extends Controller
{
    /** GET: Meta's one-time subscription check. Echo hub.challenge only for our verify token. */
    public function verify(Request $request): Response
    {
        $token = (string) config('whatsapp.verify_token');

        if ($request->query('hub_mode') === 'subscribe'
            && $token !== ''
            && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /** POST: statuses and inbound messages, signed with the app secret (X-Hub-Signature-256). */
    public function receive(Request $request, WebhookProcessor $processor): Response
    {
        $secret = (string) config('whatsapp.app_secret');
        $raw = $request->getContent();
        $header = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $raw, $secret);

        if ($secret === '' || $header === '' || ! hash_equals($expected, $header)) {
            return response('Invalid signature', 403);
        }

        $payload = json_decode($raw, true);
        if (is_array($payload) && ($payload['object'] ?? null) === 'whatsapp_business_account') {
            $processor->process($payload);
        }

        // Always 200 for a signed body, or Meta keeps re-sending it.
        return response('OK', 200);
    }
}
