{{-- "My today" for the counter: my sales, bills, cash I collected, by mode. New customers are real now. --}}
<x-ui.panel title="My today" subtitle="Your counter, since opening">
    @if ($myToday['billing'])
        @php [$words, $trend] = $home->compareWords($myToday['salesPaise'], $myToday['lastWeekPaise']); @endphp
        <div class="flex items-end justify-between gap-3">
            <div>
                <div class="section-label">My sales</div>
                <x-ui.money :paise="$myToday['salesPaise']" size="xl" class="mt-1 block" />
            </div>
            <span class="badge badge-muted mb-1.5">{{ $myToday['bills'] }} {{ str('bill')->plural($myToday['bills']) }}</span>
        </div>
        <div class="mt-1 flex items-center gap-1 text-xs font-medium {{ $trend === 'up' ? 'text-ok' : ($trend === 'down' ? 'text-bad' : 'text-muted') }}">
            @if ($trend) <x-icon :name="'trend-'.$trend" class="size-3.5" stroke="2" /> @endif {{ $words }}
        </div>
        <div class="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-line bg-line">
            @foreach (['cash' => 'Cash in drawer', 'upi' => 'UPI / QR', 'card' => 'Card', 'due' => 'Old dues collected'] as $mode => $label)
                <div class="bg-surface px-3 py-2.5">
                    <div class="text-xs font-medium text-muted">{{ $label }}</div>
                    <x-ui.money :paise="$myToday['modes'][$mode] ?? 0" size="md" @class(['text-ok' => $mode === 'due' && ($myToday['modes'][$mode] ?? 0) > 0]) />
                </div>
            @endforeach
        </div>
    @else
        <div class="grid grid-cols-3 gap-3">
            <x-ui.stat label="My sales" soon />
            <x-ui.stat label="Bills" soon />
            <x-ui.stat label="New customers" :value="$myToday['newCustomers']" />
        </div>
        <p class="mt-3 border-t border-line pt-3 text-xs font-medium text-muted">Sales, bills and the cash you collected fill in once Billing is switched on.</p>
    @endif
</x-ui.panel>
