<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** An offer / campaign sent to a customer segment (UX-FLOWS.md §3.9). */
class Offer extends Model
{
    use LogsActivity;

    public const STATUSES = ['draft', 'sending', 'sent', 'exported'];

    public const METHODS = [
        'broadcast' => 'Broadcast list (free)',
        'assisted' => 'One by one (free)',
        'automatic' => 'Automatic (paid)',
    ];

    protected $fillable = [
        'branch_id', 'title', 'message', 'filters', 'segment_id', 'send_method', 'template_name',
        'scheduled_at', 'status', 'recipient_count', 'sent_count', 'skipped_count', 'failed_count',
        'opted_out_excluded', 'estimated_cost_paise', 'exported_at', 'completed_at', 'created_by',
    ];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'scheduled_at' => 'datetime',
            'exported_at' => 'datetime',
            'completed_at' => 'datetime',
            'recipient_count' => 'integer',
            'sent_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'opted_out_excluded' => 'integer',
            'estimated_cost_paise' => 'integer',
        ];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(OfferRecipient::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->send_method] ?? 'Not chosen yet';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'sending' => $this->scheduled_at?->isFuture() ? 'Scheduled' : 'Sending',
            'sent' => 'Sent',
            'exported' => 'Exported',
            default => ucfirst((string) $this->status),
        };
    }

    /** Badge classes (DESIGN.md §5: ok / warn tints, ink outline for neutral). */
    public function statusClasses(): string
    {
        return match ($this->status) {
            'sent' => 'border border-green-200 bg-green-50 text-green-700',
            'exported' => 'border border-zinc-400 bg-white text-ink',
            default => 'border border-amber-200 bg-amber-50 text-amber-700',
        };
    }

    /** Recount recipient statuses into the cached counters, and close the offer when nothing is left. */
    public function refreshCounts(): void
    {
        $by = $this->recipients()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $this->recipient_count = (int) $by->sum();
        $this->sent_count = (int) ($by['sent'] ?? 0);
        $this->skipped_count = (int) ($by['skipped'] ?? 0);
        $this->failed_count = (int) ($by['failed'] ?? 0);

        $open = (int) ($by['pending'] ?? 0) + (int) ($by['queued'] ?? 0);
        if ($this->status === 'sending' && $open === 0 && $this->recipient_count > 0) {
            $this->status = 'sent';
            $this->completed_at = now();
        }
        $this->save();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'send_method', 'template_name', 'scheduled_at', 'recipient_count'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('offers');
    }
}
