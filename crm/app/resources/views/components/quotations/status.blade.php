@props(['status', 'label' => null])
@php
    $tone = match ($status) {
        'won' => 'border-green-200 bg-green-50 text-green-700',
        'lost' => 'border-red-200 bg-red-50 text-red-600',
        'visit_booked', 'quote_sent' => 'border-ink bg-white text-ink',
        'expired' => 'border-zinc-200 bg-zinc-100 text-zinc-600',
        'new' => 'border-amber-200 bg-amber-50 text-amber-700',
        default => 'border-zinc-300 bg-white text-ink',
    };
    $label ??= \App\Models\Quotation::STATUS_LABELS[$status] ?? (\App\Models\Lead::STATUS_LABELS[$status] ?? ucfirst($status));
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center whitespace-nowrap rounded-full border px-2 py-0.5 text-[11px] font-semibold $tone"]) }}>{{ $label }}</span>
