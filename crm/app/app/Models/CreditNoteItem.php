<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    /**
     * Nothing is mass assignable (audit L16): money, totals and status are written only by the
     * billing services with forceCreate / forceFill, so a future update($request->validated())
     * can't set paid_paise or status.
     */
    protected $guarded = ['*'];

    protected $casts = [
        'qty' => 'integer', 'taxable_paise' => 'integer', 'cgst_paise' => 'integer',
        'sgst_paise' => 'integer', 'igst_paise' => 'integer', 'line_total_paise' => 'integer',
    ];

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
