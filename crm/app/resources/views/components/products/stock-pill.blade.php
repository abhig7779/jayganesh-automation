@props(['product', 'size' => 'md', 'units' => true])
{{-- Green in stock, amber at or under the reorder level, red at 0 or below, grey for services. --}}
@php
    $state = $product->stockState();
    $unit = $units && $product->unit && $product->unit !== 'pcs' ? ' '.$product->unit : '';
    [$cls, $text] = match ($state) {
        'service' => ['border-zinc-300 bg-white text-zinc-600', 'Service'],
        'out' => ['border-red-200 bg-red-50 text-red-600', $product->on_hand < 0 ? 'Out ('.$product->on_hand.')' : 'Out of stock'],
        'low' => ['border-amber-200 bg-amber-50 text-amber-700', $product->on_hand.$unit.' left'],
        default => ['border-green-200 bg-green-50 text-green-700', $product->on_hand.$unit.' in stock'],
    };
@endphp
<span {{ $attributes->class(['inline-flex shrink-0 items-center whitespace-nowrap rounded-full border font-semibold tabular-nums', $cls, 'px-2 py-0.5 text-[11px]' => $size === 'sm', 'px-2.5 py-0.5 text-xs' => $size === 'md', 'px-3 py-1 text-sm' => $size === 'lg']) }} data-stock="{{ $state }}">{{ $text }}</span>
