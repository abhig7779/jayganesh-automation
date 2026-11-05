<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Product for the API and billing's client-side item search.
 * cost_paise and margins only for users with reports.margins (Sales never sees them).
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $margins = (bool) $request->user()?->can('reports.margins');

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'aliases' => $this->aliasList(),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand?->name),
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'type' => $this->type,
            'hsn' => $this->hsn,
            'gst_rate' => (float) $this->gst_rate,
            'price_includes_gst' => $this->price_includes_gst,
            'price_paise' => $this->price_paise,
            'price' => Money::format($this->price_paise),
            'mrp_paise' => $this->mrp_paise,
            'discount_paise' => $this->discountPaise(),
            'discount_percent' => $this->discountPercent(),
            'unit' => $this->unit,
            'track_stock' => $this->track_stock,
            'on_hand' => $this->on_hand,
            'reorder_level' => $this->reorder_level,
            'stock_state' => $this->stockState(),
            'shelf' => $this->shelf,
            'warranty' => $this->warranty,
            'compatible_cars' => $this->compatible_cars ?? [],
            'sold_count' => $this->sold_count,
            'is_active' => $this->is_active,
            $this->mergeWhen($margins, fn () => [
                'cost_paise' => $this->cost_paise,
                'margin_paise' => $this->cost_paise !== null ? $this->price_paise - $this->cost_paise : null,
            ]),
        ];
    }
}
