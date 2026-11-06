<?php

namespace App\Services\WhatsApp;

use App\Models\MessageLog;
use App\Models\Setting;
use App\Services\WhatsApp\Jobs\SendAiReply;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI auto-reply on WhatsApp, mainly for chats that start from Click-to-WhatsApp ads.
 *
 * Mode (Setting `whatsapp.ai_reply`, set on the Connect WhatsApp page):
 *   off  – never (default)
 *   ads  – only chats whose customer clicked one of our ads in the last 72 hours
 *   all  – every customer chat
 *
 * A person always wins: when someone replies from the WhatsApp Business app on the shop phone
 * (coexistence "smb_message_echoes"), or the AI hands the chat over, the AI stays quiet in that
 * chat for a while. It also stops after a few replies a day per chat, never quotes prices or
 * offers that are not in the ad the customer clicked, and never confirms bookings.
 *
 * Model: Claude when an Anthropic key is set, otherwise OpenAI (same choice as the Google review
 * replies, automation/src/lib/reply-writer.mjs). If one fails, the other is tried.
 */
class AiReplyAgent
{
    public const MODES = ['off', 'ads', 'all'];

    public function __construct(private CloudApiClient $client) {}

    public static function mode(): string
    {
        $mode = Setting::get('whatsapp.ai_reply', 'off');

        return in_array($mode, self::MODES, true) ? $mode : 'off';
    }

    /** Called by the webhook for each NEW inbound message. Never throws. */
    public function onInbound(MessageLog $in, ?array $referral): void
    {
        try {
            $chat = AiChat::for($in->to);

            if ($referral && ($referral['source_type'] ?? 'ad') === 'ad') {
                $chat->ad_referral_at = now();
                $chat->ad_referral = array_filter(array_intersect_key($referral, array_flip([
                    'source_id', 'source_url', 'source_type', 'headline', 'body', 'media_type', 'ctwa_clid',
                ])));
                $chat->save();
            }

            if (! $this->wants($in, $chat)) {
                return;
            }

            SendAiReply::dispatch($in->id)->delay(now()->addSeconds((int) config('whatsapp.ai.debounce_seconds', 20)));
        } catch (Throwable $e) {
            Log::warning('WhatsApp AI reply was not queued', ['log_id' => $in->id, 'error' => $e->getMessage()]);
        }
    }

    /** A person replied from the WhatsApp Business app: let them handle this chat. */
    public function personReplied(string $contact): void
    {
        AiChat::for($contact)->pause('owner_replied', (int) config('whatsapp.ai.pause_hours_after_owner', 12));
    }

    private function wants(MessageLog $in, AiChat $chat): bool
    {
        $mode = self::mode();
        if ($mode === 'off' || ! $this->client->isConfigured() || ! $this->hasModel()) {
            return false;
        }
        if ($mode === 'ads' && ! $chat->isAdChat()) {
            return false;
        }
        if ($chat->isPaused() || $chat->repliesToday() >= (int) config('whatsapp.ai.max_replies_per_day', 8)) {
            return false;
        }
        if (($in->body['keyword'] ?? null) !== null) {
            return false; // STOP / START are handled by the webhook, no reply
        }
        // History synced from the phone or late re-deliveries: only answer fresh messages.
        $at = $in->delivered_at ?? $in->created_at;

        return $at === null || $at->gt(now()->subMinutes(15));
    }

