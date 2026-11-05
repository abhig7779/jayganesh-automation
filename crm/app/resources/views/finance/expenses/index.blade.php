@php
    use App\Support\Money;
    use App\Models\Expense;
    $canManage = auth()->user()->can('finance.manage');
    $keep = array_filter(['category' => $filters['category'], 'mode' => $filters['mode'], 'q' => $filters['q']]);
    $maxCat = max(1, (int) $byCategory->max('total'));
    $hasFilters = $filters['category'] || $filters['mode'] || $filters['q'];
@endphp
<x-layouts.app title="Expenses">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Expenses</h1>
            <p class="text-sm font-medium text-zinc-600">{{ $period->label() }}</p>
        </div>
        @if ($canManage)
            <a href="{{ route('finance.expenses.create') }}" class="btn-accent min-h-12 lg:hidden"><x-finance.icon name="plus" /> Add expense</a>
        @endif
    </x-slot:header>

    <x-finance.tabs />

    <div class="grid grid-cols-1 gap-5 *:min-w-0 lg:grid-cols-12">
        <div class="space-y-5 lg:col-span-8">
            <x-finance.period :period="$period" :action="route('finance.expenses.index')" :keep="$keep" />

            {{-- Filters --}}
            <form method="GET" action="{{ route('finance.expenses.index') }}" class="grid grid-cols-2 gap-2 sm:grid-cols-[1fr_1fr_1.4fr_auto]">
                @foreach ($period->query() as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <select name="category" class="input" aria-label="Category" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected($filters['category'] === $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                <select name="mode" class="input" aria-label="Paid by" onchange="this.form.submit()">
                    <option value="">All modes</option>
                    @foreach (Expense::MODES as $k => $label)
                        <option value="{{ $k }}" @selected($filters['mode'] === $k)>{{ $label }} only</option>
                    @endforeach
                </select>
                <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search paid to or note" class="input col-span-2 sm:col-span-1">
                <button class="btn-ghost col-span-2 sm:col-span-1">Filter</button>
            </form>

            {{-- Totals --}}
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">
                <x-finance.stat label="Total spent" :value="Money::format($totals['total_paise'])" :hint="$totals['count'].' '.($totals['count'] === 1 ? 'entry' : 'entries')" class="col-span-3 sm:col-span-1" />
                @foreach (Expense::MODES as $k => $label)
                    <x-finance.stat :label="$label" :value="Money::format($totals['modes'][$k] ?? 0)" />
                @endforeach
            </div>

            @if ($byCategory->isNotEmpty())
                <section class="card">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">By category</h2>
                    <div class="mt-3 space-y-2.5">
                        @foreach ($byCategory as $row)
                            <div class="grid grid-cols-[110px_1fr_auto] items-center gap-3 sm:grid-cols-[140px_1fr_auto]">
                                <span class="truncate text-sm font-semibold text-zinc-700">{{ $row->name }}</span>
                                <span class="h-2 overflow-hidden rounded-sm bg-zinc-100" aria-hidden="true"><span class="block h-full rounded-sm bg-ink" style="width: {{ round($row->total * 100 / $maxCat, 1) }}%"></span></span>
                                <span class="w-24 text-right text-sm font-semibold tabular-nums">{{ Money::format((int) $row->total) }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- List --}}
            <section class="card overflow-hidden p-0 sm:p-0">
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 sm:px-5">
                    <h2 class="text-[15px] font-semibold">Entries</h2>
                    <a href="{{ route('finance.expenses.index', array_merge($period->query(), $keep, ['cancelled' => $filters['cancelled'] ? null : 1])) }}" class="text-sm font-semibold text-zinc-600 hover:text-ink">{{ $filters['cancelled'] ? 'Hide cancelled' : 'Show cancelled' }}</a>
                </div>
                @if ($expenses->isEmpty())
                    @if ($hasFilters)
                        <x-finance.empty icon="receipt" title="No expenses match these filters." :href="route('finance.expenses.index', $period->query())" action="Clear filters" />
                    @else
                        <x-finance.empty icon="receipt" title="No expenses in this period." :href="$canManage ? route('finance.expenses.create') : null" :action="$canManage ? '+ Add expense' : null" />
                    @endif
                @else
                    <table class="w-full text-left text-[13px] font-medium">
                        <thead class="hidden bg-zinc-100 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600 sm:table-header-group">
                            <tr><th class="px-5 py-2.5">Date</th><th class="px-4 py-2.5">Spent on</th><th class="px-4 py-2.5">Paid to / note</th><th class="px-4 py-2.5">Mode</th><th class="px-4 py-2.5 text-right">Amount</th><th class="px-4 py-2.5"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach ($expenses as $e)
                                <tr @class(['block px-4 py-3 sm:table-row sm:p-0 hover:bg-zinc-50', 'opacity-60' => $e->isCancelled()])>
                                    <td class="hidden whitespace-nowrap px-5 py-2.5 text-zinc-700 sm:table-cell">{{ $e->date->format('D, j M') }}</td>
                                    <td class="block sm:table-cell sm:whitespace-nowrap sm:px-4 sm:py-2.5">
                                        <div class="flex items-baseline justify-between gap-3 sm:block">
                                            <span class="text-[15px] font-semibold text-ink sm:text-[13px]">{{ $e->category->name }}</span>
                                            <span @class(['text-[15px] font-semibold tabular-nums sm:hidden', 'line-through' => $e->isCancelled()])>{{ Money::format($e->amount_paise) }}</span>
                                        </div>
                                    </td>
                                    <td class="block text-zinc-600 sm:table-cell sm:max-w-64 sm:px-4 sm:py-2.5">
                                        <span class="sm:hidden">{{ $e->date->format('D, j M') }} · {{ Expense::MODES[$e->mode] ?? $e->mode }}{{ $e->paid_to || $e->note ? ' · ' : '' }}</span>
                                        <span class="sm:block sm:truncate">{{ $e->paid_to }}{{ $e->paid_to && $e->note ? ' · ' : '' }}{{ $e->note }}</span>
                                        @if ($e->isCancelled())<span class="ml-1 rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-600">Cancelled</span>@endif
                                    </td>
                                    <td class="hidden px-4 py-2.5 text-zinc-700 sm:table-cell">{{ Expense::MODES[$e->mode] ?? $e->mode }}</td>
                                    <td @class(['hidden px-4 py-2.5 text-right text-[15px] font-semibold tabular-nums text-ink sm:table-cell', 'line-through' => $e->isCancelled()])>{{ Money::format($e->amount_paise) }}</td>
                                    <td class="mt-1 flex gap-4 sm:mt-0 sm:table-cell sm:whitespace-nowrap sm:px-4 sm:py-2.5 sm:text-right">
                                        @if ($e->receipt_path)
                                            <a href="{{ route('finance.expenses.receipt', $e) }}" target="_blank" class="inline-flex min-h-8 items-center gap-1 text-sm font-semibold text-ink hover:underline"><x-finance.icon name="receipt" class="size-4" /> Receipt</a>
                                        @endif
                                        @if ($canManage && ! $e->isCancelled())
                                            <a href="{{ route('finance.expenses.edit', $e) }}" class="inline-flex min-h-8 items-center gap-1 text-sm font-semibold text-ink hover:underline sm:ml-3"><x-finance.icon name="pencil" class="size-4" /> Edit</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($expenses->hasPages())
                        <div class="border-t border-zinc-200 px-4 py-3">{{ $expenses->links() }}</div>
                    @endif
                @endif
            </section>
        </div>

        {{-- Quick add (PC). On the phone the yellow "Add expense" button opens the full-screen form. --}}
        @if ($canManage)
            <aside class="hidden lg:col-span-4 lg:block">
                <div class="card sticky top-6">
                    <h2 class="mb-4 text-lg font-bold">Quick add</h2>
                    @include('finance.expenses._form', ['expense' => new \App\Models\Expense(['mode' => 'cash']), 'prefix' => 'quick'])
                </div>
            </aside>
        @endif
    </div>
</x-layouts.app>
