<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'qty' => 'integer', 'taxable_paise' => 'integer', 'cgst_paise' => 'integer',
        'sgst_paise' => 'integer', 'igst_paise' => 'integer', 'line_total_paise' => 'integer',
    ];

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
