<?php

namespace App\Support\Dashboard;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Low-stock numbers for the Home "Needs attention" block (owner/admin).
 * Low = a stocked product at or under its reorder level, including out of stock (≤ 0).
 */
final class LowStock
{
    public static function query(): Builder
    {
        return Product::query()->active()
            ->where('track_stock', true)
            ->where('type', Product::TYPE_PRODUCT)
            ->whereColumn('on_hand', '<=', 'reorder_level');
    }

    public static function count(): int
    {
        return self::query()->count();
    }

    /** Emptiest first, then the best sellers. @return Collection<int, Product> */
    public static function items(int $limit = 5): Collection
    {
        return self::query()->with('brand:id,name')
            ->orderBy('on_hand')->orderByDesc('sold_count')
            ->limit($limit)
            ->get(['id', 'name', 'brand_id', 'on_hand', 'reorder_level', 'sold_count', 'unit']);
    }

    public static function url(): string
    {
        return route('products.index', ['stock' => 'low']);
    }
}
