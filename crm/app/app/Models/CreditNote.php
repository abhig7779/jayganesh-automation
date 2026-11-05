<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A partial return against a bill (UX-FLOWS §3.7). */
class CreditNote extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'subtotal_paise' => 'integer', 'cgst_paise' => 'integer', 'sgst_paise' => 'integer',
        'igst_paise' => 'integer', 'total_paise' => 'integer', 'refund_paise' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
