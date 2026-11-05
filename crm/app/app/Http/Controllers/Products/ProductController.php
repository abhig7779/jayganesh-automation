<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Stock\StockService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public const SORTS = ['popular', 'name', 'price', 'stock', 'brand', 'cost', 'margin'];

    public function __construct(private StockService $stock) {}

    public function index(Request $request): View
    {
        $canMargins = $request->user()->can('reports.margins');
        $filters = $this->filters($request, $canMargins);

        $query = Product::query()->with(['brand:id,name', 'category:id,name']);
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters);

        $products = $query->paginate(50)->withQueryString();
        $active = Product::query()->active();

        $data = [
            'products' => $products,
            'filters' => $filters,
            'canMargins' => $canMargins,
            'canManage' => $request->user()->can('inventory.manage'),
            'totalActive' => (clone $active)->count(),
            // Stock value at cost: only for people who may see costs.
            'inventoryValue' => $canMargins
                ? (int) (clone $active)->where('track_stock', true)->where('on_hand', '>', 0)->whereNotNull('cost_paise')->sum(DB::raw('on_hand * cost_paise'))
                : null,
        ];

        if ($request->header('HX-Request') && $request->header('HX-Target') === 'product-results') {
            return view('products.partials.results', $data);
        }

        // On the PC the first item's detail is open in the right panel straight away.
        $first = $products->first();

        return view('products.index', $data + [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'counts' => [
                'all' => $data['totalActive'],
                'low' => (clone $active)->where('track_stock', true)->where('on_hand', '>', 0)->whereColumn('on_hand', '<=', 'reorder_level')->count(),
                'out' => (clone $active)->where('track_stock', true)->where('on_hand', '<=', 0)->count(),
            ],
            'detail' => $first ? $this->detailData($request, $first) : null,
        ]);
    }

    public function show(Request $request, Product $product): View
    {
        $data = $this->detailData($request, $product);

        if ($request->header('HX-Request')) {
            return view('products.partials.detail', $data + ['inPanel' => true]);
        }

        return view('products.show', $data);
    }

    /** Everything the detail panel / page needs. */
    private function detailData(Request $request, Product $product): array
    {
        $product->loadMissing(['brand:id,name', 'category:id,name']);
        $moves = $product->stockMoves()->with('creator:id,name')->latest('id')->limit(8)->get();

        return [
            'product' => $product,
            'moves' => $moves,
            'canMargins' => $request->user()->can('reports.margins'),
            'canManage' => $request->user()->can('inventory.manage'),
            'billUrl' => Route::has('bills.create') && $request->user()->can('invoices.create') ? route('bills.create', ['product' => $product->id]) : null,
            'whatsappUrl' => 'https://wa.me/?text='.rawurlencode($this->priceMessage($product)),
            'reasons' => StockService::ADJUST_REASONS,
        ];
    }

    public function create(Request $request): View
    {
        $product = new Product([
            'gst_rate' => Setting::get('tax.default_gst_rate', 18),
            'type' => $request->query('type') === 'service' ? Product::TYPE_SERVICE : Product::TYPE_PRODUCT,
        ]);

        return view('products.form', $this->formData($request, $product));
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $user = $request->user();
        $attrs = $this->attributes($request, null);

        $product = DB::transaction(function () use ($attrs, $request, $user) {
            $product = Product::create($attrs + ['created_by' => $user->id]);
            $opening = (int) $request->input('opening_stock', 0);
            if ($opening > 0 && $product->track_stock) {
                $this->stock->move($product, $opening, 'opening', null, $user, $product->cost_paise, 'Opening stock');
            }

            return $product;
        });

        return redirect()->route('products.show', $product)->with('status', "Saved {$product->name}.");
    }

    public function edit(Request $request, Product $product): View
    {
        return view('products.form', $this->formData($request, $product->load(['brand', 'category'])));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($this->attributes($request, $product));

        return redirect()->route('products.show', $product)->with('status', "Saved {$product->name}.");
    }

    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'new_count' => ['required', 'integer', 'min:-100000', 'max:100000'],
            'reason' => ['required', Rule::in(array_keys(StockService::ADJUST_REASONS))],
            'note' => [Rule::requiredIf($request->input('reason') === 'other'), 'nullable', 'string', 'max:150'],
        ], [
            'new_count.required' => 'Type the number you counted.',
            'note.required' => 'Write what happened.',
        ]);

        if ($product->isService() || ! $product->track_stock) {
            return back()->withErrors(['new_count' => 'Services have no stock.']);
        }

        $reason = StockService::ADJUST_REASONS[$data['reason']].(! empty($data['note']) ? ': '.$data['note'] : '');
        $move = $this->stock->adjust($product, (int) $data['new_count'], $reason, $request->user());

        return redirect()->route('products.show', $product)->with('status', $move
            ? "Stock set to {$product->on_hand} ({$this->signed($move->qty)})."
            : 'Stock was already '.$product->on_hand.'. Nothing changed.');
    }

    /** @return array<string, mixed> */
    private function filters(Request $request, bool $canMargins): array
    {
        $sorts = $canMargins ? self::SORTS : array_diff(self::SORTS, ['cost', 'margin']);
        $sort = in_array($request->query('sort'), $sorts, true) ? $request->query('sort') : 'popular';
        $defaultDir = in_array($sort, ['name', 'brand'], true) ? 'asc' : 'desc';

        // "show" is the chip row on the list: All / Low stock / Out of stock / Services.
        $show = in_array($request->query('show'), ['low', 'out', 'services'], true) ? $request->query('show') : null;
        $stock = in_array($request->query('stock'), ['low', 'out', 'in'], true) ? $request->query('stock') : null;
        $type = in_array($request->query('type'), ['product', 'service'], true) ? $request->query('type') : null;

        return [
            'q' => trim((string) $request->query('q', '')),
            'show' => $show,
            'category' => $request->integer('category') ?: null,
            'brand' => $request->integer('brand') ?: null,
            'stock' => in_array($show, ['low', 'out'], true) ? $show : $stock,
            'type' => $show === 'services' ? 'service' : $type,
            'hidden' => $request->boolean('hidden'),
            'sort' => $sort,
            'dir' => in_array($request->query('dir'), ['asc', 'desc'], true) ? $request->query('dir') : $defaultDir,
        ];
    }

    private function applyFilters(Builder $q, array $f): void
    {
        $q->where('is_active', ! $f['hidden'])
            ->search($f['q'])
            ->when($f['category'], fn ($q, $id) => $q->where('category_id', $id))
            ->when($f['brand'], fn ($q, $id) => $q->where('brand_id', $id))
            ->when($f['type'], fn ($q, $t) => $q->where('type', $t))
            ->when($f['stock'] === 'low', fn ($q) => $q->where('track_stock', true)->where('on_hand', '>', 0)->whereColumn('on_hand', '<=', 'reorder_level'))
            ->when($f['stock'] === 'out', fn ($q) => $q->where('track_stock', true)->where('on_hand', '<=', 0))
            ->when($f['stock'] === 'in', fn ($q) => $q->where('track_stock', true)->where('on_hand', '>', 0));
    }

    private function applySort(Builder $q, array $f): void
    {
        $dir = $f['dir'];
        // A typed search ranks by best match unless the user picked a column.
        if ($f['q'] !== '' && $f['sort'] === 'popular') {
            $q->rankedFor($f['q']);

            return;
        }
        match ($f['sort']) {
            'name' => $q->orderBy('name', $dir),
            'price' => $q->orderBy('price_paise', $dir),
            'stock' => $q->orderBy('track_stock', 'desc')->orderBy('on_hand', $dir),
            'brand' => $q->orderBy(Brand::select('name')->whereColumn('brands.id', 'products.brand_id'), $dir),
            'cost' => $q->orderBy('cost_paise', $dir),
            'margin' => $q->orderByRaw('(price_paise - COALESCE(cost_paise, price_paise)) '.$dir),
            default => $q->orderBy('sold_count', $dir),
        };
        $q->orderBy('name');
    }

    private function formData(Request $request, Product $product): array
    {
        return [
            'product' => $product,
            'brands' => Brand::query()->orderBy('name')->pluck('name'),
            'categories' => Category::query()->orderBy('name')->pluck('name'),
            'canPrice' => ! $product->exists || $request->user()->can('prices.manage'),
            'canCost' => $request->user()->can('reports.margins') && $request->user()->can('prices.manage'),
        ];
    }

    /** Form input → model attributes, dropping what this user may not change. */
    private function attributes(ProductRequest $request, ?Product $product): array
    {
        $user = $request->user();
        $v = $request->validated();
        $list = fn (?string $s) => array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\n]+/', (string) $s)), fn ($x) => $x !== '')));

        $attrs = [
            'name' => trim($v['name']),
            'aliases' => ($a = $list(mb_strtolower($v['aliases'] ?? ''))) ? implode(', ', $a) : null,
            'sku' => $v['sku'] ?? null,
            'brand_id' => $this->brandId($v['brand_name'] ?? null),
            'category_id' => $this->categoryId($v['category_name'] ?? null),
            'type' => $v['type'],
            'gst_rate' => $v['gst_rate'],
            'hsn' => $v['hsn'] ?? null,
            'unit' => ($v['unit'] ?? null) ?: 'pcs',
            'reorder_level' => (int) ($v['reorder_level'] ?? 0),
            'shelf' => $v['shelf'] ?? null,
            'warranty' => $v['warranty'] ?? null,
            'compatible_cars' => $list($v['compatible_cars'] ?? '') ?: null,
            'notes' => $v['notes'] ?? null,
            'is_active' => $product ? $request->boolean('is_active') : true,
        ];

        // A new product needs a price, so whoever may add products sets the first one.
        // After that, only prices.manage changes prices; cost also needs reports.margins.
        if (! $product || $user->can('prices.manage')) {
            if ($request->filled('price')) {
                $attrs['price_paise'] = Money::toPaise($v['price']);
            }
            $attrs['mrp_paise'] = $request->filled('mrp') ? Money::toPaise($v['mrp']) : null;
            $attrs['price_includes_gst'] = $request->boolean('price_includes_gst', true);
        }
        if ($user->can('prices.manage') && $user->can('reports.margins') && $request->has('cost')) {
            $attrs['cost_paise'] = $request->filled('cost') ? Money::toPaise($v['cost']) : null;
        }

        return $attrs;
    }

    private function brandId(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return Brand::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id')
            ?? Brand::create(['name' => $name])->id;
    }

    private function categoryId(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return Category::query()->whereNull('parent_id')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id')
            ?? Category::create(['name' => $name])->id;
    }

    private function priceMessage(Product $p): string
    {
        $lines = [$p->name.($p->brand ? ' ('.$p->brand->name.')' : '').': '.Money::format($p->price_paise).($p->price_includes_gst ? ' incl. GST' : ' + GST')];
        if ($p->mrp_paise && $p->mrp_paise > $p->price_paise) {
            $lines[0] .= ' (MRP '.Money::format($p->mrp_paise).')';
        }
        if ($p->warranty) {
            $lines[] = 'Warranty: '.$p->warranty;
        }
        if ($p->track_stock) {
            $lines[] = $p->on_hand > 0 ? 'In stock now.' : 'We can order it for you.';
        }
        $lines[] = Setting::get('shop.name', 'Jay Ganesh Car Accessories').', Kawadipat Toll Naka, Loni Kalbhor. Call '.Setting::get('shop.phone', '+91 99813 97676').'.';

        return implode("\n", $lines);
    }

    private function signed(int $n): string
    {
        return ($n > 0 ? '+' : '').$n;
    }
}
