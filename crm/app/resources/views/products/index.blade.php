<x-layouts.app title="Stock & prices">
    <x-slot:header>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <h1 class="text-[28px] font-bold leading-9 tracking-tight lg:text-[22px] lg:leading-7">Stock &amp; prices</h1>
            <span class="inline-flex items-center gap-2 rounded-md border border-zinc-200 bg-white px-2 py-0.5 text-xs font-medium text-zinc-700">
                <span class="tabular-nums">{{ number_format($counts['all']) }} items</span>
                @if ($counts['low']) <span class="text-amber-700 tabular-nums">{{ $counts['low'] }} low</span> @endif
                @if ($counts['out']) <span class="inline-flex items-center gap-1 text-red-600 tabular-nums"><span class="size-1.5 rounded-full bg-red-600"></span>{{ $counts['out'] }} out of stock</span> @endif
            </span>
        </div>
        @if ($canManage)
            <div class="flex gap-2">
                <a href="{{ route('products.import') }}" class="btn-ghost min-h-10">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                    Import price list
                </a>
                <a href="{{ route('products.create') }}" class="btn-primary min-h-10"><x-icon name="plus" class="size-4" /> Add product</a>
            </div>
        @endif
    </x-slot:header>

    {{-- Search + filters. Every change reloads only the results (htmx). --}}
    <form id="product-filters" method="GET" action="{{ route('products.index') }}"
          hx-get="{{ route('products.index') }}" hx-target="#product-results" hx-push-url="true"
          hx-trigger="input changed delay:150ms from:#product-q, change, submit"
          hx-indicator="#product-results"
          class="sticky top-[52px] z-20 -mx-4 mb-3 space-y-2 border-b border-zinc-200 bg-zinc-100 px-4 py-2 sm:-mx-6 sm:px-6 lg:static lg:mx-0 lg:space-y-3 lg:border-0 lg:bg-transparent lg:p-0">
        <div class="relative">
            <svg class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            <input id="product-q" name="q" value="{{ $filters['q'] }}" data-search type="text" inputmode="search" autocomplete="off" autofocus
                   placeholder="Search name, brand, code or other name"
                   class="block h-12 w-full rounded-xl border border-zinc-300 bg-white pl-12 pr-12 text-[15px] text-ink placeholder:text-zinc-500 focus:border-ink focus:outline-none focus:ring-2 focus:ring-brand-400"
                   aria-label="Search products">
            <kbd class="pointer-events-none absolute right-4 top-1/2 hidden -translate-y-1/2 rounded border border-zinc-300 bg-zinc-50 px-1.5 text-[11px] font-semibold text-zinc-600 lg:block">/</kbd>
        </div>

        <div class="flex items-center gap-2 overflow-x-auto pb-0.5 lg:overflow-visible lg:pb-0">
            @php
                $chips = [
                    '' => ['All', null, 'border-zinc-300 text-zinc-700', null],
                    'low' => ['Low stock', $counts['low'], 'border-amber-200 bg-amber-50 text-amber-700', 'bg-amber-500'],
                    'out' => ['Out of stock', $counts['out'], 'border-red-200 bg-red-50 text-red-600', 'bg-red-600'],
                    'services' => ['Services', null, 'border-zinc-300 text-zinc-700', null],
                ];
                $currentShow = $filters['show'] ?? ($filters['stock'] && in_array($filters['stock'], ['low', 'out']) ? $filters['stock'] : '');
            @endphp
            @foreach ($chips as $val => [$label, $n, $cls, $dot])
                <label class="shrink-0 cursor-pointer">
                    <input type="radio" name="show" value="{{ $val }}" class="peer sr-only" @checked((string) $currentShow === $val)>
                    <span class="inline-flex min-h-8 items-center gap-1.5 rounded-md border bg-white px-2.5 text-[13px] font-medium {{ $cls }} peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-400">
                        @if ($dot) <span class="size-1.5 rounded-full {{ $dot }}"></span> @endif
                        {{ $label }}@if ($n !== null) <span class="tabular-nums">({{ $n }})</span>@endif
                    </span>
                </label>
            @endforeach

            <div class="ml-auto flex shrink-0 items-center gap-2">
                <select name="category" class="input min-h-8 w-auto py-0 pl-2.5 pr-8 text-[13px] lg:min-h-8" aria-label="Category">
                    <option value="">Category: All</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected($filters['category'] === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <select name="brand" class="input min-h-8 w-auto py-0 pl-2.5 pr-8 text-[13px] lg:min-h-8" aria-label="Brand">
                    <option value="">Brand: All</option>
                    @foreach ($brands as $b)
                        <option value="{{ $b->id }}" @selected($filters['brand'] === $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
                <span id="showing-count" class="hidden whitespace-nowrap text-xs text-zinc-600 tabular-nums xl:inline">Showing {{ $products->count() }} of {{ number_format($totalActive) }}</span>
            </div>
        </div>
        <input type="hidden" name="sort" id="f-sort" value="{{ $filters['sort'] }}">
        <input type="hidden" name="dir" id="f-dir" value="{{ $filters['dir'] }}">
        @if ($filters['hidden']) <input type="hidden" name="hidden" value="1"> @endif
    </form>

    <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start lg:gap-4 2xl:grid-cols-[minmax(0,1fr)_400px]">
        <div id="product-results" class="min-w-0 transition-opacity [&.htmx-request]:opacity-60">
            @include('products.partials.results')
        </div>

        <x-products.panel docked>
            @if ($detail)
                @include('products.partials.detail', $detail + ['inPanel' => true])
            @endif
        </x-products.panel>
    </div>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        (() => {
            const desktop = () => window.matchMedia('(min-width: 1024px)').matches;
            const rows = () => [...document.querySelectorAll('#product-results [data-row]')].filter((r) => r.offsetParent !== null);
            const select = (row) => {
                document.querySelectorAll('#product-results [data-selected]').forEach((r) => r.removeAttribute('data-selected'));
                row?.setAttribute('data-selected', '');
            };

            // Keyboard on the counter PC: ↓ from search goes into the rows, ↑/↓ move (and open), Enter opens.
            document.addEventListener('keydown', (e) => {
                const list = rows();
                if (!list.length) return;
                const i = list.indexOf(document.activeElement);
                if (e.key === 'ArrowDown' && (document.activeElement?.id === 'product-q' || i >= 0)) {
                    e.preventDefault();
                    list[Math.min(i + 1, list.length - 1)].focus();
                } else if (e.key === 'ArrowUp' && i >= 0) {
                    e.preventDefault();
                    i === 0 ? document.getElementById('product-q').focus() : list[i - 1].focus();
                } else if (e.key === 'Enter' && document.activeElement?.id === 'product-q') {
                    e.preventDefault();
                    desktop() ? list[0].focus() : list[0].click();
                }
            });
            document.addEventListener('focusin', (e) => { if (e.target.matches?.('#product-results [data-row]')) select(e.target); });

            // After a new search on the PC, show the top result in the panel.
            document.body.addEventListener('htmx:afterSwap', (e) => {
                if (e.detail.target.id !== 'product-results' || !desktop()) return;
                const first = rows()[0];
                if (first) {
                    select(first);
                    htmx.ajax('GET', first.getAttribute('data-url'), { target: '#product-panel', swap: 'innerHTML' });
                }
            });
        })();
    </script>
</x-layouts.app>
