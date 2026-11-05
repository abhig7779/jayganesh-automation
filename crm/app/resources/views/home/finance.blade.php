{{-- Home: Finance (UX-FLOWS §2 Finance). Money today (finance module), collections by mode, dues ageing, recent bills. --}}
<x-layouts.app title="Home">
    @include('home.partials.greeting')
    @include('home.partials.tiles')

    <div class="grid gap-5 lg:grid-cols-2">
        @if ($finance)
            @include('home.partials.finance-today')
        @endif

        <x-ui.panel title="Today's collections" subtitle="By payment mode">
            <x-slot:aside>@if ($collections === null) <x-ui.status-badge status="soon" /> @endif</x-slot:aside>
            @if ($collections === null)
                @include('home.partials.soon', ['icon' => 'cash', 'text' => 'Cash, UPI and card received today, plus old dues recovered, once Billing is switched on.'])
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'due' => 'Old dues'] as $mode => $label)
                        <x-ui.stat :label="$label" :paise="$collections[$mode] ?? 0" />
                    @endforeach
                </div>
            @endif
        </x-ui.panel>

        <x-ui.panel title="Dues ageing" subtitle="Unpaid bills by age">
            <x-slot:aside>@if ($duesAgeing === null) <x-ui.status-badge status="soon" /> @endif</x-slot:aside>
            @if ($duesAgeing === null)
                @include('home.partials.soon', ['icon' => 'clock', 'text' => 'Dues split into 0–15, 15–30 and 30+ days, so the oldest get chased first.'])
            @else
                <div class="grid grid-cols-3 gap-4">
                    @foreach ($duesAgeing as $label => $paise)
                        <x-ui.stat :label="$label" :paise="$paise" :tone="$loop->last && $paise > 0 ? 'bad' : null" />
                    @endforeach
                </div>
            @endif
        </x-ui.panel>

        <x-ui.panel title="Payables to suppliers">
            <x-slot:aside><x-ui.status-badge status="soon" /></x-slot:aside>
            @include('home.partials.soon', ['icon' => 'clipboard', 'text' => 'What the shop owes each supplier, once Purchases is switched on.'])
        </x-ui.panel>

        <x-ui.panel title="Customers">
            <div class="grid grid-cols-2 gap-4">
                <x-ui.stat label="New today" :value="$newCustomers" />
                <x-ui.stat label="On file" :value="\App\Models\Customer::query()->active()->count()" :href="route('customers.index')" />
            </div>
        </x-ui.panel>

        <div class="lg:col-span-2">@include('home.partials.recent-bills')</div>
    </div>
</x-layouts.app>
