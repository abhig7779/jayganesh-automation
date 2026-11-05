@php
    use App\Support\Money;
    $hasFilters = $filters['supplier'] || $filters['state'] || $filters['from'] || $filters['to'] || $filters['q'];
    $states = ['' => 'All', 'due' => 'To pay', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];
    $keep = array_filter(['supplier' => $filters['supplier'], 'from' => $filters['from'], 'to' => $filters['to'], 'q' => $filters['q']]);
    $badge = ['paid' => 'paid', 'part' => 'part_paid', 'due' => 'due', 'cancelled' => 'cancelled'];
    $whole = fn (int $p) => intdiv($p + 50, 100) * 100; // summary tiles show whole rupees
@endphp
<x-layouts.app title="Purchases">
    <x-ui.page-header title="Purchases" subtitle="Stock in from suppliers">
        <x-slot:actions>
            <a href="{{ route('suppliers.index') }}" class="btn-ghost"><x-icon name="users" class="size-5" /> Suppliers</a>
            <a href="{{ route('purchases.create') }}" class="btn-accent"><x-icon name="plus" class="size-5" stroke="2" /> New purchase</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card"><x-ui.stat label="Bills" :value="number_format($totals['count'])" :hint="$hasFilters ? 'Matching the filters' : 'All time'" /></div>
        <div class="card"><x-ui.stat label="Bought" :paise="$whole($totals['total_paise'])" hint="Including GST" /></div>
        <div class="card"><x-ui.stat label="GST input" :paise="$whole($totals['gst_paise'])" hint="Claimable on purchases" /></div>
        <div class="card"><x-ui.stat label="Still to pay" :paise="$whole($totals['due_paise'])" :tone="$totals['due_paise'] > 0 ? 'bad' : null" hint="On these bills" /></div>
    </div>

    <form method="GET" action="{{ route('purchases.index') }}" class="mb-4 grid grid-cols-2 gap-2 lg:grid-cols-[1.4fr_1fr_150px_150px_auto]">
        @if ($filters['state']) <input type="hidden" name="state" value="{{ $filters['state'] }}"> @endif
        <input type="search" name="q" value="{{ $filters['q'] }}" class="input col-span-2 lg:col-span-1" placeholder="Search supplier, bill no. or item" aria-label="Search purchases">
        <select name="supplier" class="input col-span-2 lg:col-span-1" aria-label="Supplier" onchange="this.form.submit()">
            <option value="">All suppliers</option>
            @foreach ($suppliers as $s)
                <option value="{{ $s->id }}" @selected($filters['supplier'] === $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="input" aria-label="From date">
        <input type="date" name="to" value="{{ $filters['to'] }}" class="input" aria-label="To date">
        <button class="btn-ghost col-span-2 lg:col-span-1"><x-icon name="filter" class="size-5" /> Filter</button>
    </form>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach ($states as $k => $label)
            <a href="{{ route('purchases.index', array_merge($keep, $k ? ['state' => $k] : [])) }}"
               @class(['inline-flex min-h-10 items-center rounded-full border px-3.5 text-[13px] font-semibold', 'border-ink bg-ink text-white' => ($filters['state'] ?? '') === $k, 'border-line-strong bg-surface text-ink-2 hover:bg-sunken' => ($filters['state'] ?? '') !== $k])>{{ $label }}</a>
        @endforeach
        @if ($hasFilters)
            <a href="{{ route('purchases.index') }}" class="btn-quiet ml-auto text-sm">Clear filters</a>
        @endif
    </div>

    <section class="overflow-hidden rounded-xl border border-line bg-surface">
        @if ($purchases->isEmpty())
            @if ($hasFilters)
                <x-ui.empty-state icon="filter" text="No purchases match these filters.">
                    <a href="{{ route('purchases.index') }}" class="btn-ghost">Clear filters</a>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state icon="box" text="No stock in yet. Enter the first supplier bill and the stock goes up by itself.">
                    <a href="{{ route('purchases.create') }}" class="btn-ghost">+ New purchase</a>
                </x-ui.empty-state>
            @endif
        @else
            {{-- PC table --}}
            <table class="table-pc hidden lg:table">
                <thead>
                    <tr><th>Date</th><th>Ref</th><th>Supplier</th><th>Supplier bill</th><th class="text-right">Items</th><th class="text-right">GST</th><th class="text-right">Total</th><th class="text-right">To pay</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach ($purchases as $p)
                        <tr data-href="{{ route('purchases.show', $p) }}" @class(['opacity-60' => $p->isCancelled()])>
                            <td class="whitespace-nowrap">{{ $p->bill_date->format('j M Y') }}</td>
                            <td class="whitespace-nowrap"><a href="{{ route('purchases.show', $p) }}" class="font-semibold text-ink hover:underline">{{ $p->ref() }}</a></td>
                            <td class="max-w-56 truncate font-semibold text-ink">{{ $p->supplier->name }}</td>
                            <td class="whitespace-nowrap">{{ $p->bill_no ?: '—' }} @if ($p->photo_path)<x-icon name="camera" class="ml-1 inline size-4 text-faint" title="Photo attached" />@endif</td>
                            <td class="tnum text-right">{{ $p->items_count }}</td>
                            <td class="tnum text-right">{{ Money::format($p->gst_paise) }}</td>
                            <td @class(['tnum text-right font-semibold text-ink', 'line-through' => $p->isCancelled()])>{{ Money::format($p->total_paise) }}</td>
                            <td @class(['tnum text-right font-semibold', 'text-bad' => $p->duePaise() > 0, 'text-faint' => $p->duePaise() === 0])>{{ $p->duePaise() > 0 ? Money::format($p->duePaise()) : '—' }}</td>
                            <td><x-ui.status-badge :status="$badge[$p->payState()]" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Phone list --}}
            <ul class="divide-y divide-line lg:hidden">
                @foreach ($purchases as $p)
                    <li>
                        <a href="{{ route('purchases.show', $p) }}" @class(['flex min-h-16 items-center gap-3 px-4 py-3 active:bg-sunken', 'opacity-60' => $p->isCancelled()])>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[15px] font-semibold text-ink">{{ $p->supplier->name }}</div>
                                <div class="truncate text-[13px] font-medium text-muted">{{ $p->bill_date->format('j M') }} · {{ $p->ref() }}{{ $p->bill_no ? ' · bill '.$p->bill_no : '' }} · {{ $p->items_count }} {{ $p->items_count === 1 ? 'item' : 'items' }}</div>
                            </div>
                            <div class="text-right">
                                <div @class(['tnum text-[15px] font-semibold', 'line-through' => $p->isCancelled()])>{{ Money::format($p->total_paise) }}</div>
                                <x-ui.status-badge :status="$badge[$p->payState()]" class="mt-1" />
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($purchases->hasPages())
                <div class="border-t border-line px-4 py-3">{{ $purchases->links() }}</div>
            @endif
        @endif
    </section>
</x-layouts.app>
