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

    protected $guarded = ['id'];

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
