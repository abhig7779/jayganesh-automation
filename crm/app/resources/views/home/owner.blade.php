{{--
    Home: Owner, and Admin (= Owner without margins and without Settings; $showMargins is false
    for Admin). UX-FLOWS §2 Owner, DESIGN §7.
--}}
<x-layouts.app title="Home">
    @include('home.partials.greeting')
    @include('home.partials.tiles')

    {{-- Today's business strip --}}
    <section class="mb-5 rounded-xl border border-line bg-surface" aria-labelledby="biz-title">
        <div class="flex items-center justify-between px-4 pt-3.5 lg:px-5">
            <h2 id="biz-title" class="text-[15px] font-semibold">Today's business</h2>
            @unless ($business['billing']) <span class="text-xs font-medium text-muted">Sales numbers switch on with Billing</span> @endunless
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-5 px-4 pt-3 pb-4 sm:grid-cols-3 lg:grid-cols-5 lg:divide-x lg:divide-line lg:px-0">
            @php $cell = 'lg:px-5'; @endphp
            @if ($business['billing'])
                @php
                    [$salesWords, $salesTrend] = $home->compareWords($business['salesPaise'], $business['lastWeekPaise']);
                    [$billWords, $billTrend] = $home->compareWords($business['bills'], $business['lastWeekBills'], money: false);
                    $modes = $business['modes'];
                @endphp
                <x-ui.stat :class="$cell" label="Sales" :paise="$business['salesPaise']" :hint="$salesWords" :trend="$salesTrend" />
                <x-ui.stat :class="$cell" label="Bills" :value="$business['bills']" :hint="$billWords" :trend="$billTrend" />
                <x-ui.stat :class="$cell" label="Cash · UPI · Card" :paise="($modes['cash'] ?? 0)" :hint="'UPI '.\App\Support\Money::format($modes['upi'] ?? 0).' · Card '.\App\Support\Money::format($modes['card'] ?? 0)" />
                <x-ui.stat :class="$cell" label="Average bill" :paise="$business['avgPaise']" />
            @else
                <x-ui.stat :class="$cell" label="Sales" soon />
                <x-ui.stat :class="$cell" label="Bills" soon />
                <x-ui.stat :class="$cell" label="Cash · UPI · Card" soon />
                <x-ui.stat :class="$cell" label="Average bill" soon />
            @endif
            @php [$custWords, $custTrend] = $home->compareWords($business['newCustomers'], $business['newCustomersLastWeek'], money: false); @endphp
            <x-ui.stat :class="$cell" label="New customers" :value="$business['newCustomers']" :hint="$custWords" :trend="$custTrend"
                       :href="\Illuminate\Support\Facades\Route::has('customers.index') ? route('customers.index', ['added' => 'today']) : null" />
        </div>
    </section>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px] xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="min-w-0 space-y-5">
            {{-- Needs attention --}}
            <x-ui.panel title="Needs attention" flush>
                <x-slot:aside>@if (count($attention)) <span class="badge badge-bad">{{ count($attention) }}</span> @endif</x-slot:aside>
                @if (empty($attention))
                    <div class="flex items-center gap-2 px-4 py-4 text-[13px] font-medium text-muted lg:px-5">
                        <x-icon name="check" class="size-4 text-ok" stroke="2" /> Nothing needs you right now.
                    </div>
                @else
                    <div class="divide-y divide-line">
                        @foreach ($attention as $a)
                            <x-ui.list-row :href="$a['url']" :icon="$a['icon']" :tone="$a['tone']" :title="$a['title']" :subtitle="$a['detail']" />
                        @endforeach
                    </div>
                @endif
                @if ($pending = $home->pendingWatches())
                    <p class="border-t border-line px-4 py-2.5 text-xs font-medium text-muted lg:px-5">Also watched here once switched on: {{ implode(', ', $pending) }}.</p>
                @endif
            </x-ui.panel>

            @include('home.partials.followups')

            {{-- Staff today --}}
            <x-ui.panel title="Staff today" flush>
                <x-slot:aside>@if ($staffToday === null) <x-ui.status-badge status="soon" /> @endif</x-slot:aside>
                @if ($staffToday === null)
                    <div class="px-4 py-4 lg:px-5">@include('home.partials.soon', ['icon' => 'users', 'text' => 'Each person’s bills and sales today, side by side, once Billing is switched on.'])</div>
                @elseif (empty($staffToday))
                    <x-ui.empty-state icon="receipt" text="No bills yet today." compact />
                @else
                    <table class="table-pc">
                        <thead><tr><th>Staff</th><th class="text-right">Bills</th><th class="text-right">Sales</th><th class="w-1/3"></th></tr></thead>
                        <tbody>
                            @foreach ($staffToday as $s)
                                <tr>
                                    <td class="font-semibold text-ink">{{ $s['name'] }}</td>
                                    <td class="tnum text-right">{{ $s['bills'] }}</td>
                                    <td class="text-right"><x-ui.money :paise="$s['paise']" size="sm" class="text-ink" /></td>
                                    <td><div class="h-1.5 rounded-full bg-sunken"><div class="h-1.5 rounded-full bg-ink" style="width: {{ $s['share'] }}%"></div></div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-ui.panel>

            @include('home.partials.recent-bills')
        </div>

        <aside class="min-w-0 space-y-5">
            {{-- This month --}}
            <x-ui.panel title="This month" :subtitle="$today->format('F Y')">
                <div class="grid grid-cols-2 gap-4">
                    @if ($month['billing'])
                        <x-ui.stat label="Sales" :paise="$month['salesPaise']" />
                        <x-ui.stat label="Bills" :value="$month['bills']" />
                        <x-ui.stat label="Dues" :paise="$month['duePaise']" :tone="$month['duePaise'] > 0 ? 'bad' : null" />
                    @else
                        <x-ui.stat label="Sales" soon />
                        <x-ui.stat label="Bills" soon />
                        <x-ui.stat label="Dues" soon />
                    @endif
                    <x-ui.stat label="New customers" :value="$month['newCustomers']" />
                </div>
                @if ($showMargins)
                    <p class="mt-4 border-t border-line pt-3 text-xs font-medium text-muted">Margin and profit show here for you only (not for Admin) once purchases and billing are in.</p>
                @endif
            </x-ui.panel>
            @include('home.partials.side-panels')
        </aside>
    </div>
</x-layouts.app>
