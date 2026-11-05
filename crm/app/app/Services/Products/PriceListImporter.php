<?php

namespace App\Services\Products;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Billing\GstCalculator;
use App\Services\Stock\StockService;
use App\Support\Money;
use App\Support\SearchText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Price-list import: guess the header row and columns, check every row, then create or update
 * products. Matching is by SKU, or by name + brand when the SKU is empty, so importing the same
 * file twice changes nothing the second time. New products get an 'opening' stock move.
 */
class PriceListImporter
{
    /** field => [label, header words it is usually called] (first words win ties). */
    public const FIELDS = [
        'name' => ['Name', ['name', 'item', 'item name', 'product', 'product name', 'particulars', 'description', 'item description', 'product description']],
        'brand' => ['Brand', ['brand', 'make', 'company', 'manufacturer', 'mfr', 'brand name']],
        'category' => ['Category', ['category', 'group', 'item group', 'section', 'department']],
        'price' => ['Selling price', ['selling price', 'sale price', 'price', 'rate', 'sp', 'selling rate', 'sale rate', 'our price', 'offer price', 'retail price']],
        'mrp' => ['MRP', ['mrp', 'm r p', 'max retail price', 'list price']],
        'cost' => ['Cost price', ['cost', 'cost price', 'purchase price', 'purchase rate', 'cp', 'landing', 'landing price', 'dealer price', 'buying price']],
        'gst' => ['GST %', ['gst', 'gst rate', 'tax', 'tax rate', 'igst', 'gst percent', 'gst slab']],
        'hsn' => ['HSN / SAC', ['hsn', 'hsn code', 'sac', 'hsn sac', 'hsn sac code', 'hsn no']],
        'stock' => ['Stock', ['stock', 'qty', 'quantity', 'opening stock', 'on hand', 'closing stock', 'balance', 'stock qty', 'current stock', 'opening qty']],
        'sku' => ['SKU / code', ['sku', 'code', 'item code', 'product code', 'barcode', 'part no', 'part number', 'model no']],
        'aliases' => ['Also called', ['also called', 'aliases', 'alias', 'other names', 'keywords', 'tags', 'search words']],
        'type' => ['Type (product/service)', ['type', 'item type', 'product type', 'goods service']],
        'unit' => ['Unit', ['unit', 'uom', 'units']],
        'shelf' => ['Shelf', ['shelf', 'location', 'rack', 'bin', 'shelf no']],
        'reorder' => ['Reorder level', ['reorder', 'reorder level', 'min stock', 'minimum stock', 'min qty', 'alert qty']],
        'warranty' => ['Warranty', ['warranty', 'guarantee']],
    ];

    public const GST_RATES = GstCalculator::RATES;

    public function __construct(private StockService $stock) {}

    /**
     * Find the header row (the first of the first 10 rows that names at least two known columns)
     * and guess which column is which.
     *
     * @param  array<int, list<string>>  $rows  line number => cells
     * @return array{header_line: int|null, headers: list<string>, mapping: array<string, int|null>}
     */
    public function guess(array $rows): array
    {
        $best = null;
        foreach (array_slice($rows, 0, 10, true) as $line => $cells) {
            $mapping = $this->guessMapping($cells);
            $hits = count(array_filter($mapping, fn ($c) => $c !== null));
            if ($hits >= 2 && ($best === null || $hits > $best[2])) {
                $best = [$line, $cells, $hits, $mapping];
            }
        }

        if ($best === null) {
            // No header row: use the first row as headers, guess nothing but the obvious.
            $line = array_key_first($rows);
            $cells = $line !== null ? $rows[$line] : [];

            return ['header_line' => $line, 'headers' => $cells, 'mapping' => $this->guessMapping($cells)];
        }

        return ['header_line' => $best[0], 'headers' => $best[1], 'mapping' => $best[3]];
    }

