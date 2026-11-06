<?php

namespace App\Services\WhatsApp;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Finishes WhatsApp Embedded Signup on the server (coexistence: the number stays on the WhatsApp
 * Business app on the shop phone AND comes onto the Cloud API for the CRM).
 *
 *   1. code (valid ~30 s) → business-integration system-user token  GET /oauth/access_token
 *   2. subscribe our app to the WhatsApp account                       POST /{waba}/subscribed_apps
 *   3. read the number: is_on_biz_app, platform_type, display number   GET /{phone-number-id}
 *   4. coexistence only: ask Meta to sync contacts, then chat history  POST /{phone-number-id}/smb_app_data
 *      (each can run once, within 24 h of onboarding; results arrive by webhook)
 *
 * No /register call: coexistence numbers are registered by the Business app already, and we never
 * request a code or OTP. The token is stored encrypted and is never logged.
 */
class EmbeddedSignup
{
    /** App "Automation" and its Facebook Login for Business configuration (both public ids). */
    public const DEFAULT_APP_ID = '2901906313477061';

    public const DEFAULT_CONFIG_ID = '4202556123367996';

    public static function appId(): string
    {
        return (string) (config('whatsapp.app_id') ?: self::DEFAULT_APP_ID);
    }

    public static function configId(): string
    {
        return (string) (config('whatsapp.embedded_signup_config_id') ?: self::DEFAULT_CONFIG_ID);
    }

    public static function graphVersion(): string
    {
        return (string) config('whatsapp.graph_version', 'v23.0');
    }

    /**
     * @param  array  $event  the WA_EMBEDDED_SIGNUP "data" (waba_id, phone_number_id, business_id…)
     */
    public function connect(string $code, array $event, ?int $userId = null): WhatsAppConnection
    {
        $wabaId = (string) ($event['waba_id'] ?? '');
        if (! preg_match('/^\d{5,32}$/', $wabaId)) {
            throw new WhatsAppException('signup_no_waba', 'Meta did not send a WhatsApp account id.');
        }

        $token = $this->exchangeCode($code);
        $api = $this->http($token);

        $this->ok($api->post("/{$wabaId}/subscribed_apps"));

        $phoneId = (string) ($event['phone_number_id'] ?? '');
        if (! preg_match('/^\d{5,32}$/', $phoneId)) {
            $phoneId = (string) $this->ok($api->get("/{$wabaId}/phone_numbers", ['fields' => 'id']))->json('data.0.id');
        }
        if ($phoneId === '') {
            throw new WhatsAppException('signup_no_number', 'No phone number is attached to this WhatsApp account yet.');
        }

        $phone = $this->ok($api->get("/{$phoneId}", [
            'fields' => 'display_phone_number,verified_name,is_on_biz_app,platform_type',
        ]))->json();

        // One live connection at a time; older rows are kept (never deleted) but marked.
        WhatsAppConnection::query()->where('status', 'connected')->update(['status' => 'disconnected']);

        $connection = WhatsAppConnection::create([
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneId,
            'business_id' => isset($event['business_id']) ? (string) $event['business_id'] : null,
            'display_phone' => $phone['display_phone_number'] ?? null,
            'verified_name' => $phone['verified_name'] ?? null,
            'token' => $token,
            'is_on_biz_app' => (bool) ($phone['is_on_biz_app'] ?? false),
            'platform_type' => $phone['platform_type'] ?? null,
            'status' => 'connected',
            'subscribed_at' => now(),
            'signup_event' => $this->safeEvent($event),
            'connected_by' => $userId,
        ]);

        if ($connection->isCoexistence()) {
            $this->sync($connection);
        }

        Setting::put('whatsapp.mode', 'cloud_api', null, $userId);

        Log::info('WhatsApp connected from the CRM', [
            'waba_id' => $wabaId, 'phone_number_id' => $phoneId,
            'coexistence' => $connection->isCoexistence(), 'platform_type' => $connection->platform_type,
        ]);

        return $connection;
    }

    /** Contacts first, then history (Meta's order). Each one runs once; failures are kept, not thrown. */
    public function sync(WhatsAppConnection $connection): void
    {
        $api = $this->http($connection->token);
        $errors = [];

        foreach (['smb_app_state_sync' => 'contacts_sync_request_id', 'history' => 'history_sync_request_id'] as $type => $column) {
            if ($connection->{$column}) {
                continue;
            }
            try {
                $response = $this->ok($api->asJson()->post("/{$connection->phone_number_id}/smb_app_data", [
                    'messaging_product' => 'whatsapp',
                    'sync_type' => $type,
                ]));
                $connection->{$column} = (string) ($response->json('request_id') ?? 'ok');
            } catch (WhatsAppException $e) {
                $errors[] = "{$type}: ".($e->getMessage() ?: $e->errorCode);
            }
        }

        $connection->synced_at = now();
        $connection->last_error = $errors ? mb_substr(implode('; ', $errors), 0, 500) : null;
        $connection->save();
    }

    private function exchangeCode(string $code): string
    {
        $secret = (string) config('whatsapp.app_secret');
        if ($secret === '') {
            throw new WhatsAppException('not_configured', 'WHATSAPP_APP_SECRET is not set on the server.');
        }

        $response = $this->ok($this->base()->get('/oauth/access_token', [
            'client_id' => self::appId(),
            'client_secret' => $secret,
            'code' => $code,
        ]));

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new WhatsAppException('signup_no_token', 'Meta did not return an access token. Please try again.');
        }

        return $token;
    }

    private function base(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('whatsapp.graph_url', 'https://graph.facebook.com'), '/').'/'.self::graphVersion())
            ->acceptJson()
            ->timeout((int) config('whatsapp.http.timeout', 20))
            ->connectTimeout((int) config('whatsapp.http.connect_timeout', 5));
    }

    private function http(string $token): PendingRequest
    {
        return $this->base()->withToken($token);
    }

    /** Throws a WhatsAppException with Meta's code and message (never the request, which holds secrets). */
    private function ok(Response|callable $response): Response
    {
        try {
            $response = is_callable($response) ? $response() : $response;
        } catch (ConnectionException) {
            throw new WhatsAppException('network');
        }
        if ($response->successful()) {
            return $response;
        }

        $code = $response->json('error.code') ?? ($response->serverError() ? 'server' : $response->status());
        $message = $response->json('error.error_user_msg') ?? $response->json('error.message');

        throw new WhatsAppException((string) $code, is_string($message) ? mb_substr($message, 0, 300) : null, $response->status());
    }

    /** Keep only the ids and step info from the browser event, never anything token-like. */
    private function safeEvent(array $event): array
    {
        return array_intersect_key($event, array_flip([
            'waba_id', 'phone_number_id', 'business_id', 'event', 'current_step', 'session_id',
        ]));
    }
}
