<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\WhatsApp\AiReplyAgent;
use App\Services\WhatsApp\EmbeddedSignup;
use App\Services\WhatsApp\WhatsAppConnection;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/** Owner only: connect the shop's WhatsApp number (Embedded Signup, coexistence) and the AI reply switch. */
class ConnectController extends Controller
{
    public function show(): View
    {
        return view('whatsapp.connect', [
            'connection' => WhatsAppConnection::current(),
            'envConfigured' => filled(config('whatsapp.token')) && filled(config('whatsapp.phone_number_id')),
            'appId' => EmbeddedSignup::appId(),
            'configId' => EmbeddedSignup::configId(),
            'graphVersion' => EmbeddedSignup::graphVersion(),
            'aiMode' => AiReplyAgent::mode(),
            'aiReady' => filled(config('whatsapp.ai.anthropic_key') ?: config('services.anthropic.key'))
                || filled(config('whatsapp.ai.openai_key') ?: config('services.openai.key')),
        ]);
    }

    /** Called by the page's JavaScript after Meta's popup finishes (code is valid ~30 seconds). */
    public function store(Request $request, EmbeddedSignup $signup): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:2000',
            'event' => 'required|array',
            'event.waba_id' => 'required|regex:/^\d{5,32}$/',
            'event.phone_number_id' => 'nullable|regex:/^\d{5,32}$/',
            'event.business_id' => 'nullable|regex:/^\d{5,32}$/',
            'event.event' => 'nullable|string|max:60',
            'event.session_id' => 'nullable|string|max:120',
        ]);

        try {
            $connection = $signup->connect($data['code'], $data['event'], $request->user()?->id);
        } catch (WhatsAppException $e) {
            Log::warning('WhatsApp Embedded Signup failed', ['code' => $e->errorCode, 'waba_id' => $data['event']['waba_id']]);

            // Our own codes have a plain message; for Meta's codes show Meta's words (the bill-sending
            // texts in ErrorMap would not fit a sign-up).
            $message = str_starts_with($e->errorCode, 'signup_') || in_array($e->errorCode, ['network', 'not_configured'], true) || ! $e->metaMessage
                ? $e->staffMessage()
                : 'Meta said: '.$e->metaMessage.' Please start "Connect WhatsApp" again.';

            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        return response()->json([
            'ok' => true,
            'number' => $connection->display_phone,
            'coexistence' => $connection->isCoexistence(),
            'sync_error' => $connection->last_error,
        ]);
    }

    /** Session logging: every WA_EMBEDDED_SIGNUP event (cancel / error too), for Meta support cases. */
    public function event(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|max:40',
            'event' => 'nullable|string|max:60',
            'data' => 'nullable|array',
        ]);
        $keep = array_intersect_key($data['data'] ?? [], array_flip([
            'waba_id', 'phone_number_id', 'business_id', 'current_step', 'error_message', 'error_code', 'session_id', 'timestamp',
        ]));
        Log::info('WhatsApp Embedded Signup event', ['event' => $data['event'] ?? null] + $keep);

        return response()->json(['ok' => true]);
    }

    public function ai(Request $request): RedirectResponse
    {
        $data = $request->validate(['mode' => 'required|in:'.implode(',', AiReplyAgent::MODES)]);
        Setting::put('whatsapp.ai_reply', $data['mode'], null, $request->user()?->id);

        return back()->with('status', match ($data['mode']) {
            'ads' => 'AI replies are on for chats from your Click-to-WhatsApp ads.',
            'all' => 'AI replies are on for all WhatsApp chats.',
            default => 'AI replies are off.',
        });
    }

    /** Ask Meta again for the one-time contacts/history sync (only the parts that did not start). */
    public function sync(EmbeddedSignup $signup): RedirectResponse
    {
        $connection = WhatsAppConnection::current();
        if (! $connection || ! $connection->isCoexistence()) {
            return back()->with('error', 'No WhatsApp Business app number is connected.');
        }
        $signup->sync($connection);

        return back()->with($connection->last_error ? 'error' : 'status', $connection->last_error
            ? 'Sync not started: '.$connection->last_error
            : 'Meta is syncing contacts and chat history. Keep the WhatsApp Business app open on the shop phone.');
    }
}
