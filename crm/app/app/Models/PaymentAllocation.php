<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How much of a payment went to which bill (one payment can clear several old dues). */
class PaymentAllocation extends Model
{
    /**
     * Nothing is mass assignable (audit L16): money, totals and status are written only by the
     * billing services with forceCreate / forceFill, so a future update($request->validated())
     * can't set paid_paise or status.
     */
    protected $guarded = ['*'];

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
