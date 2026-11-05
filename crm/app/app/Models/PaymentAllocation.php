<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How much of a payment went to which bill (one payment can clear several old dues). */
class PaymentAllocation extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount_paise' => 'integer'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
