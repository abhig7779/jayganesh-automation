@props(['label', 'value', 'hint' => null, 'tone' => null])
<div {{ $attributes->merge(['class' => 'rounded-xl border border-zinc-200 bg-white px-3 py-3 sm:px-4']) }}>
    <div class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">{{ $label }}</div>
    <div @class([
        'mt-1 text-xl font-bold tabular-nums tracking-tight sm:text-2xl',
        'text-green-700' => $tone === 'ok',
        'text-red-600' => $tone === 'bad',
        'text-ink' => ! $tone,
    ])>{{ $value }}</div>
    @if ($hint)
        <div class="mt-0.5 text-xs font-medium text-zinc-600">{{ $hint }}</div>
    @endif
</div>
