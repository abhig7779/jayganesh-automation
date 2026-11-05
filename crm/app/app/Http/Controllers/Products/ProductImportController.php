<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Services\Products\PriceListImporter;
use App\Services\Products\SheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Price list import: 1 upload → 2 match columns + check rows (one page) → 3 summary.
 * The parsed file waits in storage/app/private/imports/{user}/{token}.json between steps.
 */
class ProductImportController extends Controller
{
    public const TEMPLATE_HEADERS = ['Name', 'Brand', 'Category', 'Selling price', 'MRP', 'GST %', 'HSN', 'Stock', 'SKU', 'Also called', 'Type', 'Unit', 'Shelf', 'Reorder level', 'Warranty'];

    public function __construct(private PriceListImporter $importer, private SheetReader $reader) {}

    public function create(): View
    {
        return view('products.import.upload', ['summary' => session('import_summary')]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ], [
            'file.required' => 'Choose the price list file first.',
            'file.max' => 'The file is bigger than 10 MB. Split it into smaller files.',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        try {
            $rows = $this->reader->read($file->getRealPath(), $ext);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
        if (count($rows) < 2) {
            return back()->withErrors(['file' => 'This file has no rows to import. It needs a header row and at least one item.']);
        }

        $token = Str::random(32);
        Storage::disk('local')->put($this->path($request, $token), json_encode([
            'name' => Str::limit($file->getClientOriginalName(), 120),
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE));

        return redirect()->route('products.import.map', $token);
    }

    public function map(Request $request, string $token): View
    {
        $file = $this->load($request, $token);
        [$headerLine, $headers, $mapping, $stockIsCount] = $this->settings($request, $file['rows']);
        $preview = $this->importer->preview($file['rows'], $headerLine, $mapping, $request->user(), $stockIsCount);

        $counts = collect($preview)->countBy('action');
        $filter = in_array($request->query('show'), ['error', 'create', 'update', 'same'], true) ? $request->query('show') : null;

        return view('products.import.map', [
            'token' => $token,
            'fileName' => $file['name'],
            'headerLine' => $headerLine,
            'headerChoices' => array_slice($file['rows'], 0, 10, true),
            'headers' => $headers,
            'mapping' => $mapping,
            'stockIsCount' => $stockIsCount,
            'fields' => PriceListImporter::FIELDS,
            'rows' => collect($preview)->when($filter, fn ($c) => $c->where('action', $filter))->take(500)->values(),
            'total' => count($preview),
            'counts' => $counts,
            'filter' => $filter,
            'canPrice' => $request->user()->can('prices.manage'),
        ]);
    }

    public function commit(Request $request, string $token): RedirectResponse
    {
        $file = $this->load($request, $token);
        [$headerLine, , $mapping, $stockIsCount] = $this->settings($request, $file['rows']);

        if (($mapping['name'] ?? null) === null) {
            return back()->withErrors(['map' => 'Pick which column has the item name.']);
        }

        $preview = $this->importer->preview($file['rows'], $headerLine, $mapping, $request->user(), $stockIsCount);
        $summary = $this->importer->commit($preview, $request->user(), $stockIsCount);
        $summary['file'] = $file['name'];

        Storage::disk('local')->delete($this->path($request, $token));

        return redirect()->route('products.import')->with('import_summary', $summary);
    }

    public function template(): StreamedResponse
    {
        $rows = [
            self::TEMPLATE_HEADERS,
            ['DHC Gleam 2.0 LED headlight H4', 'DHC', 'LED & lights', '4999', '5999', '18', '85122010', '12', 'DHC-GLM2-H4', 'gleam, dhc led, h4 led, headlight bulb', 'product', 'pair', 'A3', '3', '1 year'],
            ['7D floor mats Hyundai Creta', '7D', 'Mats', '3200', '3800', '18', '57033090', '6', '', 'mats, 7d mat, floor mat, matting', 'product', 'set', 'C1', '2', '6 months'],
            ['Fitting charge', '', 'Services', '500', '', '18', '998729', '', 'SRV-FIT', 'fitting, labour, lagwai', 'service', '', '', '', ''],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel opens ₹ and Marathi text correctly
            foreach ($rows as $r) {
                fputcsv($out, $r, ',', '"', '');
            }
            fclose($out);
        }, 'jay-ganesh-price-list-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: int, 1: list<string>, 2: array<string, int|null>, 3: bool} */
    private function settings(Request $request, array $rows): array
    {
        $guess = $this->importer->guess($rows);
        $headerLine = $request->filled('header') && isset($rows[(int) $request->input('header')])
            ? (int) $request->input('header')
            : (int) $guess['header_line'];
        $headers = $rows[$headerLine] ?? [];

        $mapping = $headerLine === $guess['header_line'] ? $guess['mapping'] : $this->importer->guessMapping($headers);
        $chosen = $request->input('map');
        if (is_array($chosen)) {
            foreach (array_keys(PriceListImporter::FIELDS) as $field) {
                $col = $chosen[$field] ?? '';
                $mapping[$field] = $col === '' || ! isset($headers[(int) $col]) ? null : (int) $col;
            }
        }

        return [$headerLine, $headers, $mapping, $request->boolean('stock_is_count')];
    }

    private function load(Request $request, string $token): array
    {
        $path = $this->path($request, $token);
        abort_unless(Storage::disk('local')->exists($path), 404, 'This upload has expired. Please upload the file again.');

        $data = json_decode(Storage::disk('local')->get($path), true);
        // JSON turns the line-number keys into strings; make them ints again.
        $data['rows'] = collect($data['rows'])->mapWithKeys(fn ($r, $k) => [(int) $k => $r])->all();

        return $data;
    }

    private function path(Request $request, string $token): string
    {
        return 'imports/'.$request->user()->id.'/'.$token.'.json';
    }
}
