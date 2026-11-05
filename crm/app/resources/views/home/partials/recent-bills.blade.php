{{-- Recent bills: a dense table on PC, list rows on the phone. $recentBills = null until billing merges. --}}
@php $billsIndex = \Illuminate\Support\Facades\Route::has('bills.index') ? route('bills.index') : null; @endphp
<x-ui.panel title="Recent bills" :subtitle="$recentBills === null ? null : 'Latest 5, with WhatsApp status'" flush>
    <x-slot:aside>
        @if ($recentBills === null)
            <x-ui.status-badge status="soon" />
        @elseif ($billsIndex)
            <a href="{{ $billsIndex }}" class="text-[13px] font-semibold text-ink hover:underline">View all bills</a>
        @endif
    </x-slot:aside>
    @if ($recentBills === null)
        <div class="px-4 py-4 lg:px-5">
            @include('home.partials.soon', ['icon' => 'receipt', 'text' => 'Switches on with Billing. The last five bills show here with their WhatsApp tick: sent, delivered, read or failed with Resend.'])
        </div>
    @elseif (empty($recentBills))
        <x-ui.empty-state icon="receipt" text="No bills yet today. Press F2 or tap New bill to make the first one." compact />
    @else
        <table class="table-pc hidden lg:table">
            <thead><tr><th>Bill no.</th><th>Customer</th><th>Time</th><th class="text-right">Amount</th><th>Status</th><th>WhatsApp</th></tr></thead>
            <tbody>
                @foreach ($recentBills as $b)
                    <tr @if ($b['url']) data-href="{{ $b['url'] }}" @endif>
                        <td class="tnum font-semibold text-ink">{{ $b['number'] }}</td>
                        <td>{{ $b['name'] }}</td>
                        <td class="tnum text-muted">{{ $b['time'] }}</td>
                        <td class="text-right"><x-ui.money :paise="$b['paise']" size="sm" class="text-ink" /></td>
                        <td>@if ($b['status']) <x-ui.status-badge :status="$b['status']" /> @endif</td>
                        <td>@include('home.partials.tick', ['state' => $b['whatsapp']])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="divide-y divide-line lg:hidden">
            @foreach ($recentBills as $b)
                <x-ui.list-row :href="$b['url']" :avatar="$b['name']" :title="$b['name']" :subtitle="$b['number'].' · '.$b['time']">
                    <x-slot:trailing>
                        <x-ui.money :paise="$b['paise']" />
                        <div class="mt-0.5">@include('home.partials.tick', ['state' => $b['whatsapp']])</div>
                    </x-slot:trailing>
                </x-ui.list-row>
            @endforeach
        </div>
    @endif
</x-ui.panel>
