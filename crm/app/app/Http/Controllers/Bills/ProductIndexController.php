<?php

namespace App\Http\Controllers\Bills;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * GET /bills/product-index: every active product as compact JSON for the instant search box
 * (resources/js/product-search.js). The browser caches it and revalidates with ETag /
 * Last-Modified, so a repeat load is a 304 with no body. Cost price is never included.
 *
 * Keys: i id, n name, s sku, b brand, t type (p product / s service), p price_paise,
 * m mrp_paise, g gst_rate, x price_includes_gst (1/0), h on_hand, k track_stock (1/0),
 * c sold_count, u unit, a aliases (when the products module adds that column).
 */
class ProductIndexController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        $stats = Product::query()->selectRaw('MAX(updated_at) as m, COUNT(*) as n, SUM(on_hand) as h, SUM(sold_count) as c')->first();
        $lastModified = $stats->m ? Carbon::parse($stats->m) : Carbon::create(2026);
        // on_hand and sold_count change through query-builder increments that don't touch
        // updated_at, so they are part of the ETag too.
        $etag = '"'.md5($stats->m.'|'.$stats->n.'|'.$stats->h.'|'.$stats->c).'"';

        $notModified = in_array($etag, array_map('trim', explode(',', (string) $request->header('If-None-Match'))), true);
        if ($notModified) {
            return response('', 304)->setEtag(trim($etag, '"'))->setLastModified($lastModified)
                ->header('Cache-Control', 'private, no-cache');
        }

        $hasAliases = Schema::hasColumn('products', 'aliases');
        $rows = Product::query()->active()
            ->with('brand:id,name')
            ->orderByDesc('sold_count')->orderBy('name')
            ->get()
            ->map(function (Product $p) use ($hasAliases) {
                $row = [
                    'i' => $p->id,
                    'n' => $p->name,
                    's' => $p->sku,
                    'b' => $p->brand?->name,
                    't' => $p->isService() ? 's' : 'p',
                    'p' => $p->price_paise,
                    'm' => $p->mrp_paise,
                    'g' => (float) $p->gst_rate,
                    'x' => $p->price_includes_gst ? 1 : 0,
                    'h' => $p->on_hand,
                    'k' => $p->track_stock ? 1 : 0,
                    'c' => $p->sold_count,
                    'u' => $p->unit,
                ];
                if ($hasAliases) {
                    $aliases = $p->getAttribute('aliases');
                    $row['a'] = is_array($aliases) ? implode(' ', $aliases)
                        : (is_string($aliases) && str_starts_with(trim($aliases), '[') ? implode(' ', (array) json_decode($aliases, true)) : $aliases);
                }

                return $row;
            });

        return response()->json(['v' => 1, 'products' => $rows])
            ->setEtag(trim($etag, '"'))
            ->setLastModified($lastModified)
            ->header('Cache-Control', 'private, no-cache');
    }
}
