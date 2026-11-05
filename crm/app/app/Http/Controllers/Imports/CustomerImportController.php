<?php

namespace App\Http\Controllers\Imports;

use App\Http\Controllers\Controller;
use App\Models\CustomerImport;
use App\Services\Imports\ContactsReader;
use App\Services\Imports\CustomerImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import customers: 1 upload (Excel / CSV / phone contacts) → 2 match columns + check rows →
 * 3 save in slices of 250 rows (one request each, so shared hosting's time limit is never hit)
 * → summary. The parsed file waits in storage/app/private/imports/customers/{user}/{token}.json.
 */
class CustomerImportController extends Controller
{
    public const TEMPLATE_HEADERS = ['Name', 'Mobile', 'Car', 'Number plate', 'Area', 'Notes'];

    public function __construct(private CustomerImporter $importer, private ContactsReader $reader) {}

    public function create(): View
    {
        return view('imports.customers.upload', [
            'recent' => CustomerImport::query()->with('user:id,name')->latest('id')->limit(5)->get(),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ], [
            'file.required' => 'Choose the customer list or contacts file first.',
            'file.max' => 'The file is bigger than 10 MB. Split it into smaller files.',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt', 'xlsx', 'ods', 'xls', 'vcf', 'vcard'], true)) {
            return back()->withErrors(['file' => 'Please upload an Excel (.xlsx), CSV or contacts (.vcf) file.']);
        }

        try {
            [$rows, $kind] = $this->reader->read($file->getRealPath(), $ext);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }
        if (count($rows) < 2) {
            return back()->withErrors(['file' => 'This file has no customers in it. It needs a header row and at least one person.']);
        }

        $token = Str::random(32);
        Storage::disk('local')->put($this->path($request->user()->id, $token), json_encode([
            'name' => Str::limit($file->getClientOriginalName(), 120),
            'kind' => $kind,
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE));

        return redirect()->route('customers.import.map', $token);
    }

    public function map(Request $request, string $token): View
    {
        $file = $this->load($request->user()->id, $token);
        [$headerLine, $headers, $mapping] = $this->settings($request, $file['rows']);
        $preview = $this->importer->preview($file['rows'], $headerLine, $mapping);

        $counts = collect($preview)->countBy('action');
        $filter = in_array($request->query('show'), ['error', 'create', 'update', 'same'], true) ? $request->query('show') : null;

        return view('imports.customers.map', [
            'token' => $token,
            'fileName' => $file['name'],
            'kind' => $file['kind'] ?? 'sheet',
            'headerLine' => $headerLine,
            'headerChoices' => array_slice($file['rows'], 0, 10, true),
            'headers' => $headers,
            'mapping' => $mapping,
            'fields' => CustomerImporter::FIELDS,
            'rows' => collect($preview)->when($filter, fn ($c) => $c->where('action', $filter))->take(500)->values(),
            'total' => count($preview),
            'counts' => $counts,
            'filter' => $filter,
        ]);
    }

    /** Check the rows once more with the chosen columns, save the plan, then start saving. */
    public function start(Request $request, string $token): RedirectResponse
    {
        $userId = $request->user()->id;
        $file = $this->load($userId, $token);
        [$headerLine, , $mapping] = $this->settings($request, $file['rows']);

        if (($mapping['mobile'] ?? null) === null) {
            return back()->withErrors(['map' => 'Pick which column has the mobile number.']);
        }
        if (($mapping['name'] ?? null) === null) {
            return back()->withErrors(['map' => 'Pick which column has the customer’s name.']);
        }

        $preview = $this->importer->preview($file['rows'], $headerLine, $mapping);

        $import = CustomerImport::query()->firstOrCreate(['token' => $token], [
            'user_id' => $userId,
            'file_name' => $file['name'],
            'status' => 'running',
            'total_rows' => count($preview),
        ]);
        if ((int) $import->processed === 0) {
            Storage::disk('local')->put($this->path($userId, $token), json_encode([
                'name' => $file['name'],
                'kind' => $file['kind'] ?? 'sheet',
                'plan' => $preview,
            ], JSON_UNESCAPED_UNICODE));
        }

        return redirect()->route('customers.import.show', $import);
    }

    /** Running: progress bar that continues by itself. Done: the summary. */
    public function show(CustomerImport $import): View
    {
        return view('imports.customers.show', ['import' => $import]);
    }

    /** Save the next slice of rows. The progress page posts here again until it's done. */
    public function continue(Request $request, CustomerImport $import): RedirectResponse
    {
        if ($import->isDone()) {
            return redirect()->route('customers.import.show', $import);
        }

        $lock = Cache::lock('customer-import-'.$import->id, 120);
        if (! $lock->get()) {
            return redirect()->route('customers.import.show', $import);
        }

        try {
            @set_time_limit(120);
            $import->refresh();
            $path = $this->path((int) $import->user_id, $import->token);
            abort_unless(Storage::disk('local')->exists($path), 404, 'This import’s file has expired. Please upload the file again.');
            $plan = json_decode(Storage::disk('local')->get($path), true)['plan'] ?? [];

            $this->importer->commitChunk($plan, $import->processed, $import, $request->user());

            if ($import->isDone()) {
                Storage::disk('local')->delete($path);
            }
        } finally {
            $lock->release();
        }

        return redirect()->route('customers.import.show', $import);
    }

    public function template(): StreamedResponse
    {
        $rows = [
            self::TEMPLATE_HEADERS,
            ['Rahul Pawar', '98231 07676', 'Hyundai Creta', 'MH 12 AB 1234', 'Hadapsar', 'PPF done Oct 2025'],
            ['Sneha Kulkarni', '+91 99813 97676', 'Tata Nexon', 'MH14 CD 5678', 'Wakad', ''],
            ['Amit Shinde', '09876543210', 'Swift', '', 'Kharadi', 'Asked about seat covers'],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel opens Marathi names correctly
            foreach ($rows as $r) {
                fputcsv($out, $r, ',', '"', '');
            }
            fclose($out);
        }, 'jay-ganesh-customers-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: int|null, 1: list<string>, 2: array<string, int|null>} */
    private function settings(Request $request, array $rows): array
    {
        $guess = $this->importer->guess($rows);
        $headerLine = $request->filled('header') && isset($rows[(int) $request->input('header')])
            ? (int) $request->input('header')
            : $guess['header_line'];
        $headers = $rows[$headerLine] ?? [];

        $mapping = $headerLine === $guess['header_line'] ? $guess['mapping'] : $this->importer->guessMapping($headers);
        $chosen = $request->input('map');
        if (is_array($chosen)) {
            foreach (array_keys(CustomerImporter::FIELDS) as $field) {
                $col = $chosen[$field] ?? '';
                $mapping[$field] = $col === '' || $col === null || ! isset($headers[(int) $col]) ? null : (int) $col;
            }
        }

        return [$headerLine, $headers, $mapping];
    }

    private function load(int $userId, string $token): array
    {
        $path = $this->path($userId, $token);
        abort_unless(Storage::disk('local')->exists($path), 404, 'This upload has expired. Please upload the file again.');

        $data = json_decode(Storage::disk('local')->get($path), true);
        abort_unless(isset($data['rows']), 404, 'This file was already imported. Upload it again to import once more.');
        // JSON turns the line-number keys into strings; make them ints again.
        $data['rows'] = collect($data['rows'])->mapWithKeys(fn ($r, $k) => [(int) $k => $r])->all();

        return $data;
    }

    private function path(int $userId, string $token): string
    {
        return 'imports/customers/'.$userId.'/'.$token.'.json';
    }
}
