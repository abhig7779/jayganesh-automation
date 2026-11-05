@php
    use App\Support\Money;
    use App\Services\Finance\SalesQueries;
    $badge = fn ($bucket) => match ($bucket) {
        '0_15' => 'border-zinc-300 bg-white text-zinc-700',
        '16_30' => 'border-amber-200 bg-amber-50 text-amber-700',
        default => 'border-red-200 bg-red-50 text-red-600',
    };
@endphp
<x-layouts.app title="Dues">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Dues</h1>
            <p class="text-sm font-medium text-zinc-600">Money customers still owe, oldest first.</p>
        </div>
    </x-slot:header>

    <x-finance.tabs />

    @if (! $installed)
        <section class="card">
            <x-finance.empty icon="receipt" title="Bills module not installed yet.">Dues come from unpaid bills. This page fills in as soon as bills are being made.</x-finance.empty>
        </section>
    @else
        {{-- Ageing buckets (tap to filter) --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <a href="{{ route('finance.dues') }}" @class(['col-span-2 rounded-xl border bg-white px-4 py-3 hover:bg-zinc-50 lg:col-span-1', 'border-ink ring-1 ring-ink' => ! $bucket, 'border-zinc-200' => $bucket])>
                <div class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">All dues</div>
                <div class="mt-1 text-2xl font-bold tabular-nums {{ $ageing['total_paise'] ? 'text-red-600' : '' }}">{{ Money::format($ageing['total_paise']) }}</div>
                <div class="text-xs font-medium text-zinc-600">{{ $ageing['customers'] }} {{ $ageing['customers'] === 1 ? 'customer' : 'customers' }}</div>
            </a>
            @foreach (SalesQueries::AGEING as $key => $label)
                <a href="{{ route('finance.dues', ['age' => $key]) }}" @class(['rounded-xl border bg-white px-4 py-3 hover:bg-zinc-50', 'border-ink ring-1 ring-ink' => $bucket === $key, 'border-zinc-200' => $bucket !== $key])>
                    <div class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">{{ $label }}</div>
                    <div class="mt-1 text-xl font-bold tabular-nums">{{ Money::format($ageing['buckets'][$key]) }}</div>
                    <div class="text-xs font-medium text-zinc-600">{{ $ageing['counts'][$key] }} {{ $ageing['counts'][$key] === 1 ? 'bill' : 'bills' }}</div>
                </a>
            @endforeach
        </div>

        <section class="card mt-5 overflow-hidden p-0 sm:p-0">
            @if ($customers->isEmpty())
                <x-finance.empty icon="check" :title="$bucket ? 'No dues in this age group.' : 'No dues. Every bill is fully paid.'" :href="$bucket ? route('finance.dues') : null" :action="$bucket ? 'Show all dues' : null" />
            @else
                <ul class="divide-y divide-zinc-200">
                    @foreach ($customers as $c)
                        <li class="px-4 py-4 sm:px-5" x-data="{ open: false }">
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                                <button type="button" @click="open = !open" class="flex min-w-0 flex-1 items-center gap-3 text-left" :aria-expanded="open">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-zinc-100 text-[13px] font-bold text-ink">{{ strtoupper(mb_substr($c['name'], 0, 1)) }}{{ strtoupper(mb_substr(strstr($c['name'], ' ') ?: '', 1, 1)) }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate text-[15px] font-semibold text-ink">{{ $c['name'] }}</span>
                                        <span class="block text-[13px] font-medium text-zinc-600">{{ \App\Support\Phone::pretty($c['mobile']) }} · {{ count($c['bills']) }} {{ count($c['bills']) === 1 ? 'bill' : 'bills' }}</span>
                                    </span>
                                </button>
                                <div class="text-right">
                                    <div class="text-[17px] font-bold tabular-nums text-red-600">{{ Money::format($c['due_paise']) }}</div>
                                    <span class="inline-block rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $badge($c['bucket']) }}">{{ $c['oldest_days'] }} {{ $c['oldest_days'] === 1 ? 'day' : 'days' }} old</span>
                                </div>
                                <div class="flex w-full gap-2 sm:w-auto">
                                    @if ($c['remind_url'])
                                        <a href="{{ $c['remind_url'] }}" target="_blank" rel="noopener" class="btn-ghost min-h-11 flex-1 sm:flex-none"><x-finance.icon name="chat" /> Remind on WhatsApp</a>
                                    @endif
                                    @if ($c['collect_url'])
                                        <a href="{{ $c['collect_url'] }}" class="btn-ghost min-h-11 flex-1 sm:flex-none">Collect</a>
                                    @endif
                                </div>
                            </div>
                            <div x-show="open" x-cloak class="mt-3 overflow-x-auto rounded-lg border border-zinc-200">
                                <table class="w-full min-w-max text-left text-[13px] font-medium">
                                    <thead class="bg-zinc-100 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">
                                        <tr><th class="px-3 py-2">Bill</th><th class="px-3 py-2">Date</th><th class="px-3 py-2 text-right">Bill total</th><th class="px-3 py-2 text-right">Paid</th><th class="px-3 py-2 text-right">Due</th><th class="px-3 py-2 text-right">Age</th></tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200">
                                        @foreach ($c['bills'] as $b)
                                            <tr>
                                                <td class="px-3 py-2 font-semibold">
                                                    @if (Route::has('bills.show'))
                                                        <a href="{{ route('bills.show', $b['invoice_id']) }}" class="underline decoration-brand-400 decoration-2 underline-offset-4">{{ $b['number'] }}</a>
                                                    @else
                                                        {{ $b['number'] }}
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-zinc-700">{{ \Carbon\Carbon::parse($b['date'])->format('j M Y') }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums">{{ Money::format($b['total_paise']) }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums">{{ Money::format($b['paid_paise']) }}</td>
                                                <td class="px-3 py-2 text-right font-semibold tabular-nums text-red-600">{{ Money::format($b['due_paise']) }}</td>
                                                <td class="px-3 py-2 text-right tabular-nums">{{ $b['age_days'] }} d</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</x-layouts.app>
