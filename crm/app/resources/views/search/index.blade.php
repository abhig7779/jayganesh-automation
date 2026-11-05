{{-- Full search page (phone search tab target, and "See all results" from the dropdown). --}}
<x-layouts.app title="Search">
    <x-ui.page-header title="Search" subtitle="Customers, cars, job cards, bills, quotations and products" />

    <form method="GET" action="{{ route('search') }}" class="mb-5 max-w-2xl"
          hx-get="{{ route('search') }}" hx-trigger="input delay:200ms from:#search-page-q" hx-target="#search-page-results" hx-select="#search-page-results" hx-swap="outerHTML" hx-push-url="true">
        <input type="hidden" name="stay" value="1">
        <div x-data="{ v: @js($q) }" class="relative">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted lg:left-3 lg:size-[18px]" />
            <input id="search-page-q" name="q" type="search" x-model="v" autofocus autocomplete="off" enterkeyhint="search" data-search
                   class="input pr-11 pl-11 lg:pl-9 [&::-webkit-search-cancel-button]:hidden" placeholder="Mobile no., name, car number, bill or product">
            <button type="button" x-show="v" x-cloak @click="v = ''; $nextTick(() => $el.previousElementSibling.focus())" class="absolute top-1/2 right-1.5 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-md text-muted hover:text-ink" aria-label="Clear"><x-icon name="x" class="size-5" /></button>
        </div>
    </form>

    <div id="search-page-results" class="max-w-2xl" aria-live="polite">
        @include('search.results', ['compact' => false])
    </div>
</x-layouts.app>
