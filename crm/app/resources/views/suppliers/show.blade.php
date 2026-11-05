@php
    use App\Support\Money;
    use App\Support\Phone;
    use App\Models\SupplierPayment;
    $s = $supplier;
    $balText = fn (int $b) => $b > 0 ? Money::format($b) : ($b < 0 ? Money::format(-$b).' adv.' : '₹0');
    $payFor = (int) old('purchase_id', request('pay'));
    $whole = fn (int $p) => (int) round($p / 100) * 100; // summary tiles show whole rupees
@endphp
<x-layouts.app :title="$s->name">
    <x-ui.page-header :title="$s->name" :back="route('suppliers.index')" back-label="Suppliers"
        :subtitle="collect([$s->mobile ? Phone::pretty($s->mobile) : null, $s->gstin ? 'GSTIN '.$s->gstin : null, $s->address])->filter()->implode(' · ') ?: 'No contact details yet'">
        <x-slot:actions>
            @if ($s->mobile)
                <a href="tel:{{ $s->mobile }}" class="btn-ghost"><x-icon name="phone" class="size-5" /> Call</a>
            @endif
            <a href="{{ route('suppliers.edit', $s) }}" class="btn-ghost"><x-icon name="pencil" class="size-5" /> Edit</a>
            <a href="{{ route('purchases.create', ['supplier' => $s->id]) }}" class="btn-accent"><x-icon name="plus" class="size-5" stroke="2" /> New purchase</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card"><x-ui.stat label="Opening balance" :paise="$whole((int) $s->opening_balance_paise)" /></div>
        <div class="card"><x-ui.stat label="Bought" :paise="$whole($billed)" hint="Live bills, incl. GST" /></div>
        <div class="card"><x-ui.stat label="Paid" :paise="$whole($paid)" /></div>
        <div class="card">
            @if ($balance >= 0)
                <x-ui.stat label="We owe" :paise="$whole($balance)" :tone="$balance > 0 ? 'bad' : null" :hint="$balance > 0 ? $openBills->count().' open '.($openBills->count() === 1 ? 'bill' : 'bills') : 'All clear'" />
            @else
                <x-ui.stat label="Advance with them" :paise="$whole(-$balance)" hint="We paid more than billed" />
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 *:min-w-0 lg:grid-cols-12">
        <x-ui.panel title="Ledger" subtitle="Bills and payments, newest first, with the running balance" flush class="self-start lg:col-span-8">
            @if ($rows->isEmpty())
                <x-ui.empty-state icon="receipt" text="No bills or payments yet." compact>
                    <a href="{{ route('purchases.create', ['supplier' => $s->id]) }}" class="btn-ghost">+ New purchase</a>
                </x-ui.empty-state>
            @else
                <table class="table-pc hidden lg:table">
                    <thead><tr><th>Date</th><th>Entry</th><th class="text-right">Bill</th><th class="text-right">Paid</th><th class="text-right">Balance</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $r)
                            @php $m = $r['model']; @endphp
                            <tr @if ($r['kind'] === 'bill') data-href="{{ route('purchases.show', $m) }}" @endif @class(['opacity-55' => $r['void']])>
                                <td class="whitespace-nowrap">{{ $r['date']->format('j M Y') }}</td>
                                <td>
                                    @if ($r['kind'] === 'bill')
                                        <a href="{{ route('purchases.show', $m) }}" class="font-semibold text-ink hover:underline">Bill {{ $m->bill_no ?: $m->ref() }}</a>
                                        <span class="text-muted">· {{ $m->items_count }} {{ $m->items_count === 1 ? 'item' : 'items' }}</span>
                                        @if ($r['void']) <x-ui.status-badge status="cancelled" class="ml-1" /> @endif
                                    @else
                                        <span class="font-semibold text-ink">Payment · {{ SupplierPayment::MODES[$m->mode] ?? $m->mode }}</span>
                                        <span class="text-muted">{{ $m->reference ? '· '.$m->reference : '' }}{{ $m->note ? ' · '.$m->note : '' }}</span>
                                    @endif
                                </td>
                                <td @class(['tnum text-right', 'line-through' => $r['void']])>{{ $r['kind'] === 'bill' ? Money::format($m->total_paise) : '' }}</td>
                                <td class="tnum text-right text-ok">{{ $r['kind'] === 'payment' ? Money::format($m->amount_paise) : '' }}</td>
                                <td class="tnum text-right font-semibold text-ink">{{ $balText($r['balance']) }}</td>
                            </tr>
                        @endforeach
                        @if ($s->opening_balance_paise)
                            <tr><td></td><td class="text-muted">Opening balance</td><td></td><td></td><td class="tnum text-right font-semibold">{{ $balText((int) $s->opening_balance_paise) }}</td></tr>
                        @endif
                    </tbody>
                </table>
                <ul class="divide-y divide-line lg:hidden">
                    @foreach ($rows as $r)
                        @php $m = $r['model']; @endphp
                        <li @class(['flex items-center gap-3 px-4 py-3', 'opacity-55' => $r['void']])>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[15px] font-semibold">
                                    @if ($r['kind'] === 'bill') <a href="{{ route('purchases.show', $m) }}">Bill {{ $m->bill_no ?: $m->ref() }}</a> @else Payment · {{ SupplierPayment::MODES[$m->mode] ?? $m->mode }} @endif
                                </div>
                                <div class="text-[13px] text-muted">{{ $r['date']->format('j M Y') }} · balance {{ $balText($r['balance']) }}</div>
                            </div>
                            <div @class(['tnum text-right font-semibold', 'text-ok' => $r['kind'] === 'payment', 'line-through' => $r['void']])>
                                {{ $r['kind'] === 'bill' ? Money::format($m->total_paise) : '−'.Money::format($m->amount_paise) }}
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.panel>

        <aside class="lg:col-span-4">
            <div class="card lg:sticky lg:top-24" id="pay">
                <h2 class="mb-4 text-lg font-bold">Record payment</h2>
                <form method="POST" action="{{ route('suppliers.payments.store', $s) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="label" for="amount">Amount paid</label>
                        <div class="relative"><span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted">₹</span>
                            <input id="amount" name="amount" value="{{ old('amount') }}" class="input pl-7 text-right tnum" inputmode="decimal" placeholder="{{ $balance > 0 ? Money::format($balance, false, false) : '0' }}" required @if (request('pay') || $errors->any()) autofocus @endif></div>
                        @error('amount') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="label" for="pdate">Date</label>
                            <input id="pdate" type="date" name="date" value="{{ old('date', $today) }}" max="{{ $today }}" class="input" required>
                            @error('date') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label" for="mode">Paid by</label>
                            <select id="mode" name="mode" class="input">
                                @foreach ($modes as $k => $label) <option value="{{ $k }}" @selected(old('mode', 'cash') === $k)>{{ $label }}</option> @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="purchase_id">Against bill <span class="font-normal text-muted">(optional)</span></label>
                        <select id="purchase_id" name="purchase_id" class="input">
                            <option value="">Oldest bills first</option>
                            @foreach ($openBills as $b)
                                <option value="{{ $b->id }}" @selected($payFor === $b->id)>Bill {{ $b->bill_no ?: $b->ref() }} · {{ $b->bill_date->format('j M') }} · {{ Money::format($b->duePaise()) }} due</option>
                            @endforeach
                        </select>
                        @error('purchase_id') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="reference">UTR / cheque no. <span class="font-normal text-muted">(optional)</span></label>
                        <input id="reference" name="reference" value="{{ old('reference') }}" class="input" maxlength="80">
                    </div>
                    <div>
                        <label class="label" for="pnote">Note <span class="font-normal text-muted">(optional)</span></label>
                        <input id="pnote" name="note" value="{{ old('note') }}" class="input" maxlength="300">
                    </div>
                    <button class="btn-primary w-full"><x-icon name="check" class="size-5" stroke="2" /> Save payment</button>
                </form>
                @if ($s->notes)
                    <p class="mt-4 border-t border-line pt-3 text-sm text-ink-2"><span class="font-semibold">Notes:</span> {{ $s->notes }}</p>
                @endif
            </div>
        </aside>
    </div>
</x-layouts.app>
