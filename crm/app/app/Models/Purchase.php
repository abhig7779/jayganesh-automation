<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** One supplier bill entered as stock in. Cancelled (stock reversed), never deleted. */
class Purchase extends Model
{
    use LogsActivity;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    public const CANCEL_REASONS = [
        'wrong_entry' => 'Wrong entry',
        'duplicate' => 'Entered twice',
        'returned' => 'Goods sent back',
        'other' => 'Other',
    ];

    protected $fillable = [
        'branch_id', 'supplier_id', 'bill_no', 'bill_date', 'photo_path', 'subtotal_paise', 'gst_paise',
        'total_paise', 'paid_paise', 'status', 'note', 'created_by',
    ];

    protected $attributes = ['status' => self::STATUS_RECEIVED, 'paid_paise' => 0];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'subtotal_paise' => 'integer',
            'gst_paise' => 'integer',
            'total_paise' => 'integer',
            'paid_paise' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNull('cancelled_at');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function duePaise(): int
    {
        return $this->isCancelled() ? 0 : max(0, $this->total_paise - $this->paid_paise);
    }

    /** paid / part / due / cancelled, for the status badge. */
    public function payState(): string
    {
        if ($this->isCancelled()) {
            return 'cancelled';
        }
        if ($this->paid_paise >= $this->total_paise) {
            return 'paid';
        }

        return $this->paid_paise > 0 ? 'part' : 'due';
    }

    /** Our own short reference, e.g. "PB-0012". */
    public function ref(): string
    {
        return 'PB-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function setBillDateAttribute(mixed $value): void
    {
        $this->attributes['bill_date'] = $value === null ? null : Carbon::parse($value)->toDateString();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['supplier_id', 'bill_no', 'bill_date', 'total_paise', 'gst_paise', 'status', 'cancel_reason'])
            ->logOnlyDirty()->useLogName('purchases');
    }
}
