<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\StockMove;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * TEMPORARY minimal StockService, added by the billing branch so bills can move stock before
 * the products module is merged. It has exactly the signature in crm/CONTRACTS.md. The
 * products branch's full StockService REPLACES this file at merge time; keep theirs.
 */
class StockService
{
    public function move(
        Product $p,
        int $qty,
        string $type,
        ?Model $reference = null,
        ?User $by = null,
        ?int $unitCostPaise = null,
        ?string $note = null,
    ): StockMove {
        return DB::transaction(function () use ($p, $qty, $type, $reference, $by, $unitCostPaise, $note) {
            $move = StockMove::create([
                'branch_id' => $reference?->getAttribute('branch_id') ?? $p->branch_id,
                'product_id' => $p->id,
                'qty' => $qty,
                'type' => $type,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'unit_cost_paise' => $unitCostPaise,
                'note' => $note,
                'created_by' => $by?->id ?? auth()->id(),
            ]);

            Product::query()->whereKey($p->id)->increment('on_hand', $qty);
            $p->on_hand = (int) Product::query()->whereKey($p->id)->value('on_hand');

            return $move;
        });
    }
}
