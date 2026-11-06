<?php

namespace App\Services\WhatsApp;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\MessageLog;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Handles a verified Meta webhook body (field "messages"): delivery statuses for our messages and
 * inbound customer messages (handed to the AI agent), and coexistence echoes of messages sent from
 * the WhatsApp Business app (field "smb_message_echoes"). Safe to run twice on the same body (Meta re-delivers on timeouts):
 * statuses only move forward, inbound messages are unique by wamid, and STOP/START act once.
 */
class WebhookProcessor
{
    public const LAST_WEBHOOK_KEY = 'whatsapp.last_webhook_at';

    public const STOP_WORDS = ['STOP', 'UNSUBSCRIBE'];

    public const START_WORDS = ['START'];

    public function process(array $payload): void
    {
        Cache::forever(self::LAST_WEBHOOK_KEY, now()->toIso8601String());

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                foreach ($value['statuses'] ?? [] as $status) {
                    $this->status($status);
                }
                $names = collect($value['contacts'] ?? [])->mapWithKeys(fn ($c) => [($c['wa_id'] ?? '') => $c['profile']['name'] ?? null]);
                foreach ($value['messages'] ?? [] as $message) {
                    $this->inbound($message, $names[$message['from'] ?? ''] ?? null);
                }
                // Coexistence: messages the shop sent from the WhatsApp Business app on the phone.
                foreach ($value['message_echoes'] ?? [] as $echo) {
                    $this->echo($echo);
                }
            }
        }
    }

    private function status(array $s): void
    {
        $id = $s['id'] ?? null;
        $status = $s['status'] ?? null;
        if (! $id || ! in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
            return;
        }

        DB::transaction(function () use ($id, $status, $s) {
            $log = MessageLog::query()->where('wa_message_id', $id)->where('direction', 'out')->lockForUpdate()->first();
            if (! $log) {
                return; // a message sent from the WhatsApp Business app (coexistence) or another tool
            }

            $at = isset($s['timestamp']) ? Carbon::createFromTimestamp((int) $s['timestamp']) : now();
            $moved = $log->applyStatus($status, $at);

            if ($status === 'failed' && $moved) {
                $error = $s['errors'][0] ?? [];
                $code = (string) ($error['code'] ?? 'server');
                $log->error_code = $code;
                $log->error_text = ErrorMap::message($code, $error['error_data']['details'] ?? $error['title'] ?? null);
            }

            $log->save();
        });
    }

    private function inbound(array $m, ?string $profileName): void
    {
        $id = $m['id'] ?? null;
        $from = Phone::normalize($m['from'] ?? null);
        if (! $id || ! $from) {
            return;
        }

        $text = match ($m['type'] ?? 'text') {
            'text' => $m['text']['body'] ?? '',
            'button' => $m['button']['text'] ?? $m['button']['payload'] ?? '',
            'interactive' => $m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? '',
            default => '['.($m['type'] ?? 'message').']',
        };
        $keyword = $this->keyword($text);
        $customer = Customer::query()->where('mobile', $from)->first();

        $log = MessageLog::query()->firstOrCreate(
            ['wa_message_id' => $id, 'direction' => 'in'],
            [
                'branch_id' => $customer?->branch_id ?? Branch::query()->value('id'),
                'customer_id' => $customer?->id,
                'to' => $from,
                'channel' => 'cloud_api',
                'category' => 'service',
                'body' => array_filter([
                    'text' => mb_substr($text, 0, 4000),
                    'type' => $m['type'] ?? 'text',
                    'profile_name' => $profileName,
                    'keyword' => $keyword,
                    // Click-to-WhatsApp ad the customer came from (ad id, headline, click id).
                    'referral' => isset($m['referral']) ? array_filter(array_intersect_key($m['referral'], array_flip([
                        'source_id', 'source_type', 'source_url', 'headline', 'ctwa_clid',
                    ]))) : null,
                ], fn ($v) => $v !== null),
                'status' => 'delivered',
                'delivered_at' => isset($m['timestamp']) ? Carbon::createFromTimestamp((int) $m['timestamp']) : now(),
            ],
        );

        // Act on STOP/START once, even if Meta sends the same message again. We never reply.
        if ($log->wasRecentlyCreated && $keyword && $customer) {
            $customer->whatsapp_opt_in = $keyword === 'start';
            $customer->save();
        }

        if ($log->wasRecentlyCreated) {
            app(AiReplyAgent::class)->onInbound($log, $m['referral'] ?? null);
        }
    }

    /** A message sent from the WhatsApp Business app (coexistence echo): log it, and the AI steps back. */
    private function echo(array $m): void
    {
        $id = $m['id'] ?? null;
        $to = Phone::normalize($m['to'] ?? null);
        if (! $id || ! $to) {
            return;
        }

        $customer = Customer::query()->where('mobile', $to)->first();
        $at = isset($m['timestamp']) ? Carbon::createFromTimestamp((int) $m['timestamp']) : now();

        $log = MessageLog::query()->firstOrCreate(
            ['wa_message_id' => $id, 'direction' => 'out'],
            [
                'branch_id' => $customer?->branch_id ?? Branch::query()->value('id'),
                'customer_id' => $customer?->id,
                'to' => $to,
                'channel' => 'business_app',
                'category' => 'service',
                'body' => ['text' => mb_substr((string) ($m['text']['body'] ?? '['.($m['type'] ?? 'message').']'), 0, 4000), 'type' => $m['type'] ?? 'text'],
                'status' => 'sent',
                'sent_at' => $at,
            ],
        );

        if ($log->wasRecentlyCreated && $at->gt(now()->subHours(1))) {
            app(AiReplyAgent::class)->personReplied($to);
        }
    }

    private function keyword(string $text): ?string
    {
        $word = strtoupper(trim(preg_replace('/[^\pL\s]+/u', '', $text)));

        return match (true) {
            in_array($word, self::STOP_WORDS, true) => 'stop',
            in_array($word, self::START_WORDS, true) => 'start',
            default => null,
        };
    }
}
