@php
    use App\Support\Money;
    $sample = fn (int $col) => collect($headerChoices)->filter(fn ($r, $line) => $line > $headerLine)->map(fn ($r) => $r[$col] ?? '')->filter()->first();
    $importable = ($counts['create'] ?? 0) + ($counts['update'] ?? 0);
    $mappingQuery = ['header' => $headerLine, 'map' => array_map(fn ($c) => $c ?? '', $mapping), 'stock_is_count' => $stockIsCount ? 1 : null];
    $statusStyle = [
        'create' => ['New', 'border-green-200 bg-green-50 text-green-700'],
        'update' => ['Update', 'border-zinc-300 bg-white text-ink'],
        'same' => ['Same', 'border-zinc-200 bg-zinc-50 text-zinc-600'],
        'error' => ['Skip', 'border-red-200 bg-red-50 text-red-600'],
        'skip' => ['Skip', 'border-red-200 bg-red-50 text-red-600'],
    ];
    $fmt = fn ($p) => $p === null ? '' : Money::format((int) $p);
@endphp
<x-layouts.app title="Import price list">
    <x-slot:header>
        <div>
            <a href="{{ route('products.import') }}" class="text-sm font-semibold text-zinc-700 hover:text-ink">← Choose another file</a>
            <h1 class="mt-1 text-[22px] font-bold leading-7">Check the price list</h1>
            <p class="text-sm text-zinc-600">{{ $fileName }} · <span class="tabular-nums">{{ number_format($total) }}</span> rows</p>
        </div>
    </x-slot:header>

    {{-- Step 2: columns --}}
    <form method="GET" action="{{ route('products.import.map', $token) }}" class="rounded-xl border border-zinc-200 bg-white p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Step 2 of 3</p>
                <h2 class="mt-1 text-[17px] font-semibold">Which column is which?</h2>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <span class="text-zinc-600">Headings are in row</span>
                <select name="header" class="input min-h-9 w-auto py-0 pr-8 text-[13px]" onchange="this.form.submit()">
                    @foreach ($headerChoices as $line => $cells)
                        <option value="{{ $line }}" @selected($line === $headerLine)>{{ $line }}: {{ \Illuminate\Support\Str::limit(implode(' | ', array_filter($cells)), 50) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        @error('map') <p class="error">{{ $message }}</p> @enderror

        <div class="mt-4 grid gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($fields as $field => [$label])
                @if ($field === 'cost' && ! $canPrice) @continue @endif
                <div>
                    <label class="mb-1 block text-[13px] font-medium text-zinc-700" for="map-{{ $field }}">{{ $label }}@if ($field === 'name') <span class="text-red-600">*</span>@endif</label>
                    <select id="map-{{ $field }}" name="map[{{ $field }}]" onchange="this.form.submit()"
                            @class(['input min-h-10 py-0 pr-8 text-[13px]', 'border-ink' => $mapping[$field] !== null, 'text-zinc-500' => $mapping[$field] === null])>
                        <option value="">— not in file —</option>
                        @foreach ($headers as $col => $h)
                            <option value="{{ $col }}" @selected($mapping[$field] === $col)>{{ $h !== '' ? $h : 'Column '.($col + 1) }}@if ($s = $sample($col)) (e.g. {{ \Illuminate\Support\Str::limit($s, 18) }})@endif</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>

        <label class="mt-4 flex items-start gap-2 text-sm text-zinc-700">
            <input type="checkbox" name="stock_is_count" value="1" class="mt-0.5 size-4 rounded border-zinc-300" onchange="this.form.submit()" @checked($stockIsCount)>
            <span>The stock column is today's count: also correct the stock of items we already have. <span class="text-zinc-600">(Off: stock is only used for new items.)</span></span>
        </label>
        <noscript><button class="btn-ghost mt-3">Update preview</button></noscript>
    </form>

    {{-- Preview --}}
    <section class="mt-5">
        <div class="mb-2 flex flex-wrap items-center gap-2 text-[13px]">
            <span class="mr-1 text-xs font-semibold uppercase tracking-wider text-zinc-600">Show</span>
            @foreach ([null => ['All', $total], 'create' => ['New', $counts['create'] ?? 0], 'update' => ['Update', $counts['update'] ?? 0], 'same' => ['Same', $counts['same'] ?? 0], 'error' => ['Problems', ($counts['error'] ?? 0) + ($counts['skip'] ?? 0)]] as $key => [$label, $n])
                <a href="{{ route('products.import.map', array_filter($mappingQuery + ['token' => $token, 'show' => $key])) }}"
                   @class(['inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 font-medium', 'border-ink bg-ink text-white' => $filter === $key, 'border-zinc-300 bg-white text-zinc-700 hover:border-ink' => $filter !== $key, 'text-red-600' => $key === 'error' && $n > 0 && $filter !== $key])>
                    {{ $label }} <span class="tabular-nums">{{ number_format($n) }}</span>
                </a>
            @endforeach
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white">
            <table class="w-full min-w-[900px] border-separate border-spacing-0 text-[13px]">
                <thead>
                    <tr class="text-left [&>th]:sticky [&>th]:top-0 [&>th]:border-b [&>th]:border-zinc-200 [&>th]:bg-zinc-100 [&>th]:px-3 [&>th]:py-2 [&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-zinc-600">
                        <th class="w-14">Row</th><th class="w-20">Status</th><th>Name</th><th>Brand</th><th class="text-right">MRP</th><th class="text-right">Our price</th><th class="text-right">GST</th><th>HSN</th><th class="text-right">Stock</th><th>Code</th><th class="w-[24%]">Check</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php
                            [$sLabel, $sCls] = $statusStyle[$r['action']];
                            $d = $r['data'];
                            $bad = in_array($r['action'], ['error', 'skip'], true);
                        @endphp
                        <tr @class(['[&>td]:border-b [&>td]:border-zinc-200 [&>td]:px-3 [&>td]:py-2 align-top', 'bg-red-50/70' => $bad]) data-action="{{ $r['action'] }}">
                            <td class="tabular-nums text-zinc-600">{{ $r['line'] }}</td>
                            <td><span class="inline-flex rounded-full border px-2 text-[11px] font-semibold leading-5 {{ $sCls }}">{{ $sLabel }}</span></td>
                            <td class="font-medium text-ink">{{ $d['name'] ?: '—' }}@if (! empty($d['aliases']))<div class="text-xs font-normal text-zinc-600">also: {{ $d['aliases'] }}</div>@endif</td>
                            <td class="text-zinc-700">{{ $d['brand'] }}</td>
                            <td class="text-right tabular-nums text-zinc-600">{{ $fmt($d['mrp_paise'] ?? null) }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ $fmt($d['price_paise'] ?? null) }}</td>
                            <td class="text-right tabular-nums">{{ isset($d['gst_rate']) && $d['gst_rate'] !== null ? rtrim(rtrim(number_format($d['gst_rate'], 2), '0'), '.').'%' : '' }}</td>
                            <td class="tabular-nums text-zinc-700">{{ $d['hsn'] }}</td>
                            <td class="text-right tabular-nums">{{ $d['stock'] ?? '' }}</td>
                            <td class="text-zinc-700">{{ $d['sku'] }}</td>
                            <td>
                                @foreach ($r['errors'] as $e)<div class="font-medium text-red-700">{{ $e }}</div>@endforeach
                                @if ($r['action'] === 'update')
                                    <div class="text-zinc-700">Changes: {{ collect(array_keys($r['changes']))->map(fn ($k) => ['price_paise' => 'price', 'mrp_paise' => 'MRP', 'cost_paise' => 'cost', 'gst_rate' => 'GST', 'reorder_level' => 'reorder level'][$k] ?? $k)->implode(', ') }}</div>
                                @endif
                                @foreach ($r['notes'] as $n)<div class="text-zinc-600">{{ $n }}</div>@endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-3 py-8 text-center text-zinc-600">No rows to show here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->count() >= 500)
            <p class="mt-2 text-[13px] text-zinc-600">Showing the first 500 rows. All rows are imported.</p>
        @endif
    </section>

    {{-- Step 3: confirm --}}
    <form method="POST" action="{{ route('products.import.commit', $token) }}" x-data="{ busy: false }" @submit="busy = true"
          class="sticky bottom-16 z-20 mt-5 flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between lg:bottom-4">
        @csrf
        <input type="hidden" name="header" value="{{ $headerLine }}">
        @foreach ($mapping as $field => $col)
            <input type="hidden" name="map[{{ $field }}]" value="{{ $col }}">
        @endforeach
        @if ($stockIsCount) <input type="hidden" name="stock_is_count" value="1"> @endif
        <p class="text-sm text-zinc-700">
            <strong class="tabular-nums">{{ $counts['create'] ?? 0 }}</strong> new ·
            <strong class="tabular-nums">{{ $counts['update'] ?? 0 }}</strong> to update ·
            <span class="tabular-nums">{{ $counts['same'] ?? 0 }}</span> unchanged ·
            <span @class(['tabular-nums', 'font-semibold text-red-600' => ($counts['error'] ?? 0) + ($counts['skip'] ?? 0) > 0])>{{ ($counts['error'] ?? 0) + ($counts['skip'] ?? 0) }} skipped</span>
        </p>
        <button class="btn-accent min-h-12" @disabled($importable === 0 || $mapping['name'] === null) x-bind:disabled="busy || {{ $importable === 0 || $mapping['name'] === null ? 'true' : 'false' }}">
            <span x-show="!busy">Import {{ number_format($importable) }} {{ \Illuminate\Support\Str::plural('item', $importable) }}</span>
            <span x-show="busy" x-cloak>Importing…</span>
        </button>
    </form>
</x-layouts.app>
