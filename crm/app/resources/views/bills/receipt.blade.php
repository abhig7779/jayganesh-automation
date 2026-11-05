@php use App\Support\Money; @endphp
<x-layouts.app title="Payment received">
@include('bills.partials.web-fonts')
<div class="font-noto mx-auto max-w-xl space-y-4">
    <section class="rounded-xl border border-zinc-200 bg-white p-5">
        <div class="grid size-14 place-items-center rounded-full border-2 border-green-700 text-green-700"><x-bills.icon name="check" class="size-7" /></div>
        <h1 class="mt-3 text-[28px] font-bold">Payment received</h1>
        <p class="mt-1 text-[40px] font-bold leading-none tabular-nums">{{ Money::format($payment->amount_paise) }}</p>
        <p class="mt-2 text-[15px] text-zinc-700">{{ $payment->customer?->name }} · {{ $payment->modeLabel() }}@if ($payment->reference) · Ref {{ $payment->reference }}@endif · {{ $payment->created_at->timezone('Asia/Kolkata')->format('j M, g:i A') }}</p>
        <p class="mt-3 text-[15px] font-semibold {{ $balance > 0 ? 'text-red-600' : 'text-green-700' }}">{{ $balance > 0 ? 'Balance due '.Money::format($balance) : 'No balance due' }}</p>
    </section>
    <section class="rounded-xl border border-zinc-200 bg-white">
        <h2 class="border-b border-zinc-200 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Applied to</h2>
        <ul class="divide-y divide-zinc-100 text-[13px]">
            @foreach ($payment->allocations as $a)
                <li class="flex justify-between px-4 py-2.5">
                    <a href="{{ route('bills.show', $a->invoice_id) }}" class="font-semibold underline-offset-2 hover:underline">{{ $a->invoice?->number }}</a>
                    <span class="tabular-nums">{{ Money::format($a->amount_paise) }} <span class="text-zinc-500">· due left {{ Money::format($a->invoice?->due_paise ?? 0) }}</span></span>
                </li>
            @endforeach
        </ul>
    </section>
    @if ($waLink)
        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="flex h-14 items-center justify-center gap-2 rounded-lg bg-brand-400 text-[17px] font-semibold text-ink hover:bg-[#F2C800]"><x-bills.icon name="chat" class="size-5" />Send receipt on WhatsApp</a>
        <p class="text-center text-[13px] text-zinc-600">“{{ $waText }}”</p>
    @endif
    <div class="flex items-center justify-center gap-6 text-[15px] font-semibold">
        <a href="{{ route('payments.collect') }}" class="py-2 hover:underline">Collect another</a>
        <a href="{{ route('dashboard') }}" class="py-2 hover:underline">Back to home</a>
    </div>
</div>
</x-layouts.app>
