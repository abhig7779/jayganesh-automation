<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id', 'product_id', 'name', 'hsn', 'qty', 'unit_price_paise', 'gst_rate',
        'taxable_paise', 'gst_paise', 'line_total_paise', 'position', 'mrp_paise',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price_paise' => 'integer',
            'mrp_paise' => 'integer',
            'gst_rate' => 'decimal:2',
            'taxable_paise' => 'integer',
            'gst_paise' => 'integer',
            'line_total_paise' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
