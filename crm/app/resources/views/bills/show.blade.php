@php
    use App\Support\Money;
    use App\Support\Phone;
    $i = $invoice;
    $canCancel = auth()->user()->can('cancel', $i);
    $canReturn = auth()->user()->can('returnItems', $i) && $i->items->contains(fn ($it) => $it->returnableQty() > 0);
    $intra = $i->igst_paise === 0;
@endphp
<x-layouts.app title="Bill {{ $i->number }}">
<div x-data="{ menu: false, sheet: null, reason: '{{ old('reason') }}' }" @keydown.escape.window="menu = false; sheet = null">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('bills.index') }}" class="mb-1 inline-flex items-center gap-1 text-[13px] font-semibold text-zinc-600 hover:text-ink"><x-bills.icon name="chevron-left" class="size-4" />Bills</a>
            <h1 class="flex flex-wrap items-center gap-3 text-[28px] font-bold leading-tight lg:text-2xl">
                {{ $i->number }} <x-bills.status :status="$i->status" class="!text-xs" />
            </h1>
            <p class="text-sm text-zinc-600">{{ $i->isTaxInvoice() ? 'Tax invoice' : 'Bill of supply' }} · {{ $i->created_at->timezone('Asia/Kolkata')->format('j M Y, g:i A') }} · by {{ $i->creator?->name }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('bills.print', [$i, 'format' => 'a4']) }}" target="_blank" class="btn-ghost"><x-bills.icon name="printer" class="size-4" />Print</a>
            @if ($waLink && ! $i->isCancelled())
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="btn-ghost hidden sm:inline-flex"><x-bills.icon name="chat" class="size-4" />WhatsApp</a>
            @endif
            <div class="relative" @click.outside="menu = false">
                <button type="button" @click="menu = !menu" class="btn-ghost !px-3" aria-label="More actions" :aria-expanded="menu"><x-bills.icon name="dots" class="size-5" /></button>
                <div x-show="menu" x-cloak class="absolute right-0 z-30 mt-1 w-60 overflow-hidden rounded-xl border border-zinc-200 bg-white py-1 shadow-[0_8px_24px_rgba(0,0,0,.08)]">
                    <a href="{{ route('bills.print', [$i, 'format' => 'a4']) }}" target="_blank" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-zinc-50"><x-bills.icon name="printer" class="size-4" />Print A4</a>
                    <a href="{{ route('bills.print', [$i, 'format' => 'thermal']) }}" target="_blank" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-zinc-50"><x-bills.icon name="receipt" class="size-4" />Print thermal 80 mm</a>
                    <a href="{{ route('bills.pdf', [$i, 'download' => 1]) }}" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-zinc-50"><x-bills.icon name="download" class="size-4" />Download PDF</a>
                    @if ($waLink && ! $i->isCancelled())
                        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-zinc-50"><x-bills.icon name="chat" class="size-4" />Resend on WhatsApp</a>
                    @endif
                    @if ($i->due_paise > 0 && $i->customer_id && auth()->user()->can('payments.collect'))
                        <a href="{{ route('payments.collect', ['customer' => $i->customer_id]) }}" class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-zinc-50"><x-bills.icon name="wallet" class="size-4" />Collect due</a>
                    @endif
                    @if ($canReturn)
                        <button type="button" @click="menu = false; sheet = 'return'" class="flex w-full items-center gap-2 border-t border-zinc-100 px-4 py-2.5 text-left text-sm hover:bg-zinc-50"><x-bills.icon name="return" class="size-4" />Return items</button>
                    @endif
                    @if ($canCancel)
                        <button type="button" @click="menu = false; sheet = 'cancel'" class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-semibold text-red-600 hover:bg-red-50"><x-bills.icon name="ban" class="size-4" />Cancel bill</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="relative grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
        @if ($i->isCancelled())
            <div class="pointer-events-none absolute inset-x-0 top-24 z-10 flex justify-center" aria-hidden="true">
                <span class="-rotate-12 rounded-lg border-4 border-red-600 px-6 py-2 text-5xl font-black tracking-widest text-red-600/80">CANCELLED</span>
            </div>
        @endif

        <div class="space-y-4">
            @if ($i->isCancelled())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <span class="font-semibold">Cancelled</span> by {{ $i->canceller?->name }} on {{ $i->cancelled_at?->timezone('Asia/Kolkata')->format('j M Y, g:i A') }}. Reason: {{ $i->cancel_reason }}. Stock went back; money paid is recorded as refunded.
                </div>
            @endif

            {{-- Items --}}
            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-[13px]">
                    <thead class="bg-zinc-100 text-left text-[11px] font-semibold uppercase tracking-wider text-zinc-600">
                        <tr>
                            <th class="px-4 py-2.5">Item</th>
                            <th class="px-2 py-2.5">HSN</th>
                            <th class="px-2 py-2.5 text-right">Qty</th>
                            <th class="px-2 py-2.5 text-right">MRP</th>
                            <th class="px-2 py-2.5 text-right">Rate</th>
                            <th class="px-2 py-2.5 text-right">Taxable</th>
                            <th class="px-2 py-2.5 text-right">GST</th>
                            <th class="px-4 py-2.5 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($i->items as $item)
                            <tr>
                                <td class="px-4 py-2.5">
                                    <span class="font-semibold text-ink">{{ $item->name }}</span>
                                    @if ($item->returned_qty)<span class="ml-1 rounded-full border border-amber-200 bg-amber-50 px-1.5 text-[11px] font-semibold text-amber-700">{{ $item->returned_qty }} returned</span>@endif
                                    @if ($item->discount_paise)<span class="block text-xs text-zinc-500">Discount {{ Money::format($item->discount_paise, true) }}</span>@endif
                                </td>
                                <td class="px-2 py-2.5 text-zinc-600">{{ $item->hsn ?: '—' }}</td>
                                <td class="px-2 py-2.5 text-right tabular-nums">{{ $item->qty }}</td>
                                <td class="px-2 py-2.5 text-right tabular-nums text-zinc-500">@if ($item->shownMrpPaise())<s>{{ Money::format($item->shownMrpPaise()) }}</s>@else — @endif</td>
                                <td class="px-2 py-2.5 text-right tabular-nums">{{ Money::format($item->unitInclPaise()) }}</td>
                                <td class="px-2 py-2.5 text-right tabular-nums">{{ Money::format($item->taxable_paise, true) }}</td>
                                <td class="px-2 py-2.5 text-right tabular-nums text-zinc-600">{{ $item->rateLabel() }}%<span class="block text-xs">{{ Money::format($item->taxPaise(), true) }}</span></td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ Money::format($item->line_total_paise, true) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <dl class="ml-auto max-w-sm space-y-1 border-t border-zinc-200 px-4 py-3 text-[13px] text-zinc-700 tabular-nums">
                    <div class="flex justify-between"><dt>Taxable value</dt><dd>{{ Money::format($i->subtotal_paise, true) }}</dd></div>
                    @if ($intra)
                        <div class="flex justify-between"><dt>CGST</dt><dd>{{ Money::format($i->cgst_paise, true) }}</dd></div>
                        <div class="flex justify-between"><dt>SGST</dt><dd>{{ Money::format($i->sgst_paise, true) }}</dd></div>
                    @else
                        <div class="flex justify-between"><dt>IGST</dt><dd>{{ Money::format($i->igst_paise, true) }}</dd></div>
                    @endif
                    @if ($i->round_off_paise)
                        <div class="flex justify-between"><dt>Round off</dt><dd>{{ ($i->round_off_paise > 0 ? '+' : '').Money::format($i->round_off_paise, true) }}</dd></div>
                    @endif
                    <div class="flex justify-between border-t border-zinc-200 pt-2 text-lg font-bold text-ink"><dt>Total</dt><dd>{{ Money::format($i->total_paise) }}</dd></div>
                    @if ($i->savingsLine())
                        <div class="rounded-md bg-green-50 px-2 py-1.5 text-[13px] font-semibold text-green-800">{{ $i->savingsLine() }}</div>
                    @endif
                </dl>
            </section>

            {{-- GST summary --}}
            @if ($i->isTaxInvoice())
                <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                    <h2 class="border-b border-zinc-200 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">GST summary (HSN-wise)</h2>
                    <table class="w-full text-[13px] tabular-nums">
                        <thead class="text-left text-xs text-zinc-600"><tr>
                            <th class="px-4 py-2 font-semibold">HSN / SAC</th><th class="px-2 py-2 text-right font-semibold">Rate</th><th class="px-2 py-2 text-right font-semibold">Taxable</th>
                            @if ($intra)<th class="px-2 py-2 text-right font-semibold">CGST</th><th class="px-2 py-2 text-right font-semibold">SGST</th>@else<th class="px-2 py-2 text-right font-semibold">IGST</th>@endif
                            <th class="px-4 py-2 text-right font-semibold">Total tax</th>
                        </tr></thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($hsn as $h)
                                <tr>
                                    <td class="px-4 py-2">{{ $h['hsn'] ?: '—' }}</td>
                                    <td class="px-2 py-2 text-right">{{ rtrim(rtrim(number_format($h['gst_rate'], 2), '0'), '.') }}%</td>
                                    <td class="px-2 py-2 text-right">{{ Money::format($h['taxable_paise'], true) }}</td>
                                    @if ($intra)<td class="px-2 py-2 text-right">{{ Money::format($h['cgst_paise'], true) }}</td><td class="px-2 py-2 text-right">{{ Money::format($h['sgst_paise'], true) }}</td>@else<td class="px-2 py-2 text-right">{{ Money::format($h['igst_paise'], true) }}</td>@endif
                                    <td class="px-4 py-2 text-right font-semibold">{{ Money::format($h['tax_paise'], true) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif

            @if ($i->creditNotes->isNotEmpty())
                <section class="rounded-xl border border-zinc-200 bg-white p-4">
                    <h2 class="text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Returns (credit notes)</h2>
                    <ul class="mt-2 divide-y divide-zinc-100 text-[13px]">
                        @foreach ($i->creditNotes as $cn)
                            <li class="flex justify-between py-2">
                                <span><span class="font-semibold">{{ $cn->number }}</span> · {{ $cn->created_at->timezone('Asia/Kolkata')->format('j M') }} · {{ $cn->reason }}</span>
                                <span class="tabular-nums">{{ Money::format($cn->total_paise, true) }}@if ($cn->refund_paise) <span class="text-zinc-500">(refunded {{ Money::format($cn->refund_paise) }})</span>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="space-y-4">
            <section class="rounded-xl border border-zinc-200 bg-white p-4 text-[13px]">
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Customer</h2>
                <p class="mt-1 text-[17px] font-semibold">{{ $i->customer_name }}</p>
                @if ($i->customer_mobile)
                    <p class="mt-0.5 flex items-center gap-3 text-zinc-700">{{ Phone::pretty($i->customer_mobile) }}
                        <a href="tel:{{ $i->customer_mobile }}" class="font-semibold underline">Call</a>
                    </p>
                @endif
                @if ($i->car_label)<p class="mt-1 text-zinc-600">{{ $i->car_label }}</p>@endif
                @if ($customerDue > 0)
                    <p class="mt-2 font-semibold text-red-600">Total due on all bills: {{ Money::format($customerDue) }}</p>
                @endif
                @if ($i->customer_id && \Illuminate\Support\Facades\Route::has('customers.show'))
                    <a href="{{ route('customers.show', $i->customer_id) }}" class="mt-2 inline-block font-semibold underline">Open profile</a>
                @endif
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-4 text-[13px]">
                <h2 class="text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Payments</h2>
                @forelse ($i->allocations->sortBy('id') as $a)
                    <div class="flex justify-between border-b border-zinc-100 py-2 last:border-0">
                        <span>
                            <span class="font-semibold">{{ $a->payment->kind === 'refund' ? 'Refund · ' : '' }}{{ $a->payment->modeLabel() }}</span>
                            <span class="block text-xs text-zinc-500">{{ $a->payment->created_at->timezone('Asia/Kolkata')->format('j M, g:i A') }} · {{ $a->payment->receiver?->name }}@if ($a->payment->reference) · Ref {{ $a->payment->reference }}@endif</span>
                        </span>
                        <span class="font-semibold tabular-nums {{ $a->amount_paise < 0 ? 'text-red-600' : '' }}">{{ Money::format($a->amount_paise) }}</span>
                    </div>
                @empty
                    <p class="mt-2 text-zinc-600">Nothing paid yet.</p>
                @endforelse
                <div class="mt-2 flex justify-between border-t border-zinc-200 pt-2 font-semibold"><span>Due</span><span class="tabular-nums {{ $i->due_paise > 0 ? 'text-red-600' : '' }}">{{ Money::format($i->due_paise) }}</span></div>
                @if ($i->due_paise > 0 && $i->customer_id && auth()->user()->can('payments.collect'))
                    <a href="{{ route('payments.collect', ['customer' => $i->customer_id]) }}" class="btn-accent mt-3 w-full">Collect {{ Money::format($i->due_paise) }}</a>
                @endif
            </section>
            @if ($i->notes)
                <section class="rounded-xl border border-zinc-200 bg-white p-4 text-[13px]"><h2 class="text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Note</h2><p class="mt-1">{{ $i->notes }}</p></section>
            @endif
        </aside>
    </div>

    {{-- Cancel sheet (Owner/Admin) --}}
    @if ($canCancel)
        <div x-show="sheet === 'cancel'" x-cloak class="fixed inset-0 z-50 flex items-end justify-center lg:items-center">
            <div class="absolute inset-0 bg-ink/40" @click="sheet = null"></div>
            <form method="POST" action="{{ route('bills.cancel', $i) }}" class="relative w-full max-w-md rounded-t-2xl bg-white p-5 lg:rounded-xl">
                @csrf
                <div class="mx-auto mb-4 h-1 w-9 rounded-full bg-zinc-300 lg:hidden"></div>
                <h2 class="text-[22px] font-bold">Cancel {{ $i->number }}?</h2>
                <p class="mt-1 text-sm text-zinc-600">The bill keeps its number and shows CANCELLED. Stock goes back and {{ $i->paid_paise > 0 ? Money::format($i->paid_paise).' is recorded as refunded' : 'nothing was paid' }}. This can’t be undone.</p>
                <p class="mt-4 text-[13px] font-semibold">Reason</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (['Wrong item', 'Customer returned', 'Duplicate', 'Other'] as $r)
                        <button type="button" @click="reason = '{{ $r }}'" :class="reason === '{{ $r }}' ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white'" class="h-10 rounded-full border px-4 text-sm font-semibold">{{ $r }}</button>
                    @endforeach
                </div>
                <input type="hidden" name="reason" :value="reason">
                <textarea name="note" rows="2" class="input mt-3 py-2" placeholder="Details (optional, needed for Other)" maxlength="200">{{ old('note') }}</textarea>
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <button type="button" class="h-12 rounded-lg border border-zinc-300 font-semibold" @click="sheet = null">Keep bill</button>
                    <button class="h-12 rounded-lg border border-red-600 bg-white font-semibold text-red-600 disabled:opacity-50" :disabled="!reason">Cancel bill</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Return items (credit note) --}}
    @if ($canReturn)
        <div x-show="sheet === 'return'" x-cloak class="fixed inset-0 z-50 flex items-end justify-center lg:items-center">
            <div class="absolute inset-0 bg-ink/40" @click="sheet = null"></div>
            <form method="POST" action="{{ route('bills.return', $i) }}" class="relative max-h-[90dvh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white p-5 lg:rounded-xl">
                @csrf
                <h2 class="text-[22px] font-bold">Return items</h2>
                <p class="mt-1 text-sm text-zinc-600">Makes a credit note for the items taken back. Stock goes back; the amount clears any due first, then is refunded.</p>
                <ul class="mt-4 divide-y divide-zinc-100 rounded-lg border border-zinc-200">
                    @foreach ($i->items as $item)
                        @continue($item->returnableQty() < 1)
                        <li class="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
                            <span><span class="font-semibold">{{ $item->name }}</span><span class="block text-xs text-zinc-500">Sold {{ $item->qty }}@if ($item->returned_qty), {{ $item->returned_qty }} already back @endif · {{ Money::format(intdiv($item->line_total_paise, $item->qty)) }} each</span></span>
                            <input type="number" name="qty[{{ $item->id }}]" min="0" max="{{ $item->returnableQty() }}" value="0" class="input !min-h-10 w-20 text-right" aria-label="Quantity to return">
                        </li>
                    @endforeach
                </ul>
                <label class="mt-3 block text-[13px] font-semibold">Reason<input name="reason" required maxlength="200" class="input mt-1" placeholder="e.g. Wrong size, customer returned"></label>
                <label class="mt-3 block text-[13px] font-semibold">Refund by
                    <select name="refund_mode" class="input mt-1">@foreach (\App\Models\Payment::MODE_LABELS as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                </label>
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <button type="button" class="h-12 rounded-lg border border-zinc-300 font-semibold" @click="sheet = null">Close</button>
                    <button class="h-12 rounded-lg bg-ink font-semibold text-white">Save credit note</button>
                </div>
            </form>
        </div>
    @endif
</div>
</x-layouts.app>
