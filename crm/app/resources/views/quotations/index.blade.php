@php use App\Support\Money; use App\Models\Quotation; @endphp
<x-layouts.app title="Quotations">
<div x-data="{ asked: {{ $errors->hasAny(['mobile', 'what', 'price', 'product_id']) ? 'true' : 'false' }}, report: window.innerWidth >= 1024 }">

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-[28px] font-bold leading-tight tracking-[-0.02em] lg:text-[22px]">Quotations</h1>
            <p class="text-sm text-zinc-600">
                @if ($overdueCount)<a href="{{ route('quotations.index', ['filter' => 'overdue']) }}" class="font-semibold text-red-600 hover:underline">{{ $overdueCount }} overdue follow-up{{ $overdueCount > 1 ? 's' : '' }}</a> · @endif
                <a href="{{ route('followups.index') }}" class="font-semibold text-ink underline-offset-2 hover:underline">Open follow-ups today</a>
            </p>
        </div>
        @canany(['quotations.manage', 'leads.manage'])
            <div class="flex w-full gap-2 sm:w-auto">
                @can('leads.manage')
                <button type="button" @click="asked = true" class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold hover:bg-zinc-50 sm:min-h-10 sm:flex-none">
                    <x-quotations.icon name="tag" class="size-5" /> Asked price
                </button>
                @endcan
                @can('quotations.manage')
                <a href="{{ route('quotations.create') }}" class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-lg bg-brand-400 px-4 text-sm font-semibold text-ink hover:bg-[#F2C800] sm:min-h-10 sm:flex-none">
                    <x-quotations.icon name="plus" class="size-5" /> New quotation
                </a>
                @endcan
            </div>
        @endcanany
    </div>

    {{-- ===== Owner "leak" report ===== --}}
    @if ($report)
        @include('quotations._leak-report', ['r' => $report])
    @endif

    {{-- ===== Status chips + filters ===== --}}
    @php
        $chip = fn ($active) => $active ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white text-ink hover:bg-zinc-50';
        $all = $counts->sum();
        $qs = fn (array $over) => array_filter(array_merge(['status' => $status, 'filter' => $filter, 'q' => $search], $over), fn ($v) => $v !== null && $v !== '');
    @endphp
    <div class="mb-3 flex flex-col gap-3 min-[1400px]:flex-row min-[1400px]:items-center min-[1400px]:justify-between">
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0 lg:pb-0">
            <a href="{{ route('quotations.index', $qs(['status' => null])) }}" class="inline-flex h-10 shrink-0 items-center rounded-full border px-4 text-sm font-semibold {{ $chip(! $status) }}">All <span class="ml-1.5 tabular-nums opacity-70">{{ $all }}</span></a>
            @foreach (Quotation::STATUS_LABELS as $key => $label)
                <a href="{{ route('quotations.index', $qs(['status' => $key])) }}" class="inline-flex h-10 shrink-0 items-center rounded-full border px-4 text-sm font-semibold {{ $chip($status === $key) }}">{{ $label }} <span class="ml-1.5 tabular-nums opacity-70">{{ $counts[$key] ?? 0 }}</span></a>
            @endforeach
        </div>
        <div class="flex items-center gap-2">
            <div class="flex rounded-lg bg-zinc-100 p-1 text-sm font-semibold">
                @foreach (['overdue' => 'Overdue', 'mine' => 'Mine', 'week' => 'This week'] as $key => $label)
                    <a href="{{ route('quotations.index', $qs(['filter' => $filter === $key ? null : $key])) }}"
                       @class(['whitespace-nowrap rounded-md px-3 py-1.5', 'border border-zinc-200 bg-white text-ink' => $filter === $key, 'text-zinc-600 hover:text-ink' => $filter !== $key, '!text-red-600' => $key === 'overdue' && $filter !== $key && $overdueCount])>{{ $label }}</a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('quotations.index') }}" class="relative hidden lg:block">
                @foreach ($qs(['q' => null]) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <x-quotations.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-500" />
                <input data-search name="q" value="{{ $search }}" placeholder="Number, name or mobile" class="h-10 w-64 rounded-lg border border-zinc-300 bg-white pl-9 pr-3 text-sm placeholder:text-zinc-500 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink">
            </form>
        </div>
    </div>

    @if ($quotes->isEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white px-6 py-12 text-center">
            <x-quotations.icon name="doc" class="mx-auto size-8 text-zinc-600" />
            <p class="mt-3 text-[15px] font-medium text-zinc-700">
                @if ($status || $filter || $search) No quotations match these filters. @else No quotations yet. Make the first one when a customer asks for a price. @endif
            </p>
            @if ($status || $filter || $search)
                <a href="{{ route('quotations.index') }}" class="mt-4 inline-flex min-h-11 items-center rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold hover:bg-zinc-50">Clear filters</a>
            @else
                @can('quotations.manage')<a href="{{ route('quotations.create') }}" class="mt-4 inline-flex min-h-11 items-center rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold hover:bg-zinc-50">+ New quotation</a>@endcan
            @endif
        </div>
    @else
        {{-- PC table --}}
        <div class="hidden overflow-x-auto rounded-xl border border-zinc-200 bg-white lg:block">
            <table class="w-full text-[13px]">
                <thead class="bg-zinc-100 text-left text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">
                    <tr>
                        <th class="px-4 py-3">Quote</th>
                        <th class="px-4 py-3">Customer · car</th>
                        <th class="px-4 py-3">Items</th>
                        <th class="px-4 py-3 text-right">Value</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="whitespace-nowrap px-4 py-3">Next follow-up</th>
                        <th class="hidden px-4 py-3 2xl:table-cell">Staff</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @foreach ($quotes as $q)
                        @php $next = $q->followUps->first(); $late = $next && $next->isOverdue(); @endphp
                        <tr class="cursor-pointer hover:bg-[#FAFAFA]" onclick="if (!event.target.closest('a')) location = '{{ route('quotations.show', $q) }}'">
                            <td class="px-4 py-3 align-top">
                                <a href="{{ route('quotations.show', $q) }}" class="whitespace-nowrap font-semibold tabular-nums hover:underline">{{ $q->number }}</a>
                                <div class="text-xs text-zinc-600">{{ $q->created_at->timezone('Asia/Kolkata')->format('j M') }}@if ($q->version > 1) · v{{ $q->version }}@endif</div>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <div class="font-semibold">{{ $q->customer->name }}</div>
                                <div class="text-xs text-zinc-600">{{ $q->carLabel() ?: \App\Support\Phone::pretty($q->customer->mobile) }}</div>
                                <div class="text-xs text-zinc-600 2xl:hidden">{{ $q->assignee?->name }}</div>
                            </td>
                            <td class="px-4 py-3 align-top"><div class="line-clamp-2 max-w-60">{{ $q->itemsSummary(2) }}</div></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right align-top">
                                <div class="text-[15px] font-semibold tabular-nums">{{ Money::format($q->total_paise) }}</div>
                                @if ($q->savings_paise > 0)<div class="text-xs font-semibold text-green-700">saves {{ Money::format($q->savings_paise) }}</div>@endif
                            </td>
                            <td class="px-4 py-3 align-top"><x-quotations.status :status="$q->status" /></td>
                            <td class="whitespace-nowrap px-4 py-3 align-top">
                                @if ($next)
                                    <span @class(['font-semibold', 'text-red-600' => $late])>{{ $next->due_on->format('D j M') }}</span>
                                    <div class="text-xs {{ $late ? 'text-red-600' : 'text-zinc-600' }}">{{ $late ? 'Overdue · ' : '' }}{{ $next->stepLabel() }}</div>
                                @elseif ($q->status === 'won')
                                    <span class="text-zinc-600">Billed</span>
                                @else
                                    <span class="text-zinc-600">—</span>
                                @endif
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-3 align-top text-zinc-700 2xl:table-cell">{{ $q->assignee?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Phone rows --}}
        <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white lg:hidden">
            @foreach ($quotes as $q)
                @php $next = $q->followUps->first(); $late = $next && $next->isOverdue(); @endphp
                <a href="{{ route('quotations.show', $q) }}" class="flex min-h-[72px] items-start justify-between gap-3 px-4 py-3 active:bg-zinc-50">
                    <div class="min-w-0">
                        <div class="truncate text-[15px] font-semibold">{{ $q->customer->name }}@if ($q->car)<span class="font-medium text-zinc-600"> · {{ $q->car->model }}</span>@endif</div>
                        <div class="truncate text-[13px] font-medium text-zinc-600">{{ $q->itemsSummary(2) }}</div>
                        <div class="mt-0.5 text-xs {{ $late ? 'font-semibold text-red-600' : 'text-zinc-600' }}">
                            {{ $q->number }} · @if ($next){{ $late ? 'Overdue' : 'Next' }} {{ $next->due_on->format('j M') }}@else{{ $q->created_at->timezone('Asia/Kolkata')->format('j M') }}@endif
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="text-[15px] font-semibold tabular-nums">{{ Money::format($q->total_paise) }}</div>
                        <x-quotations.status :status="$q->status" class="mt-1" />
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4">{{ $quotes->links() }}</div>
    @endif

    {{-- ===== "Asked price" quick-log sheet ===== --}}
    @can('leads.manage')
        @include('quotations._asked-price-sheet')
    @endcan
</div>
</x-layouts.app>