    /** @return array<string, int|null> */
    public function guessMapping(array $headers): array
    {
        $norm = array_map(fn ($h) => SearchText::normalize((string) $h), $headers);
        $mapping = array_fill_keys(array_keys(self::FIELDS), null);
        $used = [];

        // Pass 1: exact names; pass 2: header contains the word (e.g. "Item Name (as on bill)").
        foreach ([true, false] as $exact) {
            foreach (self::FIELDS as $field => [, $synonyms]) {
                if ($mapping[$field] !== null) {
                    continue;
                }
                foreach ($synonyms as $syn) {
                    foreach ($norm as $col => $h) {
                        if ($h === '' || isset($used[$col])) {
                            continue;
                        }
                        $hit = $exact ? $h === $syn : (strlen($syn) > 2 && preg_match('/(^| )'.preg_quote($syn, '/').'( |$)/', $h));
                        if ($hit) {
                            $mapping[$field] = $col;
                            $used[$col] = true;

                            continue 3;
                        }
                    }
                }
            }
        }

        // Only an MRP column: sell at MRP.
        if ($mapping['price'] === null && $mapping['mrp'] !== null) {
            $mapping['price'] = $mapping['mrp'];
        }

        return $mapping;
    }

    /**
     * Check every data row against the mapping. Nothing is written.
     *
     * @param  array<int, list<string>>  $rows
     * @param  array<string, int|null>  $mapping
     * @return list<array{line: int, action: string, errors: list<string>, notes: list<string>, data: array, product_id: int|null, changes: array}>
     */
    public function preview(array $rows, int $headerLine, array $mapping, User $by, bool $stockIsCount = false): array
    {
        $canPrice = $by->can('prices.manage');
        $canCost = $canPrice && $by->can('reports.margins');
        $defaultGst = (float) (Setting::get('tax.default_gst_rate') ?? 18);
        $seen = [];
        $out = [];

        $existingBySku = Product::query()->whereNotNull('sku')->get(['id', 'sku'])
            ->mapWithKeys(fn ($p) => [mb_strtolower($p->sku) => $p->id]);
        $brandIds = Brand::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [mb_strtolower($n) => $id]);

