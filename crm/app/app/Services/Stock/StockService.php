<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\StockMove;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of stock (crm/CONTRACTS.md). Every change is a row in the stock_moves ledger,
 * and products.on_hand is a cache of their sum, updated in the same transaction under a row lock.
 *
 * - Stock may go negative: a sale is never blocked because the count was wrong.
 * - Services (and products with track_stock off) never get moves: move() returns an unsaved
 *   StockMove so callers like billing can call it for every bill line without checking.
 */
class StockService
{
    public const ADJUST_REASONS = [
        'count' => 'Count correction',
        'damaged' => 'Damaged',
        'returned_to_supplier' => 'Returned to supplier',
        'other' => 'Other',
    ];

    public function move(
        Product $p,
        int $qty,
        string $type,
        ?Model $reference = null,
        ?User $by = null,
        ?int $unitCostPaise = null,
        ?string $note = null,
    ): StockMove {
        if (! in_array($type, StockMove::TYPES, true)) {
            throw new InvalidArgumentException("Unknown stock move type: {$type}");
        }

        $attrs = [
            'branch_id' => $by?->branch_id ?? $p->branch_id,
            'product_id' => $p->id,
            'qty' => $qty,
            'type' => $type,
            'unit_cost_paise' => $unitCostPaise,
            'note' => $note !== null ? mb_substr($note, 0, 200) : null,
            'created_by' => $by?->id,
        ];

        if (! $p->track_stock || $p->isService() || $qty === 0) {
            return new StockMove($attrs); // nothing to record
        }

        return DB::transaction(function () use ($p, $qty, $attrs, $reference, $by) {
            $locked = Product::query()->whereKey($p->id)->lockForUpdate()->firstOrFail();

            $move = new StockMove($attrs);
            if ($reference) {
                $move->reference()->associate($reference);
            }
            $move->save();

            // Atomic increment on the locked row, never "old value + qty" from a stale model.
            Product::query()->whereKey($p->id)->increment('on_hand', $qty);
            $p->on_hand = $locked->on_hand + $qty;
            $p->syncOriginalAttribute('on_hand');

            activity('stock')
                ->performedOn($p)
                ->causedBy($by)
                ->withProperties([
                    'type' => $attrs['type'],
                    'qty' => $qty,
                    'on_hand_after' => $p->on_hand,
                    'move_id' => $move->id,
                    'note' => $attrs['note'],
                ])
                ->log("Stock {$attrs['type']} ".($qty > 0 ? '+' : '').$qty);

            return $move;
        });
    }

    /**
     * Set the stock to a counted number. Writes one 'adjustment' move for the difference.
     * Returns null when the count already matches.
     */
    public function adjust(Product $p, int $newCount, string $reason, User $by): ?StockMove
    {
        if (! $p->track_stock || $p->isService()) {
            return null;
        }

        return DB::transaction(function () use ($p, $newCount, $reason, $by) {
            $current = (int) Product::query()->whereKey($p->id)->lockForUpdate()->value('on_hand');
            $diff = $newCount - $current;
            if ($diff === 0) {
                $p->on_hand = $current;
                $p->syncOriginalAttribute('on_hand');

                return null;
            }

            return $this->move($p, $diff, 'adjustment', null, $by, null, $reason);
        });
    }

    /** Rebuild on_hand from the ledger (repair tool). Returns the new count. */
    public function recalculate(Product $p): int
    {
        return DB::transaction(function () use ($p) {
            Product::query()->whereKey($p->id)->lockForUpdate()->first();
            $sum = (int) StockMove::query()->where('product_id', $p->id)->sum('qty');
            Product::query()->whereKey($p->id)->update(['on_hand' => $sum]);
            $p->on_hand = $sum;
            $p->syncOriginalAttribute('on_hand');

            return $sum;
        });
    }
}
