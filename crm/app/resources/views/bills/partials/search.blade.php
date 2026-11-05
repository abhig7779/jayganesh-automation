{{-- Product search box + results + empty-box lists. $pc = desktop POS layout. --}}
@php $ref = $pc ? 'searchPc' : 'searchPhone'; @endphp
<div>
    <label for="{{ $ref }}" class="sr-only">Search product, SKU or service</label>
    <div @class([
        'flex items-center gap-3 rounded-lg border-2 bg-white px-3 focus-within:border-ink',
        'h-12 border-zinc-300' => $pc,
        'h-[52px] border-zinc-300' => ! $pc,
    ])>
        <x-bills.icon name="search" class="size-5 shrink-0 text-zinc-500" />
        <input id="{{ $ref }}" x-ref="{{ $ref }}" type="text" autocomplete="off" spellcheck="false" enterkeyhint="done"
               x-model="q" @input="onSearchInput()" @keydown="onSearchKey($event)"
               placeholder="Search product, SKU or service"
               class="h-full min-w-0 flex-1 border-0 bg-transparent text-[17px] font-medium text-ink placeholder:font-normal placeholder:text-zinc-500 focus:outline-none">
        <button type="button" x-show="q" @click="q=''; onSearchInput(); $refs.{{ $ref }}.focus()" class="grid size-8 place-items-center rounded text-zinc-500 hover:bg-zinc-100" aria-label="Clear search"><x-bills.icon name="x" class="size-4" /></button>
        @if ($pc)
            <span class="flex items-center gap-1.5 text-zinc-500" title="Barcode scanner works here"><x-bills.icon name="barcode" class="size-5" /><x-bills.kbd>/</x-bills.kbd></span>
        @endif
    </div>
    <p class="mt-1.5 flex items-center gap-1.5 text-xs text-zinc-600">
        <x-bills.icon name="tag" class="size-3.5" />
        Tip: <span class="font-semibold text-ink">2*gleam</span> adds 2 @if($pc)· scan a barcode any time · ↑↓ then Enter adds @endif
    </p>

    <template x-if="loadError">
        <div class="mt-3 flex items-center justify-between rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            <span>Couldn’t load the product list. Check the internet.</span>
            <button type="button" class="font-semibold underline" @click="loadError=null; window.location.reload()">Retry</button>
        </div>
    </template>

    {{-- Results --}}
    <div x-show="q.trim().length > 0 && results.length" class="mt-3 overflow-hidden rounded-lg border border-zinc-200 bg-white">
        <div class="flex items-center justify-between border-b border-zinc-200 bg-zinc-50 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">
            <span>Search results (<span x-text="results.length"></span>)</span>
            @if ($pc)<span class="flex items-center gap-3 normal-case tracking-normal">Navigate <x-bills.kbd>↑↓</x-bills.kbd> Select <x-bills.kbd>↵</x-bills.kbd></span>@endif
        </div>
        <ul role="listbox">
            <template x-for="(r, i) in results" :key="r.product.id">
                <li role="option" :aria-selected="i === active"
                    @mouseenter="active = i" @click="pick(r.product, 1)"
                    :class="i === active ? 'bg-zinc-50 shadow-[inset_3px_0_0_#111]' : 'bg-white'"
                    class="flex cursor-pointer items-center gap-3 border-b border-zinc-100 px-3 py-2.5 last:border-0">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate text-[15px] text-ink [&_b]:font-bold" x-html="r.html"></span>
                            <span class="rounded-full border px-2 py-px text-[11px] font-semibold" :class="stockClass(r.product)" x-text="stockLabel(r.product)" x-show="stockLabel(r.product)"></span>
                        </div>
                        <div class="mt-0.5 truncate text-xs text-zinc-600">
                            <span x-show="r.product.brand">Brand: <span class="font-medium text-zinc-700" x-text="r.product.brand"></span></span>
                            <span x-show="r.product.brand && r.product.sku"> · </span>
                            <span x-show="r.product.sku">SKU: <span class="font-mono" x-text="r.product.sku"></span></span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[11px] text-zinc-500 line-through tabular-nums" x-show="r.product.mrp_paise > r.product.price_paise" x-text="money(r.product.mrp_paise)"></div>
                        <div class="text-lg font-bold tabular-nums text-ink" x-text="money(r.product.price_paise)"></div>
                    </div>
                    @if ($pc)
                        <span class="w-16 text-right">
                            <x-bills.kbd dark x-show="i === active" class="h-6 px-1.5 text-[11px]">↵ Enter</x-bills.kbd>
                            <x-bills.icon name="plus" class="ml-auto size-5 text-zinc-400" x-show="i !== active" />
                        </span>
                    @endif
                </li>
            </template>
        </ul>
    </div>

    {{-- No match --}}
    <div x-show="noMatch" class="mt-3 rounded-lg border border-zinc-200 bg-white px-4 py-4">
        <p class="text-[15px] font-medium text-zinc-700">No match for “<span x-text="q"></span>”.
            <span class="text-zinc-600">Try fewer letters, or the brand name.</span></p>
        <template x-if="cfg.can.customItem">
            <div class="mt-3">
                <button type="button" class="btn-ghost" x-show="!custom.open" @click="custom.open = true; custom.name = q; $nextTick(() => $refs.customPrice{{ $pc ? 'Pc' : 'Phone' }}.focus())">
                    <x-bills.icon name="plus" class="size-4" /> Add as custom item?
                </button>
                <div x-show="custom.open" class="grid gap-2 sm:grid-cols-[1fr_120px_96px_auto]">
                    <input class="input" x-model="custom.name" placeholder="Item name" aria-label="Custom item name">
                    <input class="input text-right tabular-nums" x-ref="customPrice{{ $pc ? 'Pc' : 'Phone' }}" x-model="custom.price" inputmode="decimal" placeholder="₹ price" aria-label="Price incl. GST" @keydown.enter.prevent="addCustom()">
                    <select class="input" x-model="custom.gst" aria-label="GST rate">
                        <template x-for="r in [0,5,12,18,28]" :key="r"><option :value="r" x-text="r + '% GST'" :selected="r == custom.gst"></option></template>
                    </select>
                    <button type="button" class="btn-primary" @click="addCustom()">Add</button>
                </div>
            </div>
        </template>
    </div>

    {{-- Empty box: Recent, Popular, what this car model buys --}}
    <div x-show="!q.trim()">
        <template x-if="!loaded && !loadError">
            <div class="mt-5 grid {{ $pc ? 'grid-cols-4' : 'grid-cols-2' }} gap-2" aria-label="Loading products">
                @for ($i = 0; $i < ($pc ? 12 : 6); $i++)<div class="h-[72px] rounded-lg bg-zinc-100"></div>@endfor
            </div>
        </template>

        <template x-if="loaded && recent.length">
            <div class="mt-4">
                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Recent on this {{ $pc ? 'PC' : 'phone' }}</h3>
                <div class="flex flex-wrap gap-2">
                    <template x-for="p in recent" :key="'r'+p.id">
                        <button type="button" @click="pick(p)" class="inline-flex h-9 items-center gap-1.5 rounded-full border border-zinc-300 bg-white px-3 text-[13px] font-medium text-ink hover:border-ink">
                            <span class="max-w-44 truncate" x-text="p.name"></span><span class="text-zinc-600 tabular-nums" x-text="money(p.price_paise)"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="loaded && popular.length">
            <div class="mt-5">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Popular counter items</h3>
                    @if ($pc)<span class="text-xs text-zinc-500">Click a tile or type the SKU</span>@endif
                </div>
                @if ($pc)
                    <div class="grid grid-cols-4 gap-2">
                        <template x-for="p in popular" :key="'p'+p.id">
                            <button type="button" @click="pick(p)" :title="p.name + (p.brand ? ' · ' + p.brand : '')" class="flex h-[76px] flex-col justify-between rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left hover:border-ink focus-visible:border-ink focus-visible:outline-none">
                                <span class="line-clamp-2 text-[13px] font-semibold leading-tight text-ink" x-text="p.name"></span>
                                <span class="flex w-full items-end justify-between gap-1">
                                    <span class="text-[15px] font-bold tabular-nums text-ink" x-text="money(p.price_paise)"></span>
                                    <span class="shrink-0 rounded-full border px-1.5 text-[10px] font-semibold" :class="stockClass(p)" x-text="p.type === 'service' ? 'Service' : (p.track_stock ? (p.on_hand > 0 ? p.on_hand + ' left' : '0 left') : '')"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                @else
                    <div class="flex flex-wrap gap-2">
                        <template x-for="p in popular.slice(0, 8)" :key="'p'+p.id">
                            <button type="button" @click="pick(p)" class="inline-flex h-10 items-center gap-1.5 rounded-full border border-zinc-300 bg-white px-3 text-sm font-medium text-ink active:bg-zinc-100">
                                <span class="max-w-40 truncate" x-text="p.name"></span><span class="text-zinc-600 tabular-nums" x-text="'· ' + money(p.price_paise)"></span>
                            </button>
                        </template>
                    </div>
                @endif
            </div>
        </template>

        <template x-if="loaded && !popular.length">
            <div class="mt-6 rounded-lg border border-dashed border-zinc-300 bg-white px-4 py-6 text-center">
                <x-bills.icon name="cart" class="mx-auto size-8 text-zinc-500" />
                <p class="mt-2 text-[15px] font-medium text-zinc-700">No products yet. Add them in Stock, then they show up here.</p>
            </div>
        </template>

        <template x-if="loaded && carSuggestions.length">
            <div class="mt-5">
                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">
                    <span x-text="carModelName"></span> owners usually buy
                    <span class="ml-1 font-normal normal-case tracking-normal text-zinc-500" x-show="selectedCarLabel">· based on past bills</span>
                </h3>
                <div class="flex flex-wrap gap-2">
                    <template x-for="p in carSuggestions" :key="'s'+p.id">
                        <button type="button" @click="pick(p)" class="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-medium text-ink hover:border-ink">
                            <x-bills.icon name="plus" class="size-4 text-zinc-500" /><span class="max-w-52 truncate" x-text="p.name"></span><span class="font-bold tabular-nums" x-text="money(p.price_paise)"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>
