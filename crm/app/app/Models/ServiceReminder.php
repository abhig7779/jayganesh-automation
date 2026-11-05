<?php

namespace App\Models;

use App\Support\Quotations\ShopClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing to remind a customer about: a service check-up (from a rule) or a Google review
 * request. pending → due (daily command) → sent (WhatsApp opened or sent) → done; or skipped.
 * Cancelled when its bill is cancelled. Never deleted.
 */
class ServiceReminder extends Model
{
    public const TYPE_SERVICE = 'service';

    public const TYPE_REVIEW = 'review';

    public const PENDING = 'pending';

    public const DUE = 'due';

    public const SENT = 'sent';

    public const DONE = 'done';

    public const SKIPPED = 'skipped';

    public const CANCELLED = 'cancelled';

    /** Still on someone's list. */
    public const OPEN = [self::PENDING, self::DUE];

    public const STATUS_LABELS = [
        'pending' => 'Upcoming',
        'due' => 'Due',
        'sent' => 'Sent',
        'done' => 'Done',
        'skipped' => 'Skipped',
        'cancelled' => 'Cancelled',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'service_on' => 'date',
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
            'offset_days' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(CustomerCar::class, 'customer_car_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ReminderRule::class, 'reminder_rule_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isReview(): bool
    {
        return $this->type === self::TYPE_REVIEW;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** Open and due today or earlier (Pune date): what "Reminders today" lists. */
    public function scopeDueByToday(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN)->whereDate('due_on', '<=', ShopClock::today());
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN)->whereDate('due_on', '<', ShopClock::today());
    }

    /** "Ceramic coating check-up (6 months)" / "Google review request" */
    public function whatLabel(): string
    {
        if ($this->isReview()) {
            return 'Google review request';
        }
        $months = $this->offset_days ? (int) round($this->offset_days / 30.4) : null;
        $when = match (true) {
            ! $this->offset_days => '',
            $this->offset_days % 365 === 0 => ' ('.($this->offset_days / 365 === 1 ? '1 year' : ($this->offset_days / 365).' years').')',
            $months >= 1 && $this->offset_days >= 28 => ' ('.($months === 1 ? '1 month' : $months.' months').')',
            default => ' ('.$this->offset_days.' days)',
        };

        return ($this->rule?->name ?? 'Service check-up').$when;
    }

    public function carLabel(): string
    {
        $car = $this->car;
        if (! $car) {
            return '';
        }

        return trim($car->make.' '.$car->model);
    }
}
