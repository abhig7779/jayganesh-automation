{{--
    Radio mode (inside a form):
        <x-ui.segmented name="source" :options="['walk_in' => 'Walk-in', …]" :value="old('source', 'walk_in')" />
    Link mode (list filters): `links` is [label => url], `active` is the selected label.
        <x-ui.segmented :links="['Any time' => $u1, 'Last 30 days' => $u2]" active="Any time" />
    Track `sunken`, the selected segment white with `ink` text (DESIGN §5 Segmented control).
    `wrap` lets many options flow onto a second line instead of scrolling sideways.
--}}
@props(['name' => null, 'options' => [], 'value' => null, 'links' => null, 'active' => null, 'wrap' => false, 'label' => null])
<div {{ $attributes->merge(['class' => 'inline-flex max-w-full gap-1 rounded-lg bg-sunken p-1 '.($wrap ? 'flex-wrap' : 'overflow-x-auto')]) }}
     role="{{ $links ? 'group' : 'radiogroup' }}" @if ($label) aria-label="{{ $label }}" @endif>
    @if ($links)
        @foreach ($links as $text => $url)
            <a href="{{ $url }}" @class([
                'inline-flex min-h-10 shrink-0 items-center justify-center rounded-md border px-3 text-[13px] font-semibold whitespace-nowrap transition-colors lg:min-h-8',
                'border-line bg-surface text-ink' => $text === $active,
                'border-transparent text-muted hover:text-ink' => $text !== $active,
            ]) @if ($text === $active) aria-current="true" @endif>{{ $text }}</a>
        @endforeach
    @else
        @foreach ($options as $optValue => $optLabel)
            <label class="relative shrink-0 cursor-pointer">
                <input type="radio" name="{{ $name }}" value="{{ $optValue }}" class="peer sr-only" @checked((string) $value === (string) $optValue)>
                <span class="inline-flex min-h-10 items-center justify-center rounded-md border border-transparent px-3 text-[13px] font-semibold whitespace-nowrap text-muted transition-colors peer-checked:border-line peer-checked:bg-surface peer-checked:text-ink peer-focus-visible:ring-2 peer-focus-visible:ring-ink lg:min-h-8">{{ $optLabel }}</span>
            </label>
        @endforeach
    @endif
</div>