        foreach ($rows as $line => $cells) {
            if ($line <= $headerLine) {
                continue;
            }
            $get = fn (string $f) => isset($mapping[$f]) && $mapping[$f] !== null && $mapping[$f] !== '' ? trim((string) ($cells[(int) $mapping[$f]] ?? '')) : '';
            $errors = [];
            $notes = [];

            $name = Str::limit($get('name'), 160, '');
            $brand = Str::limit($get('brand'), 80, '');
            $sku = $get('sku');

            $data = [
                'name' => $name,
                'brand' => $brand !== '' ? $brand : null,
                'category' => $get('category') !== '' ? Str::limit($get('category'), 80, '') : null,
                'sku' => $sku !== '' ? $sku : null,
                'aliases' => $this->cleanAliases($get('aliases')),
                'hsn' => null,
                'unit' => $get('unit') !== '' ? Str::limit(mb_strtolower($get('unit')), 10, '') : null,
                'shelf' => $get('shelf') !== '' ? Str::limit($get('shelf'), 40, '') : null,
                'warranty' => $get('warranty') !== '' ? Str::limit($get('warranty'), 80, '') : null,
            ];

            if ($name === '') {
                $out[] = ['line' => $line, 'action' => 'skip', 'errors' => ['No name'], 'notes' => [], 'data' => $data, 'product_id' => null, 'changes' => []];

                continue;
            }
            if ($sku !== '' && mb_strlen($sku) > 40) {
                $errors[] = 'SKU is longer than 40 characters';
            }

            // Money
            foreach (['price' => 'Price', 'mrp' => 'MRP', 'cost' => 'Cost'] as $f => $label) {
                $raw = $get($f);
                $data[$f.'_paise'] = null;
                if ($raw === '') {
                    continue;
                }
                $paise = $this->money($raw);
                if ($paise === null) {
                    $errors[] = "{$label} \"{$raw}\" is not an amount";
                } elseif ($paise < 0) {
                    $errors[] = "{$label} can't be negative";
                } else {
                    $data[$f.'_paise'] = $paise;
                }
            }

            if ($data['price_paise'] !== null && $data['mrp_paise'] && $data['price_paise'] > $data['mrp_paise']) {
                $errors[] = 'Selling price is more than MRP';
            }

            // GST
            $gstRaw = $get('gst');
            if ($gstRaw === '') {
                $data['gst_rate'] = null;
            } else {
                $gst = $this->gst($gstRaw);
                if ($gst === null) {
                    $errors[] = "GST \"{$gstRaw}\" should be 0, 5, 12, 18 or 28";
                }
                $data['gst_rate'] = $gst;
            }

            // HSN / SAC
            $hsn = preg_replace('/\D/', '', $get('hsn'));
            if ($get('hsn') !== '' && (strlen($hsn) < 4 || strlen($hsn) > 8)) {
                $errors[] = 'HSN "'.$get('hsn').'" should be 4 to 8 digits';
            }
            $data['hsn'] = $hsn !== '' ? $hsn : null;

            // Type
            $typeRaw = mb_strtolower($get('type'));
            $data['type'] = match (true) {
                $typeRaw !== '' && preg_match('/serv|labou?r|fitting|sac/', $typeRaw) === 1 => Product::TYPE_SERVICE,
                $typeRaw === '' && str_starts_with((string) $data['hsn'], '99') => Product::TYPE_SERVICE,
                default => Product::TYPE_PRODUCT,
            };

            // Whole numbers
            foreach (['stock' => 'Stock', 'reorder' => 'Reorder level'] as $f => $label) {
                $raw = $get($f);
                $data[$f] = null;
                if ($raw === '') {
                    continue;
                }
                if (! preg_match('/^\s*(-?\d+)(\.0+)?\s*[a-zA-Z.]*\s*$/', str_replace(',', '', $raw), $m)) {
                    $errors[] = "{$label} \"{$raw}\" is not a whole number";
                } else {
                    $data[$f] = (int) $m[1];
                }
            }

            // Match an existing product
            $productId = null;
            if ($data['sku'] !== null) {
                $productId = $existingBySku[mb_strtolower($data['sku'])] ?? null;
            }
            if ($productId === null) {
                $brandId = $data['brand'] !== null ? ($brandIds[mb_strtolower($data['brand'])] ?? false) : null;
                if ($brandId !== false) {
                    $productId = Product::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                        ->when($brandId === null, fn ($q) => $q->whereNull('brand_id'), fn ($q) => $q->where('brand_id', $brandId))
                        ->when($data['sku'] !== null, fn ($q) => $q->whereNull('sku'))
                        ->value('id');
                }
            }

            // Duplicates inside the file
            $key = $data['sku'] !== null ? 'sku:'.mb_strtolower($data['sku']) : 'nb:'.mb_strtolower($name).'|'.mb_strtolower((string) $data['brand']);
            if (isset($seen[$key])) {
                $errors[] = "Same item as row {$seen[$key]}";
            } else {
                $seen[$key] = $line;
            }

            if ($productId === null && $data['price_paise'] === null && $errors === []) {
                $errors[] = $get('price') === '' && count(array_filter($cells, fn ($c) => trim($c) !== '')) === 1
                    ? 'No price (looks like a heading row)'
                    : 'No selling price';
            }

            $changes = [];
            if ($errors === []) {
                if ($productId === null) {
                    $action = 'create';
                    if ($data['gst_rate'] === null) {
                        $data['gst_rate'] = $defaultGst;
                        $notes[] = 'GST '.rtrim(rtrim((string) $defaultGst, '0'), '.').'% (default)';
                    }
                    if (! $canCost) {
                        $data['cost_paise'] = null;
                    }
                    if ($data['type'] === Product::TYPE_SERVICE && $data['stock']) {
                        $notes[] = 'Service: stock ignored';
                    }
                } else {
                    $product = Product::with(['brand', 'category'])->find($productId);
                    $changes = $this->diff($product, $data, $canPrice, $canCost, $notes);
                    if ($stockIsCount && $data['stock'] !== null && $product->track_stock && $product->on_hand !== $data['stock']) {
                        $changes['stock'] = [$product->on_hand, $data['stock']];
                    }
                    $action = $changes === [] ? 'same' : 'update';
                }
            } else {
                $action = 'error';
            }

            $out[] = ['line' => $line, 'action' => $action, 'errors' => $errors, 'notes' => $notes, 'data' => $data, 'product_id' => $productId, 'changes' => $changes];
        }

