@props(['status', 'size' => 'sm'])
@php
    // DESIGN.md §5 status badge: Waiting muted outline, Working warn, QC ink outline, Ready ok, Delivered muted.
    $styles = [
        'waiting' => 'border-zinc-300 bg-white text-zinc-600',
        'working' => 'border-amber-200 bg-amber-50 text-amber-700',
        'qc' => 'border-ink bg-white text-ink',
        'ready' => 'border-green-200 bg-green-50 text-green-700',
        'delivered' => 'border-zinc-200 bg-zinc-100 text-zinc-600',
        'cancelled' => 'border-red-200 bg-red-50 text-red-600',
    ];
@endphp
<span {{ $attributes->class([
    'inline-flex items-center rounded-full border font-semibold',
    'px-2 py-0.5 text-[11px]' => $size === 'sm',
    'px-3 py-1 text-xs' => $size !== 'sm',
    $styles[$status] ?? $styles['waiting'],
]) }}>{{ \App\Models\JobCard::LABELS[$status] ?? ucfirst($status) }}</span>
