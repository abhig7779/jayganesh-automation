@php
    use App\Support\Money;
    use App\Services\Finance\SalesQueries;
    use App\Services\Finance\CashCloseService;
    $c = $home['collections'];
    $maxMode = max(1, $c['cash'], $c['upi'], $c['card'], $c['bank']);
    $d = $home['dues'];
    $maxDue = max(1, ...array_values($d['buckets']));
    $canManage = auth()->user()->can('finance.manage');
@endphp
<x-layouts.app title="Finance">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Finance</h1>
            <p class="text-sm font-medium text-zinc-600">{{ \Carbon\Carbon::parse($home['date'])->format('l, j M Y') }}</p>
        </div>
    </x-slot:header>

    <x-finance.tabs />

    @if ($home['missing_close'])
        <div role="alert" class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
            <x-finance.icon name="alert" class="size-5 shrink-0 text-red-600" />
            <span class="flex-1 text-sm font-semibold text-red-700">{{ $home['missing_close']['label'] }}.</span>
            @if ($home['missing_close']['url'])
                <a href="{{ $home['missing_close']['url'] }}" class="btn-ghost min-h-10 border-red-200 text-red-700">Do it now</a>
            @endif
        </div>
    @endif

    {{-- Daily jobs --}}
    <div class="grid grid-cols-2 gap-3 *:min-w-0 lg:grid-cols-4 [&>*:last-child:nth-child(even)]:col-span-2 lg:[&>*:last-child:nth-child(even)]:col-span-1">
        <a href="{{ route('finance.cash-close') }}" class="col-span-2 flex min-h-[72px] items-center gap-3 rounded-xl bg-brand-400 px-4 font-semibold text-ink hover:bg-brand-300 lg:col-span-1">
            <x-finance.icon name="wallet" class="size-6" />
            <span class="flex-1">
                <span class="block text-[17px]">Cash close</span>
                <span class="block text-xs font-medium">{{ $home['cash_closed'] ? 'Done for today · view' : 'Count the drawer at day end' }}</span>
            </span>
        </a>
        @if ($canManage)
            <a href="{{ route('finance.expenses.create') }}" class="flex min-h-[72px] items-center gap-3 rounded-xl border border-zinc-300 bg-white px-4 font-semibold hover:bg-zinc-50">
                <x-finance.icon name="plus" class="size-6" /> Add expense
            </a>
        @endif
        @if ($collectRoute)
            <a href="{{ $collectRoute }}" class="flex min-h-[72px] items-center gap-3 rounded-xl border border-zinc-300 bg-white px-4 font-semibold hover:bg-zinc-50">
                <x-icon name="rupee" class="size-6" /> Collect payment
            </a>
        @endif
        <a href="{{ route('finance.dues') }}" class="flex min-h-[72px] items-center gap-3 rounded-xl border border-zinc-300 bg-white px-4 font-semibold hover:bg-zinc-50">
            <x-finance.icon name="clock" class="size-6" />
            <span>Dues <span class="block text-xs font-semibold {{ $d['total_paise'] ? 'text-red-600' : 'text-zinc-600' }}">{{ Money::format($d['total_paise']) }} pending</span></span>
        </a>
        @can('reports.view')
            <a href="{{ route('reports.index') }}" class="flex min-h-[72px] items-center gap-3 rounded-xl border border-zinc-300 bg-white px-4 font-semibold hover:bg-zinc-50">
                <x-finance.icon name="chart" class="size-6" /> Reports
            </a>
        @endcan
    </div>

    <div class="mt-6 grid grid-cols-1 gap-5 *:min-w-0 lg:grid-cols-12">
        {{-- Money in today --}}
        <section class="card lg:col-span-5">
            <div class="flex items-baseline justify-between">
                <h2 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Money in today</h2>
                <span class="text-2xl font-bold tabular-nums">{{ Money::format($c['total']) }}</span>
            </div>
            @if (! $home['billing_installed'])
                <p class="mt-3 rounded-lg bg-zinc-100 px-3 py-2 text-sm text-zinc-700">Bills module not installed yet. Payments will show here once bills are being made.</p>
            @endif
            <dl class="mt-4 space-y-3">
                @foreach (SalesQueries::MODES as $mode => $label)
                    <div class="grid grid-cols-[56px_1fr_auto] items-center gap-3">
                        <dt class="text-sm font-semibold text-zinc-700">{{ $label }}</dt>
                        <div class="h-2 overflow-hidden rounded-sm bg-zinc-100" aria-hidden="true"><div class="h-full rounded-sm bg-ink" style="width: {{ round($c[$mode] * 100 / $maxMode, 1) }}%"></div></div>
                        <dd class="w-28 text-right text-[15px] font-semibold tabular-nums">{{ Money::format($c[$mode]) }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- Drawer --}}
        <section class="card lg:col-span-3">
            <h2 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Cash in drawer now</h2>
            <div class="mt-2 text-3xl font-bold tabular-nums tracking-tight">{{ Money::format($home['expected_cash_paise']) }}</div>
            <p class="mt-1 text-xs font-medium text-zinc-600">Opening float + cash in − cash expenses ({{ Money::format($home['expenses']['cash_paise']) }})</p>
            <div class="mt-4">
                @if ($home['cash_closed'])
                    <span class="inline-flex items-center gap-1 rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700"><x-finance.icon name="check" class="size-3.5" /> Closed for today</span>
                @else
                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Not closed yet</span>
                @endif
            </div>
        </section>

        {{-- Dues ageing --}}
        <section class="card lg:col-span-4">
            <div class="flex items-baseline justify-between">
                <h2 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Dues by age</h2>
                <a href="{{ route('finance.dues') }}" class="text-sm font-semibold underline decoration-brand-400 decoration-2 underline-offset-4">See all</a>
            </div>
            @if (! $home['billing_installed'])
                <p class="mt-3 rounded-lg bg-zinc-100 px-3 py-2 text-sm text-zinc-700">Bills module not installed yet.</p>
            @else
                <dl class="mt-4 space-y-3">
                    @foreach (SalesQueries::AGEING as $key => $label)
                        <a href="{{ route('finance.dues', ['age' => $key]) }}" class="grid grid-cols-[88px_1fr_auto] items-center gap-3 hover:opacity-80">
                            <dt class="text-sm font-semibold text-zinc-700">{{ $label }}</dt>
                            <div class="h-2 overflow-hidden rounded-sm bg-zinc-100" aria-hidden="true"><div @class(['h-full rounded-sm', 'bg-ink' => $key === '0_15', 'bg-amber-600' => $key === '16_30', 'bg-red-600' => in_array($key, ['31_60', '60_plus'])]) style="width: {{ round($d['buckets'][$key] * 100 / $maxDue, 1) }}%"></div></div>
                            <dd class="min-w-24 whitespace-nowrap text-right text-[15px] font-semibold tabular-nums">{{ Money::format($d['buckets'][$key]) }}</dd>
                        </a>
                    @endforeach
                </dl>
                <p class="mt-4 border-t border-zinc-200 pt-3 text-sm font-medium text-zinc-700">{{ Money::format($d['total_paise']) }} from {{ $d['customers'] }} {{ $d['customers'] === 1 ? 'customer' : 'customers' }}</p>
            @endif
        </section>

        {{-- Expenses --}}
        <section class="card p-0 sm:p-0 lg:col-span-7">
            <div class="flex items-baseline justify-between border-b border-zinc-200 px-4 py-3 sm:px-5">
                <h2 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Expenses · today {{ Money::format($home['expenses']['total_paise']) }}</h2>
                <a href="{{ route('finance.expenses.index') }}" class="text-sm font-semibold underline decoration-brand-400 decoration-2 underline-offset-4">All expenses</a>
            </div>
            @forelse ($recentExpenses as $e)
                <div class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3 last:border-0 sm:px-5">
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[15px] font-semibold">{{ $e->category->name }}{{ $e->paid_to ? ' · '.$e->paid_to : '' }}</div>
                        <div class="text-[13px] font-medium text-zinc-600">{{ $e->date->format('D, j M') }} · {{ \App\Models\Expense::MODES[$e->mode] ?? $e->mode }}</div>
                    </div>
                    <div class="text-[15px] font-semibold tabular-nums">{{ Money::format($e->amount_paise) }}</div>
                </div>
            @empty
                <x-finance.empty icon="receipt" title="No expenses yet. Add rent, tea, transport and other spends so the cash close adds up." :href="$canManage ? route('finance.expenses.create') : null" :action="$canManage ? '+ Add expense' : null" />
            @endforelse
        </section>

        {{-- Recent closes --}}
        <section class="card p-0 sm:p-0 lg:col-span-5">
            <div class="flex items-baseline justify-between border-b border-zinc-200 px-4 py-3 sm:px-5">
                <h2 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Last cash closes</h2>
                <a href="{{ route('finance.cash-closes.index') }}" class="text-sm font-semibold underline decoration-brand-400 decoration-2 underline-offset-4">History</a>
            </div>
            @forelse ($recentCloses as $cc)
                <a href="{{ route('finance.cash-close', ['date' => $cc->date->toDateString()]) }}" class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3 last:border-0 hover:bg-zinc-50 sm:px-5">
                    <div class="flex-1">
                        <div class="text-[15px] font-semibold">{{ $cc->date->format('D, j M') }}</div>
                        <div class="text-[13px] font-medium text-zinc-600">Counted {{ Money::format($cc->counted_cash_paise) }}</div>
                    </div>
                    <span @class([
                        'rounded-full border px-2.5 py-0.5 text-xs font-semibold tabular-nums',
                        'border-green-200 bg-green-50 text-green-700' => $cc->difference_paise === 0,
                        'border-red-200 bg-red-50 text-red-600' => $cc->difference_paise < 0,
                        'border-amber-200 bg-amber-50 text-amber-700' => $cc->difference_paise > 0,
                    ])>{{ $cc->difference_paise === 0 ? 'Matched' : CashCloseService::signedMoney($cc->difference_paise) }}</span>
                </a>
            @empty
                <x-finance.empty icon="wallet" title="No cash closes yet. Do the first one at the end of today." />
            @endforelse
        </section>
    </div>
</x-layouts.app>
