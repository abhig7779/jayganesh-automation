@php use App\Support\SearchText; @endphp
@if (! $searching && mb_strlen($q) === 1)
    <p class="px-1 py-2 text-sm text-zinc-600">Type one more letter…</p>
@endif

@if ($searching && $products->isEmpty())
    <div class="rounded-xl border border-zinc-200 bg-white px-6 py-10 text-center">
        <p class="text-[15px] font-medium text-zinc-700">Nothing matches "{{ $q }}".</p>
        <p class="mt-1 text-sm text-zinc-600">Try the brand ("dhc"), the car ("creta") or fewer letters.</p>
    </div>
@elseif ($products->isEmpty())
    <div class="rounded-xl border border-zinc-200 bg-white px-6 py-10 text-center">
        <p class="text-[15px] font-medium text-zinc-700">No products yet. Ask the owner to add the price list.</p>
    </div>
@else
    <h2 class="mb-1.5 mt-2 px-1 text-xs font-semibold uppercase tracking-wider text-zinc-600">
        {{ $searching ? $products->count().($products->count() === 30 ? '+' : '').' found' : 'Most sold' }}
    </h2>
    <ul class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white [&_mark]:rounded-sm [&_mark]:bg-brand-100 [&_mark]:text-ink">
        @foreach ($products as $p)
            <li>
                <button type="button" data-row hx-get="{{ route('products.show', $p) }}" hx-target="#product-panel"
                        hx-on::before-request="window.dispatchEvent(new CustomEvent('product-open'))"
                        class="flex min-h-[76px] w-full items-center gap-3 px-4 py-3 text-left hover:bg-zinc-50 focus:bg-brand-50 focus:outline-none active:bg-zinc-100">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[16px] font-semibold leading-5 text-ink">{{ SearchText::highlight($p->name, $q) }}</span>
                        <span class="mt-0.5 block truncate text-[13px] font-medium text-zinc-600">
                            {{ SearchText::highlight(collect([$p->brand?->name, $p->sku])->filter()->implode(' · '), $q) }}@if ($p->shelf) · Shelf {{ $p->shelf }}@endif
                        </span>
                    </span>
                    <span class="flex shrink-0 flex-col items-end gap-1">
                        <x-products.price :product="$p" size="lg" stack />
                        <x-products.stock-pill :product="$p" size="sm" />
                    </span>
                </button>
            </li>
        @endforeach
    </ul>
@endif
