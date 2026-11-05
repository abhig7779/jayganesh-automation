@php
    use App\Support\Money;
    use App\Support\SearchText;
    $q = $filters['q'];
    $base = array_filter(array_merge($filters, ['hidden' => $filters['hidden'] ?: null, 'stock' => $filters['show'] ? null : $filters['stock'], 'type' => $filters['show'] ? null : $filters['type']]), fn ($v) => $v !== null && $v !== '');
    $sortLink = function (string $col) use ($filters, $base) {
        $dir = $filters['sort'] === $col ? ($filters['dir'] === 'asc' ? 'desc' : 'asc') : (in_array($col, ['name', 'brand'], true) ? 'asc' : 'desc');

        return route('products.index', array_merge($base, ['sort' => $col, 'dir' => $dir]));
    };
    $arrow = fn (string $col) => $filters['sort'] === $col ? ($filters['dir'] === 'asc' ? '↑' : '↓') : '';
    $th = 'sticky top-0 z-10 border-b border-zinc-200 bg-zinc-100 px-3 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-600';
    $sortable = fn (string $col, string $label, string $title = '') => '<a href="'.e($sortLink($col)).'" hx-get="'.e($sortLink($col)).'" hx-target="#product-results" hx-push-url="true" class="hover:text-ink"'.($title ? ' title="'.e($title).'"' : '').'>'.e($label).' '.$arrow($col).'</a>';
    $selectedId = request()->header('HX-Request') ? null : $products->first()?->id;
@endphp

@if (request()->header('HX-Request'))
    <input type="hidden" name="sort" id="f-sort" value="{{ $filters['sort'] }}" hx-swap-oob="true">
    <input type="hidden" name="dir" id="f-dir" value="{{ $filters['dir'] }}" hx-swap-oob="true">
    <span id="showing-count" hx-swap-oob="true" class="hidden whitespace-nowrap text-xs text-zinc-600 tabular-nums xl:inline">Showing {{ $products->count() }} of {{ number_format($totalActive) }}</span>
@endif

@if ($products->isEmpty())
    <div class="rounded-xl border border-zinc-200 bg-white px-6 py-12 text-center">
        <svg class="mx-auto size-8 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
        @if ($q !== '')
            <p class="mt-3 text-[15px] font-medium text-zinc-700">Nothing matches "{{ $q }}". Try fewer letters, the brand, or the code.</p>
        @elseif (array_filter([$filters['stock'], $filters['category'], $filters['brand'], $filters['type'], $filters['hidden']]))
            <p class="mt-3 text-[15px] font-medium text-zinc-700">No items with these filters.</p>
            <a href="{{ route('products.index') }}" class="btn-ghost mt-4">Clear filters</a>
        @else
            <p class="mt-3 text-[15px] font-medium text-zinc-700">No products yet. Add one, or import the price list from Excel.</p>
            @can('inventory.manage')
                <div class="mt-4 flex justify-center gap-2">
                    <a href="{{ route('products.import') }}" class="btn-ghost">Import price list</a>
                    <a href="{{ route('products.create') }}" class="btn-primary"><x-icon name="plus" /> Add product</a>
                </div>
            @endcan
        @endif
    </div>
