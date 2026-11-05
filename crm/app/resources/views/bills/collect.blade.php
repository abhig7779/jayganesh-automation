@php
    use App\Support\Money;
    use App\Support\Phone;
    $billRows = $bills->map(fn ($b) => ['id' => $b->id, 'due' => $b->due_paise])->values();
@endphp
<x-layouts.app title="Collect payment">
    <x-slot:header>
        <div>
            <h1 class="text-[28px] font-bold leading-tight lg:text-2xl">Collect payment</h1>
            <p class="text-sm text-zinc-600">Old dues. The money goes to the oldest bills first.</p>
        </div>
    </x-slot:header>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ $errors->first() }}</div>
    @endif

    @if (! $customer)
        <form method="GET" action="{{ route('payments.collect') }}" class="mb-5">
            <label class="flex h-[52px] max-w-xl items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 focus-within:border-ink">
                <x-bills.icon name="search" class="size-5 text-zinc-500" />
                <input name="q" value="{{ $q }}" autofocus placeholder="Mobile no. or name" class="min-w-0 flex-1 border-0 bg-transparent text-[17px] focus:outline-none" aria-label="Find customer">
                <button class="btn-primary !min-h-9">Find</button>
            </label>
        </form>

        @if ($q !== '')
            @if ($matches->isEmpty())
                <div class="rounded-xl border border-zinc-200 bg-white px-6 py-8 text-center text-[15px] text-zinc-700">No customer with a due matches “{{ $q }}”. Check the number, or open their bill.</div>
            @else
                <ul class="max-w-xl divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    @foreach ($matches as $c)
                        <li><a href="{{ route('payments.collect', ['customer' => $c->id]) }}" class="flex min-h-16 items-center justify-between px-4 py-3 hover:bg-zinc-50">
                            <span><span class="block font-semibold">{{ $c->name }}</span><span class="text-[13px] text-zinc-600">{{ Phone::pretty($c->mobile) }}</span></span>
                            <span class="font-semibold text-red-600 tabular-nums">{{ Money::format(\App\Services\Billing\PaymentService::customerDuePaise($c->id)) }}</span>
                        </a></li>
                    @endforeach
                </ul>
            @endif
        @else
            <h2 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Biggest dues</h2>
            @if ($topDues->isEmpty())
                <div class="max-w-xl rounded-xl border border-zinc-200 bg-white px-6 py-8 text-center">
                    <x-bills.icon name="check-circle" class="mx-auto size-8 text-green-700" />
                    <p class="mt-2 text-[15px] font-medium text-zinc-700">No dues. Every bill is paid.</p>
                </div>
            @else
                <ul class="max-w-xl divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    @foreach ($topDues as $row)
                        <li><a href="{{ route('payments.collect', ['customer' => $row->customer_id]) }}" class="flex min-h-16 items-center justify-between gap-3 px-4 py-3 hover:bg-zinc-50">
                            <span class="min-w-0"><span class="block truncate font-semibold">{{ $row->customer?->name }}</span>
                                <span class="text-[13px] text-zinc-600">{{ $row->bills }} bill{{ $row->bills > 1 ? 's' : '' }} · oldest {{ \Illuminate\Support\Carbon::parse($row->oldest)->diffInDays(now()) < 1 ? 'today' : (int) \Illuminate\Support\Carbon::parse($row->oldest)->diffInDays(now()).' days ago' }}</span></span>
                            <span class="font-semibold text-red-600 tabular-nums">{{ Money::format((int) $row->due) }}</span>
                        </a></li>
                    @endforeach
                </ul>
            @endif
        @endif
    @else
        <form method="POST" action="{{ route('payments.collect.store') }}" class="max-w-xl pb-44 lg:pb-0"
              @submit="busy = true"
              x-data="{
                busy: false,
                bills: @js($billRows),
                ticked: @js($bills->pluck('id')->values()),
                amount: @js((string) old('amount', $totalDue % 100 ? number_format($totalDue / 100, 2, '.', '') : intdiv($totalDue, 100))),
                mode: @js((string) old('mode', 'cash')),
                money(p) { const r = Math.floor(Math.abs(p) / 100), f = Math.abs(p) % 100; return '₹' + r.toLocaleString('en-IN') + (f ? '.' + String(f).padStart(2, '0') : ''); },
                get paise() { const s = String(this.amount).replace(/[,₹\s]/g, ''); if (!/^\d+(\.\d{1,2})?$/.test(s)) return 0; const [w, f = ''] = s.split('.'); return parseInt(w, 10) * 100 + parseInt((f + '00').slice(0, 2), 10); },
                get dueTicked() { return this.bills.filter(b => this.ticked.includes(b.id)).reduce((s, b) => s + b.due, 0); },
                get split() { let left = this.paise; const out = {}; for (const b of this.bills) { if (!this.ticked.includes(b.id)) continue; const t = Math.min(left, b.due); out[b.id] = t; left -= t; } return out; },
                get balanceAfter() { return {{ $totalDue }} - Math.min(this.paise, this.dueTicked); },
                get over() { return this.paise > this.dueTicked; },
              }">
            @csrf
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
            {{-- One key per screen: a double click or a resend returns the first payment (audit M4). --}}
            <input type="hidden" name="client_key" value="{{ old('client_key', \App\Support\ClientKey::make()) }}">
            <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white px-4 py-3 text-[15px]">
                <span><span class="font-semibold">{{ $customer->name }}</span> <span class="text-zinc-600">· {{ Phone::pretty($customer->mobile) }}</span></span>
                <a href="{{ route('payments.collect') }}" class="text-[13px] font-semibold underline">Change</a>
            </div>

            @if ($bills->isEmpty())
                <div class="mt-4 rounded-xl border border-zinc-200 bg-white px-6 py-8 text-center">
                    <x-bills.icon name="check-circle" class="mx-auto size-8 text-green-700" />
                    <p class="mt-2 text-[15px] font-medium text-zinc-700">{{ $customer->name }} has no dues.</p>
                </div>
            @else
                <p class="mt-5 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Total due</p>
                <p class="text-[28px] font-bold text-red-600 tabular-nums">{{ Money::format($totalDue) }}</p>

                <p class="mt-4 mb-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Unpaid bills, oldest first</p>
                <ul class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    @foreach ($bills as $b)
                        <li>
                            <label class="flex min-h-16 cursor-pointer items-center gap-3 px-4 py-3">
                                <input type="checkbox" name="invoices[]" value="{{ $b->id }}" x-model.number="ticked" class="size-5 accent-ink">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold">{{ $b->number }}</span>
                                    <span class="text-[13px] text-zinc-600">{{ $b->created_at->timezone('Asia/Kolkata')->format('j M') }} · {{ (int) $b->created_at->diffInDays(now()) }} days · bill {{ Money::format($b->total_paise) }}</span>
                                </span>
                                <span class="text-right">
                                    <span class="block font-semibold tabular-nums">{{ Money::format($b->due_paise) }}</span>
                                    <span class="text-xs font-semibold text-green-700" x-show="split[{{ $b->id }}] > 0" x-text="'gets ' + money(split[{{ $b->id }}] || 0)"></span>
                                </span>
                            </label>
                        </li>
                    @endforeach
                </ul>

                <label class="mt-5 block text-[15px] font-semibold" for="amount">Amount received</label>
                <div class="mt-2 flex h-14 items-center rounded-lg border border-zinc-300 bg-white focus-within:border-ink focus-within:ring-2 focus-within:ring-ink/80" :class="over ? '!border-red-600' : ''">
                    <span class="pl-4 text-xl font-semibold text-zinc-600">₹</span>
                    <input id="amount" name="amount" x-model="amount" inputmode="decimal" autocomplete="off" class="h-full min-w-0 flex-1 border-0 bg-transparent px-2 text-right text-[22px] font-bold tabular-nums focus:outline-none">
                </div>
                <p class="mt-1 text-[13px] font-medium text-red-600" x-show="over">That is more than the due on the ticked bills (<span x-text="money(dueTicked)"></span>).</p>

                <p class="mt-4 mb-2 text-[15px] font-semibold">Paid by</p>
                <div class="grid grid-cols-4 gap-1 rounded-lg bg-zinc-100 p-1">
                    @foreach (\App\Models\Payment::MODE_LABELS as $v => $l)
                        <label class="flex h-11 cursor-pointer items-center justify-center rounded-md text-sm font-semibold" :class="mode === '{{ $v }}' ? 'bg-ink text-white' : 'text-zinc-600'">
                            <input type="radio" name="mode" value="{{ $v }}" x-model="mode" class="sr-only">{{ $l }}
                        </label>
                    @endforeach
                </div>
                <div x-show="mode === 'upi'" class="mt-3 flex items-center gap-3 rounded-lg border border-zinc-200 bg-white p-3">
                    @if ($upi)
                        <img :src="'{{ route('bills.upi-qr') }}?size=200&amount_paise=' + paise" alt="UPI QR" class="size-28">
                        <p class="text-[13px] text-zinc-700">Scan to pay <span class="font-bold" x-text="money(paise)"></span><br>{{ $upi }}</p>
                    @else
                        <p class="text-[13px] text-amber-700">Add the shop’s UPI ID in Settings to show a QR.</p>
                    @endif
                </div>
                <input name="reference" value="{{ old('reference') }}" x-show="mode !== 'cash'" placeholder="Reference no. (optional)" class="input mt-3">

                <p class="mt-4 text-[13px] text-zinc-700">Applied to the oldest ticked bills first. Balance after: <span class="font-semibold" :class="balanceAfter > 0 ? 'text-red-600' : 'text-green-700'" x-text="money(balanceAfter)"></span></p>

                <div class="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 border-t border-zinc-200 bg-white px-4 pt-3 pb-3 lg:static lg:mt-5 lg:border-0 lg:bg-transparent lg:p-0">
                    <button class="flex h-14 w-full items-center justify-center rounded-lg bg-brand-400 text-[17px] font-semibold text-ink hover:bg-[#F2C800] disabled:opacity-50" :disabled="busy || paise <= 0 || over || !ticked.length">
                        <span x-text="'Collect ' + money(paise) + ' & send receipt'"></span>
                    </button>
                </div>
            @endif
        </form>
    @endif
</x-layouts.app>
