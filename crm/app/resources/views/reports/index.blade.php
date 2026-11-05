<x-layouts.app :title="$reports[$key] ?? 'Reports'">
    <x-finance.print-style />
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Reports</h1>
            <p class="text-sm font-medium text-zinc-600">{{ $reports[$key] }} · {{ $period->label() }}</p>
        </div>
        @if (! $blocked && $key !== 'ca')
            <div class="flex gap-2 print:hidden">
                <button type="button" onclick="window.print()" class="btn-ghost min-h-10"><x-finance.icon name="print" /> Print</button>
                <a href="{{ route('reports.export', array_merge(['report' => $key], $period->query())) }}" class="btn-ghost min-h-10"><x-finance.icon name="download" /> Export to Excel</a>
            </div>
        @endif
    </x-slot:header>

    <x-finance.tabs />

    {{-- Report picker --}}
    <nav aria-label="Reports" class="-mx-4 mb-4 overflow-x-auto px-4 print:hidden sm:mx-0 sm:px-0">
        <div class="flex min-w-max gap-2">
            @foreach ($reports as $k => $label)
                <a href="{{ route('reports.index', ['report' => $k]) }}" @class([
                    'inline-flex min-h-10 items-center rounded-lg border px-3 text-sm font-semibold',
                    'border-ink bg-ink text-white' => $k === $key,
                    'border-zinc-300 bg-white text-ink hover:bg-zinc-50' => $k !== $key,
                ])>{{ $label }}</a>
            @endforeach
        </div>
    </nav>

    <x-finance.period :period="$period" :action="route('reports.index')" :keep="['report' => $key]" class="mb-5" />

    {{-- Print header --}}
    <div class="mb-4 hidden print:block">
        <div class="text-lg font-bold">{{ \App\Models\Setting::get('shop.name') }} · {{ $reports[$key] }}</div>
        <div class="text-sm">{{ $period->label() }} · printed {{ now()->timezone('Asia/Kolkata')->format('j M Y, g:i A') }}</div>
    </div>

    @if ($blocked)
        <section class="card">
            <x-finance.empty icon="chart" title="Bills module not installed yet.">
                This report reads the bills. It fills in by itself as soon as the billing module is switched on. Expenses and the expense register already work.
            </x-finance.empty>
        </section>
    @else
        @if ($report['stats'])
            <div class="mb-5 grid grid-cols-2 gap-3 {{ count($report['stats']) >= 5 ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }}">
                @foreach ($report['stats'] as $i => [$label, $value, $hint])
                    <x-finance.stat :label="$label" :value="$value" :hint="$hint" :class="$i === 0 && count($report['stats']) % 2 === 1 ? 'col-span-2 sm:col-span-1 lg:col-span-1' : ''" />
                @endforeach
            </div>
        @endif

        @foreach ($report['notes'] as $note)
            <p class="mb-3 text-[13px] font-medium text-zinc-600">{{ $note }}</p>
        @endforeach

        @if ($key === 'ca')
            <div class="grid gap-4 md:grid-cols-2">
                <section class="card">
                    <h2 class="text-lg font-bold">Sales register</h2>
                    <p class="mt-1 text-sm text-zinc-700">One row per bill: date, bill number, customer, subtotal, discount, CGST, SGST, IGST, total, paid and due.</p>
                    @if ($installed)
                        <a href="{{ route('reports.register', array_merge(['type' => 'sales'], $period->query())) }}" class="btn-accent mt-4 min-h-12"><x-finance.icon name="download" /> Download sales register (.xlsx)</a>
                    @else
                        <p class="mt-4 rounded-lg bg-zinc-100 px-3 py-2 text-sm text-zinc-700">Bills module not installed yet.</p>
                    @endif
                </section>
                <section class="card">
                    <h2 class="text-lg font-bold">Expense register</h2>
                    <p class="mt-1 text-sm text-zinc-700">One row per expense: date, category, paid to, mode, amount, note and whether a receipt photo is attached.</p>
                    <a href="{{ route('reports.register', array_merge(['type' => 'expenses'], $period->query())) }}" class="btn-ghost mt-4 min-h-12"><x-finance.icon name="download" /> Download expense register (.xlsx)</a>
                </section>
            </div>
            @error('register') <p class="error mt-3">{{ $message }}</p> @enderror
        @endif

        <div class="space-y-5">
            @foreach ($report['sections'] as $section)
                <x-finance.table :section="$section" />
            @endforeach
        </div>
    @endif
</x-layouts.app>
