@php
    use App\Support\Money;
    use App\Services\Finance\CashCloseService;
    $day = \Carbon\Carbon::parse($date);
    $canManage = auth()->user()->can('finance.manage');
    $showForm = $canManage && (! $close || $redo || $errors->any());
    $stale = $close && $close->expected_cash_paise !== $expected['expected_cash_paise'];
    $m = $summary['modes'];
    $prev = $day->copy()->subDay()->toDateString();
    $next = $isToday ? null : $day->copy()->addDay()->toDateString();
@endphp
<x-layouts.app title="Cash close">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Cash close</h1>
            <p class="text-sm font-medium text-zinc-600">{{ $isToday ? 'Today, ' : '' }}{{ $day->format('l, j M Y') }}</p>
        </div>
        <div class="flex items-center gap-1">
            <a href="{{ route('finance.cash-close', ['date' => $prev]) }}" class="btn-ghost min-h-10 px-3" aria-label="Previous day"><x-finance.icon name="back" class="size-4" /> {{ \Carbon\Carbon::parse($prev)->format('j M') }}</a>
            @if ($next)
                <a href="{{ route('finance.cash-close', ['date' => $next]) }}" class="btn-ghost min-h-10 px-3">{{ \Carbon\Carbon::parse($next)->format('j M') }} <x-finance.icon name="back" class="size-4 rotate-180" /></a>
            @endif
        </div>
    </x-slot:header>

    <x-finance.tabs />

    @unless ($billingInstalled)
        <p class="mb-5 rounded-xl border border-zinc-200 bg-zinc-100 px-4 py-3 text-sm text-zinc-700">Bills module not installed yet, so cash from bills isn't counted. Only the opening float and cash expenses are used.</p>
    @endunless

    <div class="grid grid-cols-1 gap-5 *:min-w-0 lg:grid-cols-12">
        {{-- Expected cash --}}
        <section class="card lg:col-span-5">
            <h2 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Cash that should be in the drawer</h2>
            <dl class="mt-4 space-y-3 text-[15px]">
                <div class="flex justify-between gap-4"><dt class="text-zinc-700">Opening float</dt><dd class="font-semibold tabular-nums">{{ Money::format($expected['opening_float_paise']) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-700">+ Cash received <span class="block text-xs text-zinc-600">bills and dues paid in cash</span></dt><dd class="font-semibold tabular-nums">{{ Money::format($expected['cash_in_paise']) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-zinc-700">− Cash expenses</dt><dd class="font-semibold tabular-nums">{{ $expected['cash_out_paise'] ? '−'.Money::format($expected['cash_out_paise']) : Money::format(0) }}</dd></div>
                <div class="flex items-baseline justify-between gap-4 border-t-2 border-ink pt-3">
                    <dt class="font-semibold">Expected cash</dt>
                    <dd class="text-3xl font-bold tabular-nums tracking-tight">{{ Money::format($expected['expected_cash_paise']) }}</dd>
                </div>
            </dl>
        </section>

        {{-- Count / result --}}
        <section class="card lg:col-span-7">
            @if ($close && ! $showForm)
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <span class="inline-flex items-center gap-1 rounded-full border border-green-200 bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700"><x-finance.icon name="check" class="size-3.5" /> Closed</span>
                        <p class="mt-2 text-sm text-zinc-700">by {{ $close->closer?->name ?? 'someone' }} at {{ $close->updated_at->timezone('Asia/Kolkata')->format('g:i A, j M') }}</p>
                    </div>
                    @if ($canManage)
                        <a href="{{ route('finance.cash-close', ['date' => $date, 'redo' => 1]) }}" class="btn-ghost min-h-10">Redo close</a>
                    @endif
                </div>
                <dl class="mt-5 grid grid-cols-2 gap-3">
                    <x-finance.stat label="Counted" :value="Money::format($close->counted_cash_paise)" />
                    <x-finance.stat label="Difference" :value="$close->difference_paise === 0 ? 'Matched' : CashCloseService::signedMoney($close->difference_paise)" :tone="$close->difference_paise === 0 ? 'ok' : 'bad'" :hint="$close->difference_paise < 0 ? 'Cash short' : ($close->difference_paise > 0 ? 'Cash over' : null)" />
                </dl>
                @if ($close->note)
                    <p class="mt-3 rounded-lg bg-zinc-100 px-3 py-2 text-sm text-zinc-700"><span class="font-semibold">Note:</span> {{ $close->note }}</p>
                @endif
                @if ($stale)
                    <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700">Something changed after this close: expected cash is now {{ Money::format($expected['expected_cash_paise']) }} (was {{ Money::format($close->expected_cash_paise) }}). Redo the close to update it.</p>
                @endif

                <div class="mt-6 border-t border-zinc-200 pt-5">
                    <h3 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Summary for the owner</h3>
                    <p class="mt-2 whitespace-pre-line rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-3 text-sm leading-6 text-ink">{{ $waText }}</p>
                    <a href="{{ $waLink }}" target="_blank" rel="noopener" class="btn-accent mt-3 min-h-12 w-full sm:w-auto"><x-finance.icon name="chat" /> Send to owner on WhatsApp</a>
                </div>
            @elseif ($showForm)
                <form method="POST" action="{{ route('finance.cash-close.store') }}" class="space-y-5"
                      x-data="cashClose({{ $expected['expected_cash_paise'] }}, @js(old('counted', $redo && $close ? Money::format($close->counted_cash_paise, false, false) : '')))">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date }}">
                    <div>
                        <label class="label" for="counted">Cash you counted in the drawer</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xl font-semibold text-zinc-600">₹</span>
                            <input id="counted" name="counted" type="text" inputmode="decimal" autocomplete="off" required autofocus
                                   x-model="counted" placeholder="0"
                                   class="input min-h-16 pl-9 text-right text-3xl font-bold tabular-nums sm:text-3xl @error('counted') border-red-600 @enderror">
                        </div>
                        @error('counted') <p class="error">{{ $message }}</p> @enderror
                    </div>

                    <div x-show="entered" x-cloak class="rounded-xl border px-4 py-3"
                         :class="diff === 0 ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-600'">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-sm font-semibold" x-text="diff === 0 ? 'Cash matches' : (diff < 0 ? 'Cash is short' : 'Cash is over')"></span>
                            <span class="text-2xl font-bold tabular-nums" x-text="signed(diff)"></span>
                        </div>
                    </div>

                    <div>
                        <label class="label" for="note">Note <span class="font-normal text-zinc-500" x-text="entered && diff !== 0 ? '(needed: say why the cash is different)' : '(only needed if the cash is different)'">(only needed if the cash is different)</span></label>
                        <textarea id="note" name="note" rows="2" maxlength="500" class="input py-2 @error('note') border-red-600 @enderror"
                                  :required="entered && diff !== 0" placeholder="e.g. ₹200 given to Sunil for fuel, not entered">{{ old('note', $redo && $close ? $close->note : '') }}</textarea>
                        @error('note') <p class="error">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="btn-accent min-h-14 w-full text-[17px] lg:min-h-12">Save cash close</button>
                    @if ($redo && $close)
                        <a href="{{ route('finance.cash-close', ['date' => $date]) }}" class="btn-ghost w-full">Keep the saved close</a>
                    @endif
                </form>
            @else
                <x-finance.empty icon="wallet" title="Not closed yet. Only Finance or the Owner can do the cash close." />
            @endif
        </section>

        {{-- Day summary --}}
        <section class="card lg:col-span-12">
            <h2 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">The day in numbers</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <x-finance.stat label="Sales" :value="Money::format($summary['sales_paise'])" :hint="$summary['bills'].' '.($summary['bills'] === 1 ? 'bill' : 'bills')" />
                <x-finance.stat label="Cash" :value="Money::format($m['cash'])" />
                <x-finance.stat label="UPI" :value="Money::format($m['upi'])" />
                <x-finance.stat label="Card" :value="Money::format($m['card'])" :hint="$m['bank'] ? 'Bank '.Money::format($m['bank']) : null" />
                <x-finance.stat label="Dues collected" :value="Money::format($summary['dues_collected_paise'])" />
                <x-finance.stat label="Expenses" :value="Money::format($summary['expenses_paise'])" />
            </div>
        </section>
    </div>

    <script>
        function cashClose(expected, initial) {
            return {
                expected, counted: initial || '',
                get paise() {
                    const s = String(this.counted).replace(/[₹,\s]/g, '');
                    if (!/^\d+(\.\d{1,2})?$/.test(s)) return null;
                    const [w, f = ''] = s.split('.');
                    return parseInt(w, 10) * 100 + parseInt((f + '00').slice(0, 2), 10);
                },
                get entered() { return this.paise !== null; },
                get diff() { return this.entered ? this.paise - this.expected : 0; },
                signed(p) {
                    const abs = Math.abs(p), r = Math.floor(abs / 100), f = abs % 100;
                    let s = String(r);
                    if (s.length > 3) s = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + s.slice(-3);
                    const txt = '₹' + s + (f ? '.' + String(f).padStart(2, '0') : '');
                    return p > 0 ? '+' + txt : (p < 0 ? '−' + txt : '₹0');
                },
            };
        }
    </script>
</x-layouts.app>
