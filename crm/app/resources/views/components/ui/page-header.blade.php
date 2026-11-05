{{--
    <x-ui.page-header title="Customers" subtitle="248 people" :back="route('dashboard')">
        <x-slot:actions> buttons… </x-slot:actions>
    </x-ui.page-header>
    Phone: a 34 px large title (DESIGN §3 display) with an optional back chevron above it.
    PC: a 24 px title with the actions on the right. `subtitle` is one muted line.
--}}
@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Back', 'actions' => null])
<div {{ $attributes->merge(['class' => 'mb-5 lg:mb-6']) }}>
    @if ($back)
        <a href="{{ $back }}" class="-ml-2 mb-1 inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-[15px] font-medium text-ink-2 hover:text-ink lg:min-h-8 lg:text-sm">
            <x-icon name="chevron-left" class="size-5 lg:size-4" /> {{ $backLabel }}
        </a>
    @endif
    <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
        <div class="min-w-0">
            <h1 class="display-title truncate lg:text-2xl lg:leading-8 lg:tracking-[-0.01em]">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1 text-[15px] text-muted lg:text-sm">{{ $subtitle }}</p>
            @endif
        </div>
        @if ($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endif
    </div>
</div>
