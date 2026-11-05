{{--
    <x-ui.money :paise="1234500" size="md" />  →  ₹12,345
    Indian grouping via App\Support\Money, tabular numbers. size: sm | md | lg | xl (DESIGN §3 money-*).
    Negative amounts use a real minus and the `bad` colour. `tone` = bad | ok | muted forces a colour.
--}}
@props(['paise' => 0, 'size' => 'md', 'tone' => null])
@php
    $paise = (int) $paise;
    $sizes = [
        'sm' => 'text-[13px] font-semibold',
        'md' => 'text-[15px] leading-5 font-semibold',
        'lg' => 'text-2xl leading-7 font-bold tracking-[-0.01em]',
        'xl' => 'text-[40px] leading-[44px] font-bold tracking-[-0.02em]',
    ];
    $tone = $tone ?? ($paise < 0 ? 'bad' : null);
    $toneClass = match ($tone) { 'bad' => 'text-bad', 'ok' => 'text-ok', 'muted' => 'text-muted', default => '' };
    $text = ($paise < 0 ? '−' : '').\App\Support\Money::format(abs($paise));
@endphp
<span {{ $attributes->merge(['class' => trim('tnum whitespace-nowrap '.($sizes[$size] ?? $sizes['md']).' '.$toneClass)]) }}>{{ $text }}</span>