    /**
     * Runs from the queue a few seconds after the message. Returns the sent log, or null when it
     * decided not to reply (a newer message came in, a person took over, limits reached…).
     */
    public function reply(MessageLog $in): ?MessageLog
    {
        $chat = AiChat::for($in->to);
        if (! $this->wants($in->fresh() ?? $in, $chat)) {
            return null;
        }

        // The customer sent more messages since: the job for the newest one answers all of them.
        $newer = MessageLog::query()->where('to', $in->to)->where('direction', 'in')->where('id', '>', $in->id)->exists();
        if ($newer) {
            return null;
        }

        $first = ! MessageLog::query()->where('to', $in->to)->where('direction', 'out')
            ->where('created_at', '>', now()->subDays(30))->exists();

        $answer = $this->write($this->history($in->to), $chat, $first, $in->body['profile_name'] ?? null);
        if ($answer === null || $answer['reply'] === '') {
            return null;
        }

        $wamid = $this->client->sendText($in->to, $answer['reply']);
        try {
            $this->client->markRead((string) $in->wa_message_id);
        } catch (WhatsAppException) {
            // blue ticks are nice to have only
        }

        $log = MessageLog::create([
            'branch_id' => $in->branch_id,
            'customer_id' => $in->customer_id,
            'to' => $in->to,
            'direction' => 'out',
            'channel' => 'cloud_api',
            'category' => 'service',
            'body' => ['text' => $answer['reply'], 'ai' => true, 'handoff' => $answer['handoff']],
            'wa_message_id' => $wamid,
            'status' => 'sent',
            'sent_at' => now(),
            'attempts' => 1,
        ]);

        $chat->countReply();
        if ($answer['handoff']) {
            $chat->pause('handoff', (int) config('whatsapp.ai.pause_hours_after_handoff', 24));
        }

        return $log;
    }

    /** Last messages of this chat, oldest first, as [role, text]. */
    private function history(string $contact): array
    {
        return MessageLog::query()
            ->where('to', $contact)
            ->where('created_at', '>', now()->subDays(3))
            ->orderByDesc('id')
            ->limit(16)
            ->get()
            ->reverse()
            ->map(function (MessageLog $m) {
                $text = (string) ($m->body['text'] ?? '');
                if ($text === '' && $m->template) {
                    $text = '[template '.$m->template.' sent]';
                }

                return $text === '' ? null : ['role' => $m->direction === 'in' ? 'user' : 'assistant', 'text' => $text];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function hasModel(): bool
    {
        return filled($this->anthropicKey()) || filled($this->openaiKey());
    }

    private function anthropicKey(): ?string
    {
        return config('whatsapp.ai.anthropic_key') ?: config('services.anthropic.key');
    }

    private function openaiKey(): ?string
    {
        return config('whatsapp.ai.openai_key') ?: config('services.openai.key');
    }

    /** @return array{reply: string, handoff: bool}|null */
    private function write(array $history, AiChat $chat, bool $first, ?string $name): ?array
    {
        $system = $this->systemPrompt($chat, $first, $name);
        $order = array_filter([
            $this->anthropicKey() ? fn () => $this->claude($system, $history) : null,
            $this->openaiKey() ? fn () => $this->openai($system, $history) : null,
        ]);

        foreach ($order as $attempt) {
            try {
                $parsed = $this->parse($attempt());
                if ($parsed !== null) {
                    return $parsed;
                }
            } catch (Throwable $e) {
                Log::warning('WhatsApp AI reply model failed', ['error' => mb_substr($e->getMessage(), 0, 300)]);
            }
        }

        return null;
    }

    private function claude(string $system, array $history): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->anthropicKey(),
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->acceptJson()->asJson()->post('https://api.anthropic.com/v1/messages', [
            'model' => config('whatsapp.ai.claude_model', 'claude-sonnet-5-5'),
            'max_tokens' => 2000,
            'system' => $system,
            'messages' => $this->alternate($history),
        ]);
        if (! $response->successful()) {
            throw new \RuntimeException('Anthropic '.$response->status().': '.$response->json('error.message'));
        }
        if ($response->json('stop_reason') === 'refusal') {
            return '';
        }

        return collect($response->json('content', []))->where('type', 'text')->pluck('text')->implode('');
    }

    private function openai(string $system, array $history): string
    {
        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($this->alternate($history) as $m) {
            $messages[] = $m;
        }
        $response = Http::withToken((string) $this->openaiKey())->timeout(60)->acceptJson()->asJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('whatsapp.ai.openai_model', 'gpt-5.4'),
                'max_completion_tokens' => 2500,
                'messages' => $messages,
            ]);
        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI '.$response->status().': '.$response->json('error.message'));
        }

