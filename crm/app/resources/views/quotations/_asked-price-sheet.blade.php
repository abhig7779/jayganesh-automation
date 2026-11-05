{{-- One-tap "Asked price" (UX-FLOWS §3.2 step 1): mobile + product + price told → a lead with follow-ups. --}}
<div x-show="asked" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="asked = false">
    <div class="absolute inset-0 bg-[rgba(17,17,17,.4)]" @click="asked = false"></div>
    <div class="absolute inset-x-0 bottom-0 max-h-[92dvh] overflow-y-auto rounded-t-2xl bg-white px-4 pb-6 pt-2 shadow-[0_-8px_24px_rgba(0,0,0,.08)] lg:inset-auto lg:left-1/2 lg:top-24 lg:w-[480px] lg:-translate-x-1/2 lg:rounded-xl lg:border lg:border-zinc-200 lg:px-6 lg:pt-5"
         x-data="askedPrice(@js(['productsUrl' => route('leads.product-index'), 'old' => ['mobile' => old('mobile'), 'what' => old('what'), 'price' => old('price'), 'product_id' => old('product_id'), 'source' => old('source', 'walk_in_enquiry')]]))"
         x-effect="if (asked) $nextTick(() => $refs.mobile.focus())">
        <div class="mx-auto mb-3 h-1 w-9 rounded-full bg-zinc-300 lg:hidden"></div>
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-[22px] font-bold leading-7">Asked price</h2>
                <p class="text-sm text-zinc-600">Log a price told on the phone or at the counter. It joins the follow-up list.</p>
            </div>
            <button type="button" @click="asked = false" class="grid size-11 shrink-0 place-items-center rounded-lg" aria-label="Close"><x-quotations.icon name="x" class="size-6" /></button>
        </div>

        <form method="POST" action="{{ route('quotations.asked-price') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-semibold text-zinc-700" for="ap-mobile">Mobile</label>
                <div class="flex min-h-[52px] items-center rounded-lg border {{ $errors->has('mobile') ? 'border-red-600' : 'border-zinc-300' }} focus-within:ring-2 focus-within:ring-ink lg:min-h-11">
                    <span class="pl-3 pr-2 font-semibold text-zinc-600">+91</span>
                    <input id="ap-mobile" x-ref="mobile" name="mobile" x-model="mobile" inputmode="numeric" required maxlength="14" placeholder="98231 07676"
                           class="h-full min-w-0 flex-1 bg-transparent pr-3 text-[17px] font-semibold tabular-nums placeholder:font-normal placeholder:text-zinc-500 focus:outline-none">
                </div>
                @error('mobile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="relative">
                <label class="mb-1 block text-sm font-semibold text-zinc-700" for="ap-what">What did they ask for?</label>
                <input type="hidden" name="product_id" :value="productId">
                <input id="ap-what" name="what" x-model="what" @input="productId = null; search()" autocomplete="off"
                       @keydown.arrow-down.prevent="hi = Math.min(hi + 1, results.length - 1)" @keydown.arrow-up.prevent="hi = Math.max(hi - 1, 0)"
                       @keydown.enter="if (results.length && !productId) { $event.preventDefault(); choose(results[hi]); }"
                       placeholder="Type a product, or describe it" class="block min-h-[52px] w-full rounded-lg border {{ $errors->has('what') ? 'border-red-600' : 'border-zinc-300' }} px-3 text-[15px] placeholder:text-zinc-500 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink lg:min-h-11">
                <div x-show="results.length && !productId" class="mt-1 overflow-hidden rounded-lg border border-zinc-200">
                    <template x-for="(p, i) in results" :key="p.id">
                        <button type="button" @click="choose(p)" class="flex w-full justify-between gap-2 border-b border-zinc-200 px-3 py-2 text-left text-sm last:border-0" :class="hi === i ? 'bg-zinc-100' : 'bg-white'">
                            <span class="truncate font-semibold" x-text="p.name"></span><span class="shrink-0 tabular-nums" x-text="jgRupees(p.price_paise)"></span>
                        </button>
                    </template>
                </div>
                @error('what') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-semibold text-zinc-700" for="ap-price">Price told</label>
                <div class="flex min-h-[52px] items-center rounded-lg border border-zinc-300 focus-within:ring-2 focus-within:ring-ink lg:min-h-11">
                    <span class="pl-3 pr-1 font-semibold text-zinc-600">₹</span>
                    <input id="ap-price" name="price" x-model="price" inputmode="decimal" placeholder="4,999" class="h-full min-w-0 flex-1 bg-transparent pr-3 text-right text-[17px] font-semibold tabular-nums placeholder:font-normal placeholder:text-zinc-500 focus:outline-none">
                </div>
                @error('price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <div class="mb-1 text-sm font-semibold text-zinc-700">How did they ask?</div>
                <input type="hidden" name="source" :value="source">
                <div class="flex flex-wrap gap-2">
                    @foreach (['walk_in_enquiry' => 'At the counter', 'phone_call' => 'Phone call', 'whatsapp_ad' => 'WhatsApp', 'instagram' => 'Instagram'] as $k => $l)
                        <button type="button" @click="source = '{{ $k }}'" class="min-h-10 rounded-full border px-4 text-sm font-semibold"
                                :class="source === '{{ $k }}' ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white'">{{ $l }}</button>
                    @endforeach
                </div>
            </div>

            <button class="flex min-h-14 w-full items-center justify-center rounded-lg bg-brand-400 text-[17px] font-semibold text-ink hover:bg-[#F2C800] lg:min-h-12">Save &amp; follow up</button>
        </form>
    </div>
</div>

@include('quotations._product-search-js')
<script nonce="{{ Vite::cspNonce() }}">
window.askedPrice = function (cfg) {
    return {
        mobile: cfg.old.mobile || '', what: cfg.old.what || '', price: cfg.old.price || '', productId: cfg.old.product_id || null,
        source: cfg.old.source || 'walk_in_enquiry', results: [], hi: 0,
        init() { jgProductSearch.load(cfg.productsUrl).catch(() => {}); },
        search() { this.results = jgProductSearch.search(this.what, 6).results; this.hi = 0; },
        choose(p) { this.productId = p.id; this.what = p.name; this.price = String(p.price_paise / 100); this.results = []; },
    };
};
</script>
