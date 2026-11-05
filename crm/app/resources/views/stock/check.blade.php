<x-layouts.app title="Check stock">
    <div class="mx-auto max-w-2xl">
        <h1 class="text-[28px] font-bold leading-9 tracking-tight lg:text-[22px] lg:leading-7">Check stock &amp; price</h1>
        <p class="mb-3 text-sm text-zinc-600">Type 2 letters of the name, brand, code or what customers call it.</p>

        <form method="GET" action="{{ route('stock.check') }}" role="search"
              hx-get="{{ route('stock.check') }}" hx-target="#stock-results" hx-push-url="true"
              hx-trigger="input changed delay:150ms from:#stock-q, submit"
              hx-indicator="#stock-results"
              class="sticky top-[52px] z-20 -mx-4 bg-zinc-100 px-4 py-2 sm:-mx-6 sm:px-6 lg:top-0 lg:mx-0 lg:px-0">
            <div class="relative">
                <svg class="pointer-events-none absolute left-4 top-1/2 size-6 -translate-y-1/2 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                <input id="stock-q" name="q" value="{{ $q }}" type="text" inputmode="search" data-search autofocus autocomplete="off" enterkeyhint="search"
                       placeholder="glea, creta mat, ppf…"
                       class="block h-14 w-full rounded-xl border border-zinc-300 bg-white pl-12 pr-12 text-[17px] text-ink placeholder:text-zinc-500 focus:border-ink focus:outline-none focus:ring-2 focus:ring-brand-400"
                       aria-label="Search stock">
                <button type="button" data-clear-input="stock-q"
                        class="absolute right-2 top-1/2 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100" aria-label="Clear search">
                    <x-icon name="x" />
                </button>
            </div>
        </form>

        <div id="stock-results" class="mt-2 transition-opacity [&.htmx-request]:opacity-60">
            @include('stock.partials.results')
        </div>
    </div>

    <x-products.panel />

    <script nonce="{{ Vite::cspNonce() }}">
        document.getElementById('stock-q')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); document.querySelector('#stock-results [data-row]')?.click(); }
        });
    </script>
</x-layouts.app>
