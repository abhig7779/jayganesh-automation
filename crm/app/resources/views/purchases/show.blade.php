@php
    use App\Support\Money;
    use App\Models\SupplierPayment;
    $p = $purchase;
    $badge = ['paid' => 'paid', 'part' => 'part_paid', 'due' => 'due', 'cancelled' => 'cancelled'];
@endphp
<x-layouts.app :title="'Purchase '.$p->ref()">
    <x-ui.page-header :title="$p->ref().' · '.$p->supplier->name" :back="route('purchases.index')" back-label="Purchases"
        :subtitle="'Supplier bill '.($p->bill_no ?: '(no number)').' · '.$p->bill_date->format('j M Y').' · entered by '.($p->creator?->name ?? '—').' on '.$p->created_at->timezone('Asia/Kolkata')->format('j M, g:i a')">
        <x-slot:actions>
            <x-ui.status-badge :status="$badge[$p->payState()]" class="text-[13px]" />
            @if ($p->photo_path)
                <a href="{{ route('purchases.photo', $p) }}" target="_blank" class="btn-ghost"><x-icon name="camera" class="size-5" /> Bill photo</a>
            @endif
            <a href="{{ route('suppliers.show', $p->supplier) }}" class="btn-ghost"><x-icon name="users" class="size-5" /> Supplier ledger</a>
            <a href="{{ route('purchases.create', ['supplier' => $p->supplier_id]) }}" class="btn-accent"><x-icon name="plus" class="size-5" stroke="2" /> New purchase</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($p->isCancelled())
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-bad-line bg-bad-tint px-4 py-3 text-bad">
            <x-icon name="alert" class="mt-0.5 size-5 shrink-0" />
            <div class="text-sm font-medium">
                <span class="font-bold tracking-wide uppercase">Cancelled</span>
                on {{ $p->cancelled_at->timezone('Asia/Kolkata')->format('j M Y, g:i a') }} by {{ $p->canceller?->name ?? '—' }}. Reason: {{ $p->cancel_reason }}.
                The stock went back out. It no longer counts in the supplier balance.
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 *:min-w-0 lg:grid-cols-12">
        <section class="self-start overflow-hidden rounded-xl border border-line bg-surface lg:col-span-8">
            <table class="table-pc hidden lg:table">
                <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Cost / unit</th><th class="text-right">GST</th><th class="text-right">Taxable</th><th class="text-right">Total</th><th class="text-right">In stock now</th></tr></thead>
                <tbody>
                    @foreach ($p->items as $item)
                        <tr>
                            <td>
                                @if ($item->product && Route::has('products.show'))
                                    <a href="{{ route('products.show', $item->product) }}" class="font-semibold text-ink hover:underline">{{ $item->name }}</a>
                                @else
                                    <span class="font-semibold text-ink">{{ $item->name }}</span>
                                @endif
                                @if ($item->product?->sku) <div class="text-xs text-muted">{{ $item->product->sku }}</div> @endif
                            </td>
                            <td class="tnum text-right">{{ $item->qty }}</td>
                            <td class="tnum text-right">{{ Money::format($item->unit_cost_paise) }}</td>
                            <td class="tnum text-right">{{ rtrim(rtrim((string) $item->gst_rate, '0'), '.') }}%</td>
                            <td class="tnum text-right">{{ Money::format($item->taxable_paise) }}</td>
                            <td class="tnum text-right font-semibold text-ink">{{ Money::format($item->total_paise) }}</td>
                            <td class="tnum text-right">{{ $item->product?->on_hand ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <ul class="divide-y divide-line lg:hidden">
                @foreach ($p->items as $item)
                    <li class="flex items-start gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-ink">{{ $item->name }}</div>
                            <div class="text-[13px] text-muted">{{ $item->qty }} × {{ Money::format($item->unit_cost_paise) }} + {{ rtrim(rtrim((string) $item->gst_rate, '0'), '.') }}% GST</div>
                        </div>
                        <div class="tnum font-semibold">{{ Money::format($item->total_paise) }}</div>
                    </li>
                @endforeach
            </ul>
            @if ($p->note)
                <p class="border-t border-line px-4 py-3 text-sm text-ink-2 lg:px-5"><span class="font-semibold">Note:</span> {{ $p->note }}</p>
            @endif
        </section>

        <aside class="space-y-5 lg:col-span-4">
            <div class="card">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Taxable value</dt><dd class="tnum font-medium">{{ Money::format($p->subtotal_paise) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">GST input</dt><dd class="tnum font-medium">{{ Money::format($p->gst_paise) }}</dd></div>
                    <div class="flex items-end justify-between border-t border-line pt-3"><dt class="text-[15px] font-semibold">Bill total</dt><dd @class(['tnum text-2xl font-bold', 'line-through' => $p->isCancelled()])>{{ Money::format($p->total_paise) }}</dd></div>
                    @unless ($p->isCancelled())
                        <div class="flex justify-between"><dt class="text-muted">Paid</dt><dd class="tnum font-medium text-ok">{{ Money::format($p->paid_paise) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">To pay</dt><dd @class(['tnum font-semibold', 'text-bad' => $p->duePaise() > 0])>{{ Money::format($p->duePaise()) }}</dd></div>
                    @endunless
                </dl>
                @if (! $p->isCancelled() && $p->duePaise() > 0)
                    <a href="{{ route('suppliers.show', [$p->supplier, 'pay' => $p->id]) }}#pay" class="btn-primary mt-4 w-full"><x-icon name="rupee" class="size-5" /> Record payment</a>
                @endif
            </div>

            @if ($p->payments->isNotEmpty())
                <x-ui.panel title="Paid against this bill" flush>
                    <ul class="divide-y divide-line">
                        @foreach ($p->payments as $pay)
                            <li class="flex items-center justify-between px-4 py-2.5 text-sm lg:px-5">
                                <span>{{ $pay->date->format('j M Y') }} · {{ SupplierPayment::MODES[$pay->mode] ?? $pay->mode }}{{ $pay->reference ? ' · '.$pay->reference : '' }}</span>
                                <span class="tnum font-semibold">{{ Money::format($pay->amount_paise) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.panel>
            @endif

            @if ($canCancel && ! $p->isCancelled())
                <div class="card" x-data="{ open: {{ $errors->hasAny(['reason', 'reason_text']) ? 'true' : 'false' }}, reason: '{{ old('reason') }}' }">
                    <button type="button" class="btn-danger w-full" @click="open = !open" x-show="!open">Cancel this purchase</button>
                    <form method="POST" action="{{ route('purchases.cancel', $p) }}" x-show="open" x-cloak class="space-y-3">
                        @csrf
                        <h3 class="text-[15px] font-semibold">Cancel {{ $p->ref() }}?</h3>
                        <p class="text-sm text-muted">The stock of every item goes back out. Payments already made stay on the supplier's ledger as an advance.</p>
                        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Reason">
                            @foreach ($reasons as $k => $label)
                                <label class="cursor-pointer">
                                    <input type="radio" name="reason" value="{{ $k }}" x-model="reason" class="peer sr-only">
                                    <span class="inline-flex min-h-10 items-center rounded-full border border-line-strong px-3.5 text-[13px] font-semibold peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-ink">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('reason') <p class="error">Pick a reason.</p> @enderror
                        <input name="reason_text" value="{{ old('reason_text') }}" class="input" maxlength="150" placeholder="Details (needed for Other)">
                        @error('reason_text') <p class="error">{{ $message }}</p> @enderror
                        <div class="flex gap-2">
                            <button type="button" class="btn-ghost flex-1" @click="open = false">Keep it</button>
                            <button class="btn-danger flex-1" :disabled="!reason">Cancel purchase</button>
                        </div>
                    </form>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.app>
