<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Append-only ledger. Written only by App\Services\Stock\StockService. */
class StockMove extends Model
{
    public const TYPES = ['opening', 'purchase', 'sale', 'sale_cancel', 'return', 'adjustment'];

    protected $fillable = ['branch_id', 'product_id', 'qty', 'type', 'reference_type', 'reference_id', 'unit_cost_paise', 'note', 'created_by'];

    protected $casts = ['qty' => 'integer', 'unit_cost_paise' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
