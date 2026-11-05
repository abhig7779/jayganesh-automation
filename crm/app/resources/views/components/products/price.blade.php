@props(['product', 'size' => 'md', 'stack' => false])
{{-- Our price, with the normal price (MRP) struck through in grey and "% off" when there is a discount.
     stack = price on top, "MRP · % off" on a small line under it (phone rows, detail). --}}
@php
    $off = $product->discountPercent();
    $priceCls = match ($size) {
        'xl' => 'text-[34px] leading-10 font-bold tracking-tight',
        'lg' => 'text-[19px] leading-6 font-bold',
        'sm' => 'text-[13px] font-semibold',
        default => 'text-[15px] font-semibold',
    };
    $mrp = $off > 0 ? \App\Support\Money::format($product->mrp_paise) : null;
@endphp
@if ($stack)
    <span {{ $attributes->class(['inline-flex flex-col tabular-nums']) }}>
        <span class="{{ $priceCls }} text-ink" data-price>{{ \App\Support\Money::format($product->price_paise) }}</span>
        @if ($off > 0)
            <span class="mt-0.5 inline-flex items-center gap-1.5 self-end text-xs">
                <s class="font-medium text-zinc-500" title="Normal price (MRP)">{{ $mrp }}</s>
                <span class="rounded-full border border-green-200 bg-green-50 px-1.5 text-[11px] font-semibold leading-4 text-green-700">{{ $off }}% off</span>
            </span>
        @endif
    </span>
@else
    <span {{ $attributes->class(['inline-flex items-baseline justify-end gap-1.5 whitespace-nowrap tabular-nums']) }}>
        @if ($off > 0)
            <s class="text-xs font-medium text-zinc-500" title="Normal price (MRP)">{{ $mrp }}</s>
        @endif
        <span class="{{ $priceCls }} text-ink" data-price>{{ \App\Support\Money::format($product->price_paise) }}</span>
        @if ($off > 0)
            <span class="rounded-full border border-green-200 bg-green-50 px-1.5 text-[11px] font-semibold leading-4 text-green-700">{{ $off }}% off</span>
        @endif
    </span>
@endif
