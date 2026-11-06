<?php

namespace App\Services\WhatsApp;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Per-customer state for the AI auto-reply (see AiReplyAgent). */
class AiChat extends Model
{
    protected $table = 'whatsapp_ai_chats';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ad_referral' => 'array',
            'ad_referral_at' => 'datetime',
            'paused_until' => 'datetime',
            'last_ai_reply_at' => 'datetime',
            'ai_replies_date' => 'date',
        ];
    }

    public static function for(string $contact): self
    {
        return self::query()->firstOrCreate(['contact' => $contact]);
    }

    public function isPaused(): bool
    {
        return $this->paused_until !== null && $this->paused_until->isFuture();
    }

    public function pause(string $reason, int $hours): void
    {
        $this->paused_until = now()->addHours($hours);
        $this->paused_reason = $reason;
        $this->save();
    }

    /** True when the customer's last ad click is inside Meta's 72-hour free window. */
    public function isAdChat(): bool
    {
        return $this->ad_referral_at !== null && $this->ad_referral_at->gt(now()->subHours(72));
    }

    public function repliesToday(): int
    {
        $today = Carbon::now('Asia/Kolkata')->toDateString();

        return $this->ai_replies_date?->toDateString() === $today ? (int) $this->ai_replies_today : 0;
    }

    public function countReply(): void
    {
        $today = Carbon::now('Asia/Kolkata')->toDateString();
        $this->ai_replies_today = $this->repliesToday() + 1;
        $this->ai_replies_date = $today;
        $this->last_ai_reply_at = now();
        $this->save();
    }
}
