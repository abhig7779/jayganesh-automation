<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Customer asks how much?" One box, live results, big price and stock. */
class StockCheckController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $searching = mb_strlen($q) >= 2;

        $products = Product::query()->active()
            ->with('brand:id,name')
            ->when($searching, fn ($x) => $x->search($q)->rankedFor($q), fn ($x) => $x->orderByDesc('sold_count')->orderBy('name'))
            ->limit($searching ? 30 : 12)
            ->get(['id', 'name', 'sku', 'brand_id', 'type', 'price_paise', 'mrp_paise', 'on_hand', 'reorder_level', 'track_stock', 'shelf', 'unit']);

        $data = ['products' => $products, 'q' => $q, 'searching' => $searching];

        if ($request->header('HX-Request') && $request->header('HX-Target') === 'stock-results') {
            return view('stock.partials.results', $data);
        }

        return view('stock.check', $data);
    }
}
