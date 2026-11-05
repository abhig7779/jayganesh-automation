<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Money received (receipt, positive) or paid back (refund, negative). Never edited after saving. */
class Payment extends Model
{
    public const MODES = ['cash', 'upi', 'card', 'bank'];

    public const MODE_LABELS = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank'];

    public const KIND_RECEIPT = 'receipt';

    public const KIND_REFUND = 'refund';

    /**
     * Nothing is mass assignable (audit L16): money, totals and status are written only by the
     * billing services with forceCreate / forceFill, so a future update($request->validated())
     * can't set paid_paise or status.
     */
    protected $guarded = ['*'];

    protected $casts = ['amount_paise' => 'integer', 'reversed_at' => 'datetime'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function modeLabel(): string
    {
        return self::MODE_LABELS[$this->mode] ?? ucfirst($this->mode);
    }
}
