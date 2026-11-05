<?php

namespace App\Models;

use App\Services\WhatsApp\ErrorMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A WhatsApp message, outgoing or incoming (table `message_log`).
 *
 * `body` holds: params (template body values), text (free text / inbound text), language,
 * document_path (private path of the PDF), document_pending (PDF still to be rendered),
 * media_id (Meta media id, reused on retries), keyword (stop/start for inbound), resend_of.
 */
class MessageLog extends Model
{
    protected $table = 'message_log';

    public const STATUSES = ['queued', 'sent', 'delivered', 'read', 'failed', 'manual_opened'];

    public const STATUS_LABELS = [
        'queued' => 'Sending',
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'read' => 'Read',
        'failed' => 'Not sent',
        'manual_opened' => 'Opened in WhatsApp',
    ];

    /**
     * Webhook statuses only ever move forward. Meta can deliver them out of order (read before
     * delivered), and a late "sent" must never undo "read". "failed" beats queued/sent only.
     */
    public const STATUS_RANK = [
        'queued' => 0,
        'manual_opened' => 1,
        'sent' => 1,
        'failed' => 2,
        'delivered' => 3,
        'read' => 4,
    ];

    protected $fillable = [
        'branch_id', 'customer_id', 'to', 'direction', 'channel', 'template', 'category', 'body',
        'document_name', 'wa_message_id', 'status', 'error_code', 'error_text', 'attempts',
        'related_type', 'related_id', 'sent_at', 'delivered_at', 'read_at', 'failed_at', 'created_by',
    ];

    protected $attributes = [
        'direction' => 'out',
        'channel' => 'manual',
        'category' => 'utility',
        'status' => 'queued',
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOutgoing(Builder $q): void
    {
        $q->where('direction', 'out');
    }

    public function isOutgoing(): bool
    {
        return $this->direction === 'out';
    }

    public function isManual(): bool
    {
        return $this->channel === 'manual';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /** Sent, delivered or read: nothing left for the queue to do. */
    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read'], true);
    }

    public function params(): array
    {
        return array_values($this->body['params'] ?? []);
    }

    public function bodyValue(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function mergeBody(array $values): static
    {
        $this->body = array_merge($this->body ?? [], $values);

        return $this;
    }

    /** The message as the customer reads it: the template text with its values filled in. */
    public function previewText(): string
    {
        if (! empty($this->body['text'])) {
            return (string) $this->body['text'];
        }
        $params = $this->params();
        $text = config('whatsapp.texts.'.$this->template);
        if (! $text) {
            return trim(($this->template ?? 'Message').': '.implode(' · ', $params));
        }

        return preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => $params[(int) $m[1] - 1] ?? '', $text);
    }

    /** The plain-English reason staff see, never a raw Meta message. */
    public function reason(): ?string
    {
        if ($this->error_text) {
            return $this->error_text;
        }

        return $this->error_code ? ErrorMap::message($this->error_code) : null;
    }

    public function statusLabel(): string
    {
        if ($this->status === 'queued' && $this->isManual()) {
            return 'Ready to send';
        }

        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Move to a webhook status if it is newer than the current one, and fill its timestamp even
     * when the status itself does not change (a late "delivered" after "read" still records when).
     */
    public function applyStatus(string $status, \DateTimeInterface $at): bool
    {
        $column = match ($status) {
            'sent' => 'sent_at',
            'delivered' => 'delivered_at',
            'read' => 'read_at',
            'failed' => 'failed_at',
            default => null,
        };
        if ($column === null) {
            return false;
        }

        if ($this->{$column} === null && ! ($status === 'failed' && $this->rank() > self::STATUS_RANK['failed'])) {
            $this->{$column} = $at;
        }
        $forward = (self::STATUS_RANK[$status] ?? 0) > $this->rank();
        if ($forward) {
            $this->status = $status;
            if ($status !== 'failed') {
                $this->error_code = null;
                $this->error_text = null;
            }
        }

        return $forward;
    }

    public function rank(): int
    {
        return self::STATUS_RANK[$this->status] ?? 0;
    }
}
