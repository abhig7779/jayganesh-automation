@php
    use App\Support\Phone;
    use App\Support\RegNo;
    use Illuminate\Support\Str;
    $sample = fn (int $col) => collect($headerChoices)->filter(fn ($r, $line) => $line > $headerLine)->map(fn ($r) => $r[$col] ?? '')->filter()->first();
    $importable = ($counts['create'] ?? 0) + ($counts['update'] ?? 0);
    $ready = $importable > 0 && $mapping['mobile'] !== null && $mapping['name'] !== null;
    $mappingQuery = ['header' => $headerLine, 'map' => array_map(fn ($c) => $c ?? '', $mapping)];
    $statusStyle = [
        'create' => ['New', 'border-green-200 bg-green-50 text-green-700'],
        'update' => ['Add details', 'border-zinc-300 bg-white text-ink'],
        'same' => ['Same', 'border-zinc-200 bg-zinc-50 text-zinc-600'],
        'error' => ['Skip', 'border-red-200 bg-red-50 text-red-600'],
    ];
    $kindLabel = ['vcf' => 'Phone contacts (.vcf)', 'google' => 'Google Contacts export', 'sheet' => null][$kind] ?? null;
@endphp
<x-layouts.app title="Import customers">
    <x-slot:header>
        <div>
            <a href="{{ route('customers.import') }}" class="text-sm font-semibold text-zinc-700 hover:text-ink">← Choose another file</a>
            <h1 class="mt-1 text-[22px] font-bold leading-7">Check the customer list</h1>
            <p class="text-sm text-zinc-600">{{ $fileName }}@if ($kindLabel) · {{ $kindLabel }}@endif · <span class="tabular-nums">{{ number_format($total) }}</span> rows</p>
        </div>
    </x-slot:header>

    {{-- Step 2: columns --}}
    <form method="GET" action="{{ route('customers.import.map', $token) }}" class="rounded-xl border border-zinc-200 bg-white p-5" x-data>
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Step 2 of 3</p>
                <h2 class="mt-1 text-[17px] font-semibold">Which column is which?</h2>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <span class="text-zinc-600">Headings are in row</span>
                <select name="header" class="input min-h-9 w-auto py-0 pr-8 text-[13px]" @change="$el.form.submit()">
                    @foreach ($headerChoices as $line => $cells)
                        <option value="{{ $line }}" @selected($line === $headerLine)>{{ $line }}: {{ Str::limit(implode(' | ', array_filter($cells)), 50) }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        @error('map') <p class="error">{{ $message }}</p> @enderror

        <div class="mt-4 grid gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($fields as $field => [$label])
                <div>
                    <label class="mb-1 block text-[13px] font-medium text-zinc-700" for="map-{{ $field }}">{{ $label }}@if (in_array($field, ['name', 'mobile'], true)) <span class="text-red-600">*</span>@endif</label>
                    <select id="map-{{ $field }}" name="map[{{ $field }}]" @change="$el.form.submit()"
                            @class(['input min-h-10 py-0 pr-8 text-[13px]', 'border-ink' => $mapping[$field] !== null, 'text-zinc-500' => $mapping[$field] === null])>
                        <option value="">— not in file —</option>
                        @foreach ($headers as $col => $h)
                            <option value="{{ $col }}" @selected($mapping[$field] === $col)>{{ $h !== '' ? $h : 'Column '.($col + 1) }}@if ($s = $sample($col)) (e.g. {{ Str::limit($s, 18) }})@endif</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
        <noscript><button class="btn-ghost mt-3">Update preview</button></noscript>
    </form>

    {{-- Preview --}}
    <section class="mt-5">
        <div class="mb-2 flex flex-wrap items-center gap-2 text-[13px]">
            <span class="mr-1 text-xs font-semibold uppercase tracking-wider text-zinc-600">Show</span>
            @foreach ([null => ['All', $total], 'create' => ['New', $counts['create'] ?? 0], 'update' => ['Add details', $counts['update'] ?? 0], 'same' => ['Same', $counts['same'] ?? 0], 'error' => ['Problems', $counts['error'] ?? 0]] as $key => [$label, $n])
                <a href="{{ route('customers.import.map', array_filter($mappingQuery + ['token' => $token, 'show' => $key])) }}"
                   @class(['inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 font-medium', 'border-ink bg-ink text-white' => $filter === $key, 'border-zinc-300 bg-white text-zinc-700 hover:border-ink' => $filter !== $key, 'text-red-600' => $key === 'error' && $n > 0 && $filter !== $key])>
                    {{ $label }} <span class="tabular-nums">{{ number_format($n) }}</span>
                </a>
            @endforeach
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white">
            <table class="w-full min-w-[900px] border-separate border-spacing-0 text-[13px]">
                <thead>
                    <tr class="text-left [&>th]:sticky [&>th]:top-0 [&>th]:border-b [&>th]:border-zinc-200 [&>th]:bg-zinc-100 [&>th]:px-3 [&>th]:py-2 [&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-zinc-600">
                        <th class="w-14">Row</th><th class="w-24">Status</th><th>Name</th><th>Mobile</th><th>Car</th><th>Area</th><th class="w-[30%]">Check</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php
                            [$sLabel, $sCls] = $statusStyle[$r['action']];
                            $d = $r['data'];
                            $bad = $r['action'] === 'error';
                        @endphp
                        <tr @class(['[&>td]:border-b [&>td]:border-zinc-200 [&>td]:px-3 [&>td]:py-2 align-top', 'bg-red-50/70' => $bad]) data-action="{{ $r['action'] }}">
                            <td class="tabular-nums text-zinc-600">{{ $r['line'] }}</td>
                            <td><span class="inline-flex whitespace-nowrap rounded-full border px-2 text-[11px] font-semibold leading-5 {{ $sCls }}">{{ $sLabel }}</span></td>
                            <td class="font-medium text-ink">{{ $d['name'] ?: '—' }}@if ($d['email'])<div class="text-xs font-normal text-zinc-600">{{ $d['email'] }}</div>@endif</td>
                            <td class="whitespace-nowrap tabular-nums">{{ $d['mobile'] ? Phone::pretty($d['mobile']) : '—' }}@if ($d['alt_mobile'])<div class="text-xs text-zinc-600">also {{ Phone::pretty($d['alt_mobile']) }}</div>@endif</td>
                            <td class="text-zinc-700">
                                @foreach ($d['cars'] as $c)
                                    <div>{{ trim($c['make'].' '.$c['model']) }}@if ($c['reg_no']) <span class="whitespace-nowrap font-medium text-ink">{{ RegNo::pretty($c['reg_no']) }}</span>@endif</div>
                                @endforeach
                            </td>
                            <td class="text-zinc-700">{{ $d['area'] }}</td>
                            <td>
                                @foreach ($r['errors'] as $e)<div class="font-medium text-red-700">{{ $e }}</div>@endforeach
                                @foreach ($r['notes'] as $n)<div class="text-zinc-600">{{ $n }}</div>@endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-zinc-600">No rows to show here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->count() >= 500)
            <p class="mt-2 text-[13px] text-zinc-600">Showing the first 500 rows. All rows are imported.</p>
        @endif
        <p class="mt-3 text-[13px] text-zinc-600">New customers are marked <strong>Imported</strong>. They get bills and service messages on WhatsApp, but no offers until they agree.</p>
    </section>

    {{-- Step 3: confirm --}}
    <form method="POST" action="{{ route('customers.import.start', $token) }}" x-data="{ busy: false }" @submit="busy = true"
          class="sticky bottom-16 z-20 mt-5 flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between lg:bottom-4">
        @csrf
        <input type="hidden" name="header" value="{{ $headerLine }}">
        @foreach ($mapping as $field => $col)
            <input type="hidden" name="map[{{ $field }}]" value="{{ $col }}">
        @endforeach
        <p class="text-sm text-zinc-700">
            <strong class="tabular-nums">{{ number_format($counts['create'] ?? 0) }}</strong> new ·
            <strong class="tabular-nums">{{ number_format($counts['update'] ?? 0) }}</strong> get new details ·
            <span class="tabular-nums">{{ number_format($counts['same'] ?? 0) }}</span> already saved ·
            <span @class(['tabular-nums', 'font-semibold text-red-600' => ($counts['error'] ?? 0) > 0])>{{ number_format($counts['error'] ?? 0) }} skipped</span>
        </p>
        <button class="btn-accent min-h-12" @disabled(! $ready) x-bind:disabled="busy || {{ $ready ? 'false' : 'true' }}">
            <span x-show="!busy">Import {{ number_format($importable) }} {{ Str::plural('customer', $importable) }}</span>
            <span x-show="busy" x-cloak>Starting…</span>
        </button>
    </form>
</x-layouts.app>
