{{--
    Global search (the shell's box):  <x-ui.search-input global />
        Live results from route('search') drop down under the box as you type (htmx), grouped by
        type. ↑ ↓ move, Enter opens the highlighted (or first) result, Esc closes. Pressing `/`
        anywhere focuses the first visible global box. Enter with no results opens the full page.
    Plain list filter, inside your own GET form:
        <x-ui.search-input name="q" :value="request('q')" placeholder="Name, mobile or car number" />
        Magnifier, a clear × that resubmits the form, and the same 52 / 40 px input.
--}}
@props(['name' => 'q', 'value' => '', 'placeholder' => 'Mobile no., name or car number', 'global' => false, 'autofocus' => false])
@php $id = 'search-'.\Illuminate\Support\Str::random(6); @endphp
@if ($global)
<form action="{{ route('search') }}" method="GET" role="search" {{ $attributes->merge(['class' => 'relative']) }}
      x-data="searchBox" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
      @keydown.enter.prevent="go($event)" @keydown.escape="close()" @click.outside="close()">
    <label for="{{ $id }}" class="sr-only">Search customers, cars, bills and products</label>
    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted lg:left-3 lg:size-[18px]" />
    <input id="{{ $id }}" name="q" type="search" value="{{ $value }}" autocomplete="off" enterkeyhint="search"
           placeholder="{{ $placeholder }}" data-search @if ($autofocus) autofocus @endif
           class="input pr-10 pl-11 lg:pl-9 [&::-webkit-search-cancel-button]:hidden"
           hx-get="{{ route('search') }}" hx-trigger="input changed delay:180ms" hx-target="#{{ $id }}-results"
           hx-swap="innerHTML" hx-sync="this:replace"
           @input="open = $event.target.value.trim().length > 0; active = -1" @focus="open = $event.target.value.trim().length > 0 && $refs.results.innerHTML.trim() !== ''"
           role="combobox" aria-autocomplete="list" aria-controls="{{ $id }}-results" :aria-expanded="open.toString()">
    <span class="kbd pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 lg:inline-flex" x-show="!open">/</span>
    <div id="{{ $id }}-results" x-ref="results" x-show="open" x-cloak role="listbox"
         class="absolute inset-x-0 top-full z-40 mt-2 max-h-[min(70dvh,560px)] overflow-y-auto rounded-xl border border-line bg-surface shadow-pop"></div>
</form>
@else
<div {{ $attributes->merge(['class' => 'relative']) }} x-data="{ v: @js((string) $value) }">
    <label for="{{ $id }}" class="sr-only">{{ $placeholder }}</label>
    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted lg:left-3 lg:size-[18px]" />
    <input id="{{ $id }}" name="{{ $name }}" type="search" x-model="v" autocomplete="off" enterkeyhint="search"
           placeholder="{{ $placeholder }}" @if ($autofocus) autofocus @endif
           class="input pr-11 pl-11 lg:pl-9 [&::-webkit-search-cancel-button]:hidden">
    <button type="button" x-show="v" x-cloak @click="v = ''; $nextTick(() => { const f = $el.closest('form'); f ? f.requestSubmit() : $el.previousElementSibling.focus() })"
            class="absolute top-1/2 right-1.5 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-md text-muted hover:text-ink" aria-label="Clear search">
        <x-icon name="x" class="size-5" />
    </button>
</div>
@endif