@else
    {{-- Phone: card rows; tap opens the bottom sheet --}}
    <ul class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white lg:hidden">
        @foreach ($products as $p)
            <li>
                <button type="button" data-row hx-get="{{ route('products.show', $p) }}" hx-target="#product-panel" hx-on::before-request="window.dispatchEvent(new CustomEvent('product-open'))"
                        class="flex min-h-[72px] w-full items-center gap-3 px-4 py-3 text-left active:bg-zinc-50 [&_mark]:rounded-sm [&_mark]:bg-brand-100 [&_mark]:text-ink">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[15px] font-semibold leading-5 text-ink">{{ SearchText::highlight($p->name, $q) }}</span>
                        <span class="mt-0.5 block truncate text-[13px] font-medium text-zinc-600">
                            {{ SearchText::highlight(collect([$p->brand?->name, $p->shelf ? 'Shelf '.$p->shelf : null])->filter()->implode(' · ') ?: ($p->category?->name ?? ''), $q) }}
                        </span>
                        <x-products.stock-pill :product="$p" size="sm" class="mt-1.5" />
                    </span>
                    <x-products.price :product="$p" size="lg" stack class="items-end" />
                </button>
            </li>
        @endforeach
    </ul>

    {{-- PC: dense sortable table; ↑/↓ or a click shows the item in the right panel --}}
    <div class="hidden overflow-clip rounded-xl border border-zinc-200 bg-white lg:block">
        <table class="w-full table-fixed border-separate border-spacing-0 text-[13px]">
            <thead>
                <tr class="text-left">
                    <th class="{{ $th }}">{!! $sortable('name', 'Item & brand') !!}</th>
                    <th class="{{ $th }} w-[128px]">SKU</th>
                    <th class="{{ $th }} hidden w-[110px] 2xl:table-cell">Category</th>
                    <th class="{{ $th }} w-[72px] text-right">MRP</th>
                    <th class="{{ $th }} w-[104px] text-right">{!! $sortable('price', 'Our price') !!}</th>
                    <th class="{{ $th }} w-[108px] text-right">{!! $sortable('stock', 'Stock') !!}</th>
                </tr>
            </thead>
            <tbody class="[&_mark]:rounded-sm [&_mark]:bg-brand-100 [&_mark]:text-ink">
                @foreach ($products as $p)
                    @php $off = $p->discountPercent(); @endphp
                    <tr data-row tabindex="0" data-url="{{ route('products.show', $p) }}" @if ($p->id === $selectedId) data-selected @endif
                        hx-get="{{ route('products.show', $p) }}" hx-target="#product-panel" hx-trigger="focus" hx-sync="#product-panel:replace"
                        class="group cursor-pointer outline-none hover:bg-zinc-50 data-selected:bg-zinc-100 focus-visible:bg-zinc-100 [&>td]:border-b [&>td]:border-zinc-200 [&>td]:px-3 [&>td]:py-2 [&:last-child>td]:border-b-0">
                        <td class="relative">
                            <span class="absolute inset-y-0 left-0 w-[3px] bg-ink opacity-0 group-data-selected:opacity-100"></span>
                            <div class="line-clamp-2 font-semibold leading-[18px] text-ink">{{ SearchText::highlight($p->name, $q) }}</div>
                            <div class="truncate text-xs text-zinc-600">
                                @if ($p->brand) Brand: <span class="font-medium text-zinc-700">{{ SearchText::highlight($p->brand->name, $q) }}</span>@endif<span class="2xl:hidden">{{ $p->brand && $p->category ? ' · ' : '' }}{{ $p->category?->name }}</span>
                            </div>
                        </td>
                        <td class="truncate text-xs font-medium tabular-nums text-zinc-700" title="{{ $p->sku }}">{{ SearchText::highlight($p->sku ?? '—', $q) }}</td>
                        <td class="hidden truncate text-zinc-700 2xl:table-cell">{{ $p->category?->name }}</td>
                        <td class="text-right tabular-nums text-zinc-500">
                            @if ($off > 0) <s>{{ Money::format($p->mrp_paise) }}</s> @elseif ($p->mrp_paise) {{ Money::format($p->mrp_paise) }} @endif
                        </td>
                        <td class="text-right">
                            <div class="font-semibold tabular-nums text-ink">{{ Money::format($p->price_paise) }}</div>
                            @if ($off > 0)
                                <div class="text-[11px] font-semibold text-green-700">{{ $off }}% off</div>
                            @endif
                            @if ($canMargins && $p->cost_paise !== null)
                                <div class="text-[11px] tabular-nums text-zinc-600" title="Margin">m {{ Money::format($p->price_paise - $p->cost_paise) }}</div>
                            @endif
                        </td>
                        <td class="text-right"><x-products.stock-pill :product="$p" size="sm" :units="false" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="flex items-center justify-between gap-3 border-t border-zinc-200 bg-zinc-50 px-3 py-2 text-xs text-zinc-600">
            <span>Press <kbd class="rounded border border-zinc-300 bg-white px-1">↑</kbd> <kbd class="rounded border border-zinc-300 bg-white px-1">↓</kbd> to move through rows</span>
            <span class="flex items-center gap-3">
                @if ($canMargins && $inventoryValue !== null)
                    <span>Inventory value: <strong class="tabular-nums text-ink">{{ Money::format($inventoryValue) }}</strong></span>
                @endif
                @if ($canMargins)
                    <a href="{{ $sortLink('margin') }}" hx-get="{{ $sortLink('margin') }}" hx-target="#product-results" hx-push-url="true" class="underline underline-offset-2 hover:text-ink">Sort by margin</a>
                @endif
            </span>
        </div>
    </div>

    {{-- Paging --}}
    <div class="mt-3 flex items-center justify-between gap-3 text-[13px] text-zinc-600">
        <span class="tabular-nums">
            @if ($products->total() > $products->perPage())
                {{ $products->firstItem() }}–{{ $products->lastItem() }} of {{ number_format($products->total()) }}
            @else
                {{ $products->total() }} {{ \Illuminate\Support\Str::plural('item', $products->total()) }}
            @endif
            @can('inventory.manage')
                · <a href="{{ route('products.index', $filters['hidden'] ? [] : ['hidden' => 1]) }}" class="underline underline-offset-2 hover:text-ink">{{ $filters['hidden'] ? 'Back to shown items' : 'Hidden items' }}</a>
            @endcan
        </span>
        <span class="flex gap-2">
            @if ($products->previousPageUrl())
                <a href="{{ $products->previousPageUrl() }}" hx-get="{{ $products->previousPageUrl() }}" hx-target="#product-results" hx-push-url="true" class="btn-ghost min-h-9">Previous</a>
            @endif
            @if ($products->nextPageUrl())
                <a href="{{ $products->nextPageUrl() }}" hx-get="{{ $products->nextPageUrl() }}" hx-target="#product-results" hx-push-url="true" class="btn-ghost min-h-9">Next</a>
            @endif
        </span>
    </div>
@endif
