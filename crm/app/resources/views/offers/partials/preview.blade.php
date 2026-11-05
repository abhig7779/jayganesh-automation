<div>
    <div class="flex items-baseline justify-between gap-2">
        <div class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Preview</div>
        <div class="text-xs text-zinc-600">
            @if ($customer)
                as {{ $customer->name }}{{ $customer->cars->first() ? ' · '.$customer->cars->first()->label() : '' }}
            @else
                sample customer (no one matches yet)
            @endif
        </div>
    </div>
    <div class="mt-2 rounded-xl border border-zinc-200 bg-[#F4F4F5] p-3">
        <div class="ml-auto max-w-[92%] whitespace-pre-line break-words rounded-lg border border-zinc-200 bg-white px-3 py-2 text-[15px] leading-snug text-ink" data-preview>{{ $rendered !== '' ? $rendered : 'Your message appears here.' }}</div>
    </div>
    <p @class(['mt-2 text-xs', 'text-red-600 font-semibold' => $length > 1000, 'text-zinc-600' => $length <= 1000])>
        {{ number_format($length) }} / 1,000 characters
        @if ($length > 1000) · too long for a WhatsApp template @endif
    </p>
</div>
