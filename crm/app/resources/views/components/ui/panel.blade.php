{{--
    <x-ui.panel title="Recent bills" subtitle="Last 5 today">
        <x-slot:aside><a class="btn-quiet" href="…">See all</a></x-slot:aside>
        …body…
    </x-ui.panel>
    A white card with a title row (title 15/600, optional one-line subtitle, optional right-hand
    `aside` for a count pill, filter or "See all" link). `flush` removes the body padding for
    lists and tables that run edge to edge.
--}}
@props(['title', 'subtitle' => null, 'aside' => null, 'flush' => false])
<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-xl border border-line bg-surface']) }}>
    <header class="flex items-center justify-between gap-3 px-4 pt-3.5 pb-3 lg:px-5">
        <div class="min-w-0">
            <h2 class="truncate text-[15px] leading-5 font-semibold text-ink">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 truncate text-xs font-medium text-muted">{{ $subtitle }}</p>
            @endif
        </div>
        @if ($aside)
            <div class="flex shrink-0 items-center gap-2">{{ $aside }}</div>
        @endif
    </header>
    <div @class(['px-4 pb-4 lg:px-5' => ! $flush, 'border-t border-line' => $flush])>{{ $slot }}</div>
</section>
