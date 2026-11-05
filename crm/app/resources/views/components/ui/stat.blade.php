{{--
    <x-ui.stat label="Sales" :paise="4865000" hint="₹4,200 more than last Thursday" trend="up" />
    <x-ui.stat label="Bills" value="9" />
    A caption, one big number (money-lg) and one plain-words line under it. No sparklines.
    `paise` formats as money; otherwise `value` shows as given. `tone="bad"` makes the number red.
    `trend` (up|down) adds a small arrow to the hint in ok/bad colour. `soon` shows a quiet dash
    for a number whose module isn't switched on yet. `href` makes the whole stat a link.
--}}
@props(['label', 'value' => null, 'paise' => null, 'hint' => null, 'trend' => null, 'tone' => null, 'soon' => false, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'block min-w-0']) }}>
    <div class="truncate text-[13px] font-medium text-muted">{{ $label }}</div>
    <div class="mt-1">
        @if ($soon)
            <span class="text-2xl leading-7 font-bold text-line-strong" title="Switches on with its module">—</span>
        @elseif ($paise !== null)
            <x-ui.money :paise="$paise" size="lg" :tone="$tone" />
        @else
            <span @class(['tnum text-2xl leading-7 font-bold tracking-[-0.01em]', 'text-bad' => $tone === 'bad'])>{{ $value }}</span>
        @endif
    </div>
    @if ($hint)
        <div @class(['mt-1 flex items-start gap-1 text-xs font-medium', 'text-muted' => ! $trend, 'text-ok' => $trend === 'up', 'text-bad' => $trend === 'down'])>
            @if ($trend) <x-icon :name="'trend-'.$trend" class="size-3.5 shrink-0" stroke="2" /> @endif
            <span class="line-clamp-2">{{ $hint }}</span>
        </div>
    @endif
</{{ $tag }}>