        return $out;
    }

    /**
     * Write the checked rows. Rows with errors are skipped.
     *
     * @return array{created: int, updated: int, same: int, skipped: list<array{line: int, reason: string}>}
     */
    public function commit(array $previewRows, User $by, bool $stockIsCount = false): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'same' => 0, 'skipped' => []];

        DB::transaction(function () use ($previewRows, $by, $stockIsCount, &$summary) {
            foreach ($previewRows as $row) {
                if (in_array($row['action'], ['error', 'skip'], true)) {
                    $summary['skipped'][] = ['line' => $row['line'], 'reason' => implode('; ', $row['errors'])];

                    continue;
                }
                if ($row['action'] === 'same') {
                    $summary['same']++;

                    continue;
                }
                $d = $row['data'];

                try {
                    if ($row['action'] === 'create') {
                        $p = Product::create(array_filter([
                            'branch_id' => null,
                            'name' => $d['name'],
                            'sku' => $d['sku'],
                            'aliases' => $d['aliases'],
                            'brand_id' => $this->brandId($d['brand']),
                            'category_id' => $this->categoryId($d['category']),
                            'type' => $d['type'],
                            'hsn' => $d['hsn'],
                            'gst_rate' => $d['gst_rate'],
                            'price_paise' => $d['price_paise'],
                            'mrp_paise' => $d['mrp_paise'],
                            'cost_paise' => $d['cost_paise'],
                            'unit' => $d['unit'],
                            'shelf' => $d['shelf'],
                            'warranty' => $d['warranty'],
                            'reorder_level' => $d['reorder'],
                            'created_by' => $by->id,
                        ], fn ($v) => $v !== null));
                        if ($d['stock'] && $p->track_stock) {
                            $this->stock->move($p, $d['stock'], 'opening', null, $by, $d['cost_paise'], 'Price list import');
                        }
                        $summary['created']++;
                    } else {
                        $p = Product::findOrFail($row['product_id']);
                        $attrs = [];
                        foreach ($row['changes'] as $field => [, $new]) {
                            match ($field) {
                                'brand' => $attrs['brand_id'] = $this->brandId($new),
                                'category' => $attrs['category_id'] = $this->categoryId($new),
                                'stock' => null,
                                default => $attrs[$field] = $new,
                            };
                        }
                        if ($attrs) {
                            $p->update($attrs);
                        }
                        if ($stockIsCount && isset($row['changes']['stock'])) {
                            $this->stock->adjust($p, (int) $row['changes']['stock'][1], 'Count from price list import', $by);
                        }
                        $summary['updated']++;
                    }
                } catch (Throwable $e) {
                    report($e);
                    $summary['skipped'][] = ['line' => $row['line'], 'reason' => 'Could not save: '.Str::limit($e->getMessage(), 120)];
                }
            }
        });

        activity('products')->causedBy($by)->withProperties([
            'created' => $summary['created'], 'updated' => $summary['updated'], 'skipped' => count($summary['skipped']),
        ])->log('Price list imported');

        return $summary;
    }

    /** "₹1,499.00" / "Rs. 1499/-" / "1499" → paise; null if not money. */
    public function money(string $raw): ?int
    {
        $clean = preg_replace('/^(rs\.?|inr|₹)\s*/iu', '', trim($raw));
        $clean = preg_replace('/\s*\/-\s*$/', '', $clean);
        $clean = str_replace([',', ' ', '₹'], '', $clean);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $clean)) {
            return null;
        }
        // Round anything past 2 decimals (Excel float noise like 4999.0000001).
        if (preg_match('/^(-?\d+)\.(\d{3,})$/', $clean)) {
            $clean = number_format(round((float) $clean, 2), 2, '.', '');
        }

        return Money::toPaise($clean);
    }

    /** "18%", "18", "0.18", "GST 18" → 18.0; null when not an Indian GST slab. */
    public function gst(string $raw): ?float
    {
        $clean = trim(str_ireplace(['gst', '%', 'igst'], '', $raw));
        if (! is_numeric($clean)) {
            return null;
        }
        $rate = (float) $clean;
        if ($rate > 0 && $rate < 1) {
            $rate = round($rate * 100, 2);
        }

        return in_array($rate, array_map('floatval', self::GST_RATES), true) ? $rate : null;
    }

    private function cleanAliases(string $raw): ?string
    {
        $parts = array_filter(array_map('trim', preg_split('/[,;|\/]+/', $raw)), fn ($p) => $p !== '');
        $parts = array_values(array_unique(array_map(fn ($p) => mb_strtolower(Str::limit($p, 40, '')), $parts)));

        return $parts ? implode(', ', $parts) : null;
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    private function diff(Product $p, array $d, bool $canPrice, bool $canCost, array &$notes): array
    {
        $changes = [];
        $set = function (string $field, mixed $old, mixed $new) use (&$changes) {
            if ($new !== null && $new !== '' && $old != $new) {
                $changes[$field] = [$old, $new];
            }
        };

        $set('name', $p->name, $d['name'] !== $p->name && mb_strtolower($d['name']) === mb_strtolower($p->name) ? null : $d['name']);
        $set('brand', $p->brand?->name, $d['brand'] !== null && mb_strtolower($d['brand']) !== mb_strtolower((string) $p->brand?->name) ? $d['brand'] : null);
        $set('category', $p->category?->name, $d['category'] !== null && mb_strtolower($d['category']) !== mb_strtolower((string) $p->category?->name) ? $d['category'] : null);
        $set('sku', $p->sku, $d['sku']);
        $set('hsn', $p->hsn, $d['hsn']);
        $set('gst_rate', (float) $p->gst_rate, $d['gst_rate']);
        $set('unit', $p->unit, $d['unit']);
        $set('shelf', $p->shelf, $d['shelf']);
        $set('warranty', $p->warranty, $d['warranty']);
        $set('reorder_level', $p->reorder_level, $d['reorder']);
        if ($d['aliases'] !== null) {
            $merged = array_values(array_unique(array_merge($p->aliasList(), explode(', ', $d['aliases']))));
            $set('aliases', $p->aliases, implode(', ', $merged) !== (string) $p->aliases ? implode(', ', $merged) : null);
        }

        $priceChanges = [];
        foreach (['price_paise', 'mrp_paise'] as $f) {
            if ($d[$f] !== null && $d[$f] !== $p->{$f}) {
                $priceChanges[$f] = [$p->{$f}, $d[$f]];
            }
        }
        if ($priceChanges && ! $canPrice) {
            $notes[] = 'New price not applied (only the owner can change prices)';
        } else {
            $changes += $priceChanges;
        }
        if ($canCost && $d['cost_paise'] !== null && $d['cost_paise'] !== $p->cost_paise) {
            $changes['cost_paise'] = [$p->cost_paise, $d['cost_paise']];
        }

        return $changes;
    }

    private function brandId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }
        $existing = Brand::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->value('id');

        return $existing ?? Brand::create(['name' => trim($name)])->id;
    }

    private function categoryId(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }
        $existing = Category::query()->whereNull('parent_id')->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->value('id');

        return $existing ?? Category::create(['name' => trim($name)])->id;
    }
}
