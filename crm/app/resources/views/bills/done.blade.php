@php
    use App\Support\Money;
    use App\Support\Phone;
    $i = $invoice;
    $hasNumber = Phone::isValidIndianMobile($i->customer_mobile);
    $doneCfg = [
        'statusUrl' => route('bills.status', $i),
        'pdfUrl' => route('bills.pdf', $i),
        'publicUrl' => $pdfUrl,
        'fileName' => $i->fileName(),
        'title' => 'Bill '.$i->number,
        'text' => \App\Services\Billing\BillShare::message($i, $pdfUrl),
        'sendWhatsapp' => $i->send_whatsapp,
        'automatic' => app(\App\Services\WhatsApp\WhatsApp::class)->isAutomatic(),
        'manualLink' => $waLink,
        'initialStatus' => null,
    ];
@endphp
<x-layouts.app title="Bill {{ $i->number }} saved">
<div x-data="billDone(@js($doneCfg))" @keydown.f2.window.prevent="window.location='{{ route('bills.create') }}'" class="mx-auto grid max-w-5xl gap-5 lg:grid-cols-[minmax(0,1fr)_380px]">
    <div class="space-y-4">
        <section class="rounded-xl border border-zinc-200 bg-white p-5 sm:p-6">
            <div class="grid size-14 place-items-center rounded-full border-2 border-green-700 text-green-700"><x-bills.icon name="check" class="size-7" /></div>
            <h1 class="mt-3 text-[28px] font-bold leading-tight">Bill saved</h1>
            <p class="mt-1 text-[15px] text-zinc-600"><span class="font-semibold text-ink">{{ $i->number }}</span> · {{ $i->created_at->timezone('Asia/Kolkata')->format('j M, g:i A') }}</p>
            <p class="mt-3 text-[40px] font-bold leading-none tracking-tight tabular-nums">{{ Money::format($i->total_paise) }}</p>
            <p class="mt-2 text-[15px] text-zinc-700">
                {{ $i->customer_name }}@if ($i->car_label) · {{ $i->car_label }}@endif
                <span class="block text-zinc-600">{{ $i->paymentSummary() }}</span>
            </p>
            @if ($i->savingsLine())
                <p class="mt-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-[15px] font-semibold text-green-800">{{ $i->savingsLine() }}</p>
            @endif
            @if ($i->due_paise > 0)
                <p class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-[15px] font-semibold text-red-700">Due {{ Money::format($i->due_paise) }}. Collect it later from Collect payment.</p>
            @endif
        </section>

        {{-- WhatsApp e-bill --}}
        <section class="rounded-xl border border-zinc-200 bg-white p-5">
            <h2 class="flex items-center gap-2 text-[17px] font-semibold"><x-bills.icon name="chat" class="size-5" />WhatsApp e-bill</h2>
            @if (! $hasNumber)
                <p class="mt-2 text-[15px] text-zinc-600">Walk-in bill with no mobile number, so no WhatsApp bill. Print it or share the PDF.</p>
            @elseif (! $i->send_whatsapp)
                <p class="mt-2 text-[15px] text-zinc-600">Not sent (switched off for this bill). You can still send it:</p>
                <a href="{{ $waLink }}" target="_blank" rel="noopener" class="mt-3 inline-flex h-12 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-[15px] font-semibold hover:border-ink"><x-bills.icon name="chat" class="size-5" />Send on WhatsApp</a>
            @else
                <template x-if="automatic">
                    <div class="mt-3">
                        <ol class="flex items-center gap-2 text-[13px] font-semibold">
                            @foreach (['sending' => 'Sending', 'sent' => 'Sent ✓', 'delivered' => 'Delivered ✓✓', 'read' => 'Read'] as $k => $label)
                                <li class="flex items-center gap-2" :class="['sending','sent','delivered','read'].indexOf(status || 'sending') >= {{ $loop->index }} ? 'text-ink' : 'text-zinc-400'">
                                    @if (! $loop->first)<span class="h-px w-6 bg-zinc-300"></span>@endif{{ $label }}
                                </li>
                            @endforeach
                        </ol>
                        <p class="mt-2 text-[13px] text-zinc-600">To {{ Phone::pretty($i->customer_mobile) }} · PDF attached</p>
                        <div x-show="status === 'failed'" class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-[13px] text-red-700">
                            <span class="font-semibold">Not sent. The number may not be on WhatsApp.</span>
                            <a :href="manualLink" target="_blank" rel="noopener" class="shrink-0 rounded-md border border-red-300 bg-white px-3 py-1.5 font-semibold">Send manually</a>
                        </div>
                    </div>
                </template>
                <template x-if="!automatic">
                    <div class="mt-2">
                        <p class="text-[15px] text-zinc-600">Automatic WhatsApp isn’t switched on yet. One tap opens WhatsApp with the message and a PDF link (valid {{ \App\Services\Billing\BillShare::LINK_DAYS }} days) for {{ Phone::pretty($i->customer_mobile) }}.</p>
                        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="mt-3 inline-flex h-12 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-[15px] font-semibold hover:border-ink"><x-bills.icon name="chat" class="size-5" />Send on WhatsApp</a>
                    </div>
                </template>
            @endif
        </section>

        {{-- Next steps --}}
        <section class="grid gap-2">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <a href="{{ route('bills.print', [$i, 'format' => 'a4']) }}" target="_blank" class="flex h-[52px] items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white text-[15px] font-semibold hover:border-ink">
                    <x-bills.icon name="printer" class="size-5" />Print A4
                </a>
                <a href="{{ route('bills.print', [$i, 'format' => 'thermal']) }}" target="_blank" class="flex h-[52px] items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white text-[15px] font-semibold hover:border-ink">
                    <x-bills.icon name="receipt" class="size-5" />Thermal 80 mm
                </a>
                <button type="button" @click="share()" class="col-span-2 flex h-[52px] items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white text-[15px] font-semibold hover:border-ink sm:col-span-1">
                    <x-bills.icon name="share" class="size-5" />Share PDF
                </button>
            </div>
            <a href="{{ route('bills.create') }}" class="flex h-14 items-center justify-center gap-2 rounded-lg bg-brand-400 text-[17px] font-semibold text-ink hover:bg-[#F2C800]">
                <x-bills.icon name="plus" class="size-5" />New bill <x-bills.kbd class="ml-1 hidden lg:inline-flex">F2</x-bills.kbd>
            </a>
            <div class="flex items-center justify-center gap-6 text-[15px] font-semibold">
                <a href="{{ route('dashboard') }}" class="py-2 underline-offset-4 hover:underline">Back to home</a>
                <a href="{{ route('bills.show', $i) }}" class="py-2 underline-offset-4 hover:underline">Open bill</a>
            </div>
        </section>
    </div>

    {{-- Summary --}}
    <aside class="h-fit rounded-xl border border-zinc-200 bg-white">
        <h2 class="border-b border-zinc-200 px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">{{ $i->items->count() }} item{{ $i->items->count() === 1 ? '' : 's' }}</h2>
        <ul class="divide-y divide-zinc-100">
            @foreach ($i->items as $item)
                <li class="flex justify-between gap-3 px-4 py-2.5 text-[13px]">
                    <span class="min-w-0">
                        <span class="block font-semibold text-ink">{{ $item->name }}</span>
                        <span class="text-zinc-600">{{ $item->qty }} × {{ Money::format($item->unitInclPaise()) }}
                            @if ($item->shownMrpPaise())<span class="ml-1 text-zinc-500 line-through">MRP {{ Money::format($item->shownMrpPaise()) }}</span>@endif
                        </span>
                    </span>
                    <span class="font-semibold tabular-nums">{{ Money::format($item->line_total_paise) }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="space-y-1 border-t border-zinc-200 px-4 py-3 text-[13px] text-zinc-700 tabular-nums">
            <div class="flex justify-between"><dt>Taxable</dt><dd>{{ Money::format($i->subtotal_paise, true) }}</dd></div>
            @if ($i->isTaxInvoice())
                <div class="flex justify-between"><dt>GST</dt><dd>{{ Money::format($i->taxPaise(), true) }}</dd></div>
            @endif
            @if ($i->round_off_paise)
                <div class="flex justify-between"><dt>Round off</dt><dd>{{ ($i->round_off_paise > 0 ? '+' : '').Money::format($i->round_off_paise, true) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-zinc-200 pt-2 text-[15px] font-bold text-ink"><dt>Total</dt><dd>{{ Money::format($i->total_paise) }}</dd></div>
        </dl>
    </aside>
</div>
</x-layouts.app>
