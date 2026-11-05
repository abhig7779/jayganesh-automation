@props(['status'])
@php
    $map = [
        'paid' => ['Paid', 'border-green-200 bg-green-50 text-green-700'],
        'partial' => ['Part paid', 'border-amber-200 bg-amber-50 text-amber-700'],
        'due' => ['Due', 'border-red-200 bg-red-50 text-red-600'],
        'cancelled' => ['Cancelled', 'border-red-200 bg-red-50 text-red-600'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'border-zinc-200 bg-zinc-50 text-zinc-600'];
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full border px-2 py-px text-[11px] font-semibold', $cls]) }}>{{ $label }}</span>
