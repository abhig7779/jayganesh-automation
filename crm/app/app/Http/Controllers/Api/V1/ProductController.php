<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /** GET /api/v1/products?q=&limit= : ranked search (exact SKU first, then name start, then best sellers). */
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('inventory.view') || $request->user()->can('invoices.create'), 403);
        $q = trim((string) $request->query('q', ''));
        $limit = min(max($request->integer('limit', 20), 1), 100);

        $products = Product::query()->active()->with(['brand:id,name', 'category:id,name'])
            ->search($q)->rankedFor($q)
            ->limit($limit)->get();

        return ProductResource::collection($products);
    }

    /**
     * GET /api/v1/products/index : every active product in one compact payload, for an instant
     * client-side search on the bill screen. ETag lets the browser skip the download when nothing changed.
     */
    public function all(Request $request)
    {
        // Quotations (Sales) and the asked-price sheet (Marketing / leads) search the same list.
        $user = $request->user();
        abort_unless($user->can('inventory.view') || $user->can('invoices.create') || $user->can('quotations.manage') || $user->can('leads.manage'), 403);

        $stamp = Product::query()->max('updated_at').'|'.Product::query()->count();
        $etag = '"'.md5($stamp.'|'.(int) $request->user()->can('reports.margins')).'"';
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        $products = Product::query()->active()->with(['brand:id,name', 'category:id,name'])
            ->orderByDesc('sold_count')->orderBy('name')->get();

        return ProductResource::collection($products)->response()->header('ETag', $etag)->header('Cache-Control', 'private, no-cache');
    }
}
