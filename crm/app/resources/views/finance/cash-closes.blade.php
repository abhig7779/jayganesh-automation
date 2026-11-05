@php use App\Support\Money; use App\Services\Finance\CashCloseService; @endphp
<x-layouts.app title="Cash close history">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Cash close history</h1>
            <p class="text-sm font-medium text-zinc-600">Every day's drawer count. Red means the cash didn't match.</p>
        </div>
        <a href="{{ route('finance.cash-close') }}" class="btn-accent min-h-12">Today's close</a>
    </x-slot:header>

    <x-finance.tabs />

    <section class="card overflow-hidden p-0 sm:p-0">
        @if ($closes->isEmpty())
            <x-finance.empty icon="wallet" title="No cash closes yet. Do the first one at the end of today." :href="route('finance.cash-close')" action="Open cash close" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-max text-left text-[13px] font-medium">
                    <thead class="bg-zinc-100 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">
                        <tr>
                            <th class="px-5 py-2.5">Date</th>
                            <th class="px-4 py-2.5 text-right">Expected</th>
                            <th class="px-4 py-2.5 text-right">Counted</th>
                            <th class="px-4 py-2.5 text-right">Difference</th>
                            <th class="px-4 py-2.5">Note</th>
                            <th class="px-4 py-2.5">Closed by</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @foreach ($closes as $c)
                            <tr class="hover:bg-zinc-50">
                                <td class="whitespace-nowrap px-5 py-2.5"><a class="font-semibold underline decoration-brand-400 decoration-2 underline-offset-4" href="{{ route('finance.cash-close', ['date' => $c->date->toDateString()]) }}">{{ $c->date->format('D, j M Y') }}</a></td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ Money::format($c->expected_cash_paise) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ Money::format($c->counted_cash_paise) }}</td>
                                <td @class(['px-4 py-2.5 text-right font-semibold tabular-nums', 'text-green-700' => $c->difference_paise === 0, 'text-red-600' => $c->difference_paise !== 0])>{{ $c->difference_paise === 0 ? 'Matched' : CashCloseService::signedMoney($c->difference_paise) }}</td>
                                <td class="max-w-72 truncate px-4 py-2.5 text-zinc-700">{{ $c->note ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-zinc-700">{{ $c->closer?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($closes->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3">{{ $closes->links() }}</div>
            @endif
        @endif
    </section>
</x-layouts.app>