        return (string) $response->json('choices.0.message.content', '');
    }

    /** Merge same-role neighbours and start with the customer, as both APIs expect. */
    private function alternate(array $history): array
    {
        $out = [];
        foreach ($history as $m) {
            if ($out === [] && $m['role'] !== 'user') {
                continue;
            }
            $last = array_key_last($out);
            if ($last !== null && $out[$last]['role'] === $m['role']) {
                $out[$last]['content'] .= "\n".$m['text'];
            } else {
                $out[] = ['role' => $m['role'], 'content' => $m['text']];
            }
        }

        return $out;
    }

    /** @return array{reply: string, handoff: bool}|null */
    private function parse(string $raw): ?array
    {
        if (! preg_match('/\{.*\}/s', $raw, $m)) {
            return null;
        }
        $data = json_decode($m[0], true);
        if (! is_array($data) || ! isset($data['reply']) || ! is_string($data['reply'])) {
            return null;
        }
        $reply = trim($data['reply']);
        if (mb_strlen($reply) > 1000) {
            $reply = mb_substr($reply, 0, 1000);
        }

        return ['reply' => $reply, 'handoff' => (bool) ($data['handoff'] ?? false)];
    }

    private function systemPrompt(AiChat $chat, bool $first, ?string $name): string
    {
        $shop = Setting::get('shop.name', 'Jay Ganesh Car Accessories');
        $address = Setting::get('shop.address', 'Kawadipat Toll Naka, Loni Kalbhor, Pune');
        $maps = Setting::get('shop.maps_url');
        $phone = Setting::get('shop.phone', '+91 99813 97676');

        $ad = '';
        if ($chat->isAdChat() && $chat->ad_referral) {
            $r = $chat->ad_referral;
            $ad = "\n\nThis customer came from our Click-to-WhatsApp ad. The ad said:\n"
                .'Headline: '.($r['headline'] ?? '(none)')."\n"
                .'Text: '.($r['body'] ?? '(none)')."\n"
                .'You may repeat what this ad says (including any offer in it), but nothing beyond it.';
        }

        $intro = $first
            ? "- This is our first reply in this chat: start by saying you are {$shop}'s automated assistant and a team member will also see the chat."
            : '- Do not introduce yourself again.';

        $who = $name ? "The customer's WhatsApp name is \"{$name}\" (use the first name only if it looks like a real name)." : '';

        return <<<PROMPT
You reply on WhatsApp for {$shop}, a car accessories shop and PPF & detailing studio at {$address}.{$ad}

What the shop does: seat covers, LED headlights and lighting, Android stereos, dashcams, paint protection film (PPF), ceramic coating, detailing and other car accessories. Authorised dealer for Lumar, Garware, 3M, Aultragard, DHC, Night Scene and TVS. Rated 4.9 on Google. Call: {$phone}.{$this->mapsLine($maps)}
{$who}

Your job: answer the customer's question briefly and help them plan a visit. Find out, one question at a time and only what is still missing: the car model (and year), what they want done, and when they would like to come.

Rules:
{$intro}
- Reply in the customer's language and script (English, Hindi, Marathi, or Hindi/Marathi in English letters). Keep it to 1–3 short sentences. Friendly and respectful, like the shop's own staff. At most one emoji.
- Never state a price, discount, offer, stock level, timing, warranty or opening hours unless it is in the ad text above. For prices say the team will share the exact price for their car.
- Never confirm a booking, appointment or delivery date; say the team will confirm.
- Hand over to a person (handoff true) when the customer: asks for a price or quote, wants to book or visit at a time, asks to talk to someone or call, complains, or asks anything you are not sure about. In that reply, say a team member will reply here shortly.
- Do not discuss anything unrelated to the shop. Never ask for payment, OTPs, passwords or documents.

Answer with JSON only: {"reply": "<the WhatsApp message>", "handoff": true|false}
PROMPT;
    }

    private function mapsLine(?string $maps): string
    {
        return $maps ? " Location: {$maps}" : '';
    }
}
