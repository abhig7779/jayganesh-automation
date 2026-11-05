@php
    use App\Support\Money;
    use App\Support\Phone;
    use App\Models\Payment;
    $isToday = $f['from']->isToday() && $f['to']->isToday();
    $rangeLabel = $f['from']->equalTo($f['to'])
        ? ($isToday ? 'Today' : $f['from']->format('j M Y'))
        : $f['from']->format('j M').' – '.$f['to']->format('j M Y');
    $q = request()->except('page');
    $chip = fn (array $set) => route('bills.index', array_merge(array_diff_key($q, array_flip(['range', 'from', 'to'])), $set));
    $activeFilters = collect([$f['status'], $f['mode'], $f['staff'], $f['unpaid'] ? 1 : null, $f['q']])->filter()->count();
@endphp
<x-layouts.app title="Bills">
    <x-slot:header>
        <div>
            <h1 class="text-[28px] font-bold leading-tight lg:text-2xl">Bills</h1>
            <p class="text-sm text-zinc-600">{{ $rangeLabel }}@unless (auth()->user()->can('reports.all_staff')) · your bills @endunless</p>
        </div>
        <div class="flex gap-2">
            @can('payments.collect')
                <a href="{{ route('payments.collect') }}" class="btn-ghost"><x-bills.icon name="wallet" class="size-4" />Collect payment</a>
            @endcan
            @can('invoices.create')
                <a href="{{ route('bills.create') }}" class="btn-accent"><x-bills.icon name="plus" class="size-4" />New bill <x-bills.kbd class="hidden lg:inline-flex">F2</x-bills.kbd></a>
            @endcan
        </div>
    </x-slot:header>

    {{-- Filters --}}
    <form method="GET" action="{{ route('bills.index') }}" class="mb-4 space-y-3" x-data="{ more: {{ $activeFilters > 0 || request()->has('from') ? 'true' : 'false' }} }">
        <div class="flex flex-wrap items-center gap-2">
            <label class="flex h-11 min-w-0 flex-1 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 focus-within:border-ink lg:max-w-md">
                <x-bills.icon name="search" class="size-5 text-zinc-500" />
                <input name="q" value="{{ $f['q'] }}" placeholder="Bill no., name, mobile or car number" class="min-w-0 flex-1 border-0 bg-transparent text-[15px] focus:outline-none" aria-label="Search bills">
            </label>
            <div class="flex gap-1 overflow-x-auto">
                @foreach (['' => 'Today', 'yesterday' => 'Yesterday', 'week' => '7 days', 'month' => 'This month'] as $r => $label)
                    @php $on = ($r === '' && $isToday && ! $f['range']) || $f['range'] === $r && $r !== ''; @endphp
                    <a href="{{ $chip($r === '' ? [] : ['range' => $r]) }}" @class(['inline-flex h-9 shrink-0 items-center rounded-full border px-3 text-[13px] font-semibold', 'border-ink bg-ink text-white' => $on, 'border-zinc-300 bg-white text-ink hover:border-ink' => ! $on])>{{ $label }}</a>
                @endforeach
                <a href="{{ route('bills.index', ['unpaid' => 1]) }}" @class(['inline-flex h-9 shrink-0 items-center rounded-full border px-3 text-[13px] font-semibold', 'border-red-600 bg-red-50 text-red-700' => $f['unpaid'], 'border-zinc-300 bg-white text-ink hover:border-ink' => ! $f['unpaid']])>Unpaid</a>
            </div>
            <button type="button" @click="more = !more" class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2 text-[13px] font-semibold text-ink"><x-bills.icon name="filter" class="size-4" />Filters @if ($activeFilters)<span class="rounded-full bg-ink px-1.5 text-[11px] text-white">{{ $activeFilters }}</span>@endif</button>
        </div>
        <div x-show="more" x-cloak class="grid grid-cols-2 gap-2 rounded-xl border border-zinc-200 bg-white p-3 sm:grid-cols-3 lg:grid-cols-7">
            <label class="text-xs font-semibold text-zinc-700">From<input type="date" name="from" value="{{ $f['from']->format('Y-m-d') }}" class="input mt-1 !min-h-10"></label>
            <label class="text-xs font-semibold text-zinc-700">To<input type="date" name="to" value="{{ $f['to']->format('Y-m-d') }}" class="input mt-1 !min-h-10"></label>
            <label class="text-xs font-semibold text-zinc-700">Status
                <select name="status" class="input mt-1 !min-h-10"><option value="">Any</option>
                    @foreach (\App\Models\Invoice::STATUS_LABELS as $v => $l)<option value="{{ $v }}" @selected($f['status'] === $v)>{{ $l }}</option>@endforeach
                </select>
            </label>
            <label class="text-xs font-semibold text-zinc-700">Paid by
                <select name="mode" class="input mt-1 !min-h-10"><option value="">Any</option>
                    @foreach (Payment::MODE_LABELS as $v => $l)<option value="{{ $v }}" @selected($f['mode'] === $v)>{{ $l }}</option>@endforeach
                </select>
            </label>
            @if ($staffList->isNotEmpty())
                <label class="text-xs font-semibold text-zinc-700">Staff
                    <select name="staff" class="input mt-1 !min-h-10"><option value="">Everyone</option>
                        @foreach ($staffList as $s)<option value="{{ $s->id }}" @selected($f['staff'] === $s->id)>{{ $s->name }}</option>@endforeach
                    </select>
                </label>
            @endif
            <label class="flex items-end gap-2 pb-2.5 text-[13px] font-semibold"><input type="checkbox" name="unpaid" value="1" @checked($f['unpaid']) class="size-4 accent-ink">Unpaid only</label>
            <div class="flex items-end gap-2">
                <button class="btn-primary !min-h-10 flex-1">Show</button>
                <a href="{{ route('bills.index') }}" class="btn-ghost !min-h-10">Reset</a>
            </div>
        </div>
    </form>

    {{-- Totals for the filter --}}
    <div class="mb-4 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 sm:grid-cols-4">
        @foreach ([
            ['Bills', number_format($totals['count']).($totals['cancelled'] ? ' ('.$totals['cancelled'].' cancelled)' : ''), 'text-ink'],
            ['Sales', Money::format($totals['total']), 'text-ink'],
            ['Received', Money::format($totals['paid']), 'text-ink'],
            ['Due', Money::format($totals['due']), $totals['due'] > 0 ? 'text-red-600' : 'text-ink'],
        ] as [$label, $value, $cls])
            <div class="bg-white px-4 py-3">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-zinc-600">{{ $label }}</div>
                <div class="mt-0.5 text-xl font-bold tabular-nums {{ $cls }}">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @if ($bills->isEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white px-6 py-10 text-center">
            <x-bills.icon name="receipt" class="mx-auto size-8 text-zinc-500" />
            <p class="mt-3 text-[15px] font-medium text-zinc-700">
                @if ($f['q'] || $activeFilters) No bills match these filters. @elseif ($isToday) No bills yet today. @else No bills in this period. @endif
            </p>
            @can('invoices.create')
                <a href="{{ route('bills.create') }}" class="btn-ghost mt-4">+ New bill</a>
            @endcan
        </div>
    @else
        {{-- Phone: card rows --}}
        <ul class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white lg:hidden">
            @foreach ($bills as $b)
                <li>
                    <a href="{{ route('bills.show', $b) }}" class="flex min-h-[68px] items-center gap-3 px-4 py-3 active:bg-zinc-50">
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-zinc-100 text-[13px] font-bold">{{ collect(explode(' ', preg_replace('/[^\p{L}\s]/u', '', $b->customer_name ?: 'W')))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('') }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[15px] font-semibold">{{ $b->customer_name }}</span>
                            <span class="block truncate text-[13px] text-zinc-600">{{ $b->number }} · {{ $b->created_at->timezone('Asia/Kolkata')->format($isToday ? 'g:i A' : 'j M, g:i A') }}</span>
                        </span>
                        <span class="text-right">
                            <span @class(['block text-[15px] font-semibold tabular-nums', 'text-zinc-500 line-through' => $b->isCancelled()])>{{ Money::format($b->total_paise) }}</span>
                            @if ($b->status !== 'paid')<x-bills.status :status="$b->status" />@else<x-bills.wa-tick :status="$waStatus[$b->id] ?? null" :send="$b->send_whatsapp" />@endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- PC: table --}}
        <div class="hidden overflow-hidden rounded-xl border border-zinc-200 bg-white lg:block">
            <table class="w-full text-[13px]">
                <thead class="sticky top-0 bg-zinc-100 text-left text-[11px] font-semibold uppercase tracking-wider text-zinc-600">
                    <tr>
                        <th class="px-4 py-2.5">Bill no.</th>
                        <th class="px-3 py-2.5">Time</th>
                        <th class="px-3 py-2.5">Customer</th>
                        <th class="px-3 py-2.5">Car</th>
                        <th class="px-3 py-2.5">Paid by</th>
                        <th class="px-3 py-2.5">Staff</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">WhatsApp</th>
                        <th class="px-3 py-2.5 text-right">Due</th>
                        <th class="px-4 py-2.5 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($bills as $b)
                        <tr class="cursor-pointer hover:bg-[#FAFAFA]" onclick="if(!event.target.closest('a'))window.location='{{ route('bills.show', $b) }}'">
                            <td class="whitespace-nowrap px-4 py-2.5 font-semibold"><a href="{{ route('bills.show', $b) }}" class="hover:underline">{{ $b->number }}</a></td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-zinc-600">{{ $b->created_at->timezone('Asia/Kolkata')->format($isToday ? 'g:i A' : 'j M, g:i A') }}</td>
                            <td class="px-3 py-2.5">
                                <span class="font-medium text-ink">{{ $b->customer_name }}</span>
                                @if ($b->customer_mobile)<span class="block whitespace-nowrap text-xs text-zinc-500">{{ Phone::pretty($b->customer_mobile) }}</span>@endif
                            </td>
                            <td class="max-w-44 truncate px-3 py-2.5 text-zinc-600">{{ $b->car_label ?? '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-zinc-700">{{ collect($b->paidByMode())->keys()->map(fn ($m) => Payment::MODE_LABELS[$m] ?? $m)->implode(' + ') ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-zinc-600">{{ \Illuminate\Support\Str::before(trim((string) $b->creator?->name), ' ') }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5"><x-bills.status :status="$b->status" /></td>
                            <td class="whitespace-nowrap px-3 py-2.5"><x-bills.wa-tick :status="$waStatus[$b->id] ?? null" :send="$b->send_whatsapp" /></td>
                            <td class="px-3 py-2.5 text-right tabular-nums {{ $b->due_paise > 0 ? 'font-semibold text-red-600' : 'text-zinc-400' }}">{{ $b->due_paise > 0 ? Money::format($b->due_paise) : '—' }}</td>
                            <td @class(['px-4 py-2.5 text-right font-semibold tabular-nums', 'text-zinc-500 line-through' => $b->isCancelled()])>{{ Money::format($b->total_paise) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $bills->links() }}</div>
    @endif
</x-layouts.app>
