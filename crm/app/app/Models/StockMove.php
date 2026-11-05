<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Append-only ledger. Written only by App\Services\Stock\StockService. */
class StockMove extends Model
{
    public const TYPES = ['opening', 'purchase', 'sale', 'sale_cancel', 'return', 'adjustment'];

    public const LABELS = [
        'opening' => 'Opening stock', 'purchase' => 'Stock in', 'sale' => 'Sold', 'sale_cancel' => 'Bill cancelled',
        'return' => 'Returned', 'adjustment' => 'Adjusted',
    ];

    protected $fillable = ['branch_id', 'product_id', 'qty', 'type', 'reference_type', 'reference_id', 'unit_cost_paise', 'note', 'created_by'];

    protected $casts = ['qty' => 'integer', 'unit_cost_paise' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
