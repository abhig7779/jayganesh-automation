<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id', 'product_id', 'name', 'qty', 'unit_cost_paise', 'gst_rate', 'taxable_paise', 'gst_paise', 'total_paise',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_cost_paise' => 'integer',
            'gst_rate' => 'decimal:2',
            'taxable_paise' => 'integer',
            'gst_paise' => 'integer',
            'total_paise' => 'integer',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
