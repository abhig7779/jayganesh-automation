<x-layouts.app title="New quotation">
<div x-data="quoteBuilder(@js([
        'prefill' => $prefill,
        'canEditPrice' => $canEditPrice,
        'validDays' => $validDays,
        'lookupUrl' => url('/api/v1/customers/lookup'),
        'productsUrl' => route('api.v1.products.all'),
        'storeUrl' => route('quotations.store'),
    ]))" @keydown.window="key($event)" class="pb-28 lg:pb-0">

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('quotations.index') }}" class="mb-1 inline-flex items-center gap-1 text-sm font-semibold text-zinc-600 hover:text-ink"><x-quotations.icon name="chevron-left" class="size-4" />Quotations</a>
            <h1 class="text-[28px] font-bold leading-tight tracking-[-0.02em] lg:text-[22px]">
                <span x-text="revisedFrom ? 'Revise ' + revisedFrom : 'New quotation'"></span>
            </h1>
        </div>
        <p class="hidden text-xs text-zinc-600 lg:block"><kbd class="rounded border border-zinc-300 bg-white px-1">/</kbd> search items · <kbd class="rounded border border-zinc-300 bg-white px-1">↑</kbd><kbd class="rounded border border-zinc-300 bg-white px-1">↓</kbd> <kbd class="rounded border border-zinc-300 bg-white px-1">Enter</kbd> add · <kbd class="rounded border border-zinc-300 bg-white px-1">Ctrl</kbd>+<kbd class="rounded border border-zinc-300 bg-white px-1">Enter</kbd> save &amp; send</p>
    </div>

    <div class="grid grid-cols-[minmax(0,1fr)] gap-4 lg:grid-cols-[minmax(0,1fr)_420px] lg:gap-5">
        {{-- ================= LEFT: customer + item search ================= --}}
        <div class="space-y-4">
            {{-- Customer --}}
            <section class="rounded-xl border border-zinc-200 bg-white p-4 lg:p-5">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Customer</h2>
                    <span x-show="lookup === 'loading'" class="text-xs text-zinc-600">Checking…</span>
                </div>
                <label class="sr-only" for="q-mobile">Mobile number</label>
                <div class="flex min-h-[52px] items-center rounded-lg border bg-white focus-within:ring-2 focus-within:ring-ink lg:min-h-11"
                     :class="errors.mobile ? 'border-red-600' : 'border-zinc-300'">
                    <span class="pl-3 pr-2 text-[15px] font-semibold text-zinc-600">+91</span>
                    <input id="q-mobile" x-ref="mobile" x-model="mobile" @input="onMobile()" inputmode="numeric" autocomplete="off" maxlength="14"
                           placeholder="Mobile number (needed to follow up)" class="h-full min-w-0 flex-1 bg-transparent pr-3 text-[17px] font-semibold tabular-nums tracking-wide placeholder:text-[15px] placeholder:font-normal placeholder:tracking-normal placeholder:text-zinc-500 focus:outline-none">
                </div>
                <p x-show="errors.mobile" x-cloak class="mt-1 text-sm text-red-600" x-text="errors.mobile"></p>
                <p x-show="digits.length > 0 && digits.length < 10" x-cloak class="mt-1 text-sm text-zinc-600">Mobile number needs 10 digits.</p>

                {{-- Known customer --}}
                <div x-show="lookup === 'found'" x-cloak class="mt-3">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-lg bg-zinc-100 text-[13px] font-bold" x-text="initials(customer?.name)"></span>
                        <div>
                            <div class="text-[15px] font-semibold" x-text="customer?.name"></div>
                            <div class="text-[13px] font-medium text-zinc-600">Known customer · <span x-text="customer?.cars?.length || 0"></span> car(s)</div>
                        </div>
                    </div>
                </div>

                {{-- New number --}}
                <div x-show="lookup === 'new'" x-cloak class="mt-3">
                    <p class="mb-2 text-sm font-medium text-zinc-700">New customer. Add their name:</p>
                    <input x-model="name" x-ref="name" placeholder="Customer name" maxlength="120"
                           class="block min-h-[52px] w-full rounded-lg border px-3 text-[15px] focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink lg:min-h-11"
                           :class="errors.name ? 'border-red-600' : 'border-zinc-300'">
                    <p x-show="errors.name" class="mt-1 text-sm text-red-600" x-text="errors.name"></p>
                </div>

                {{-- Car --}}
                <div x-show="lookup === 'found' || lookup === 'new'" x-cloak class="mt-4">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Car</div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="car in (customer?.cars || [])" :key="car.id">
                            <button type="button" @click="carId = car.id; addingCar = false"
                                    class="inline-flex min-h-10 items-center gap-2 rounded-full border px-4 text-sm font-semibold"
                                    :class="carId === car.id ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white text-ink hover:bg-zinc-50'">
                                <x-quotations.icon name="car" class="size-4" /><span x-text="car.label + (car.reg_no_pretty ? ' · ' + car.reg_no_pretty : '')"></span>
                            </button>
                        </template>
                        <button type="button" @click="addingCar = true; carId = null; $nextTick(() => $refs.carModel.focus())"
                                class="inline-flex min-h-10 items-center rounded-full border border-dashed border-zinc-400 px-4 text-sm font-semibold"
                                :class="addingCar ? 'bg-zinc-100' : 'bg-white hover:bg-zinc-50'">+ Add car</button>
                    </div>
                    <div x-show="addingCar" x-cloak class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <input x-model="car.make" placeholder="Make (Hyundai)" class="min-h-11 rounded-lg border border-zinc-300 px-3 text-sm focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink">
                        <input x-model="car.model" x-ref="carModel" placeholder="Model (Creta)" class="min-h-11 rounded-lg border border-zinc-300 px-3 text-sm focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink">
                        <input x-model="car.reg_no" placeholder="MH12 AB 1234 (optional)" class="col-span-2 min-h-11 rounded-lg border border-zinc-300 px-3 text-sm uppercase focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink sm:col-span-1">
                    </div>
                </div>
            </section>

            {{-- Item search --}}
            <section class="rounded-xl border border-zinc-200 bg-white p-4 lg:p-5">
                <h2 class="mb-2 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Items</h2>
                <div class="relative">
                    <div class="flex min-h-[52px] items-center rounded-lg border border-zinc-300 bg-white focus-within:ring-2 focus-within:ring-ink lg:min-h-11">
                        <x-quotations.icon name="search" class="ml-3 size-5 text-zinc-500" />
                        <input data-search x-ref="search" x-model="query" @input="runSearch()" @keydown.arrow-down.prevent="hi = Math.min(hi + 1, results.length - 1)"
                               @keydown.arrow-up.prevent="hi = Math.max(hi - 1, 0)" @keydown.enter.prevent="results[hi] && add(results[hi], searchQty)"
                               @keydown.escape="query = ''; results = []" autocomplete="off"
                               placeholder="Search product, brand or SKU (e.g. gleam, creta mat)" class="h-full min-w-0 flex-1 bg-transparent px-3 text-[15px] placeholder:text-zinc-500 focus:outline-none">
                        <kbd class="mr-3 hidden rounded border border-zinc-300 px-1.5 text-[11px] text-zinc-500 lg:inline">/</kbd>
                    </div>
                    <div x-show="query && results.length" x-cloak class="mt-2 overflow-hidden rounded-lg border border-zinc-200">
                        <template x-for="(p, i) in results" :key="p.id">
                            <button type="button" @click="add(p, searchQty)" @mouseenter="hi = i"
                                    class="flex w-full items-center justify-between gap-3 border-b border-zinc-200 px-3 py-2.5 text-left last:border-0"
                                    :class="hi === i ? 'bg-zinc-100' : 'bg-white'">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold" x-text="p.name"></span>
                                    <span class="block truncate text-xs text-zinc-600"><span x-text="p.brand || ''"></span><span x-show="p.sku"> · <span x-text="p.sku"></span></span><span x-show="p.on_hand !== null && p.on_hand <= 0" class="font-semibold text-amber-700"> · Not in stock</span></span>
                                </span>
                                <span class="shrink-0 text-right tabular-nums">
                                    <span x-show="p.mrp_paise > p.price_paise" class="block text-xs text-zinc-500 line-through" x-text="jgRupees(p.mrp_paise)"></span>
                                    <span class="text-sm font-semibold" x-text="jgRupees(p.price_paise)"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p x-show="query && !results.length && productsReady" x-cloak class="mt-2 text-sm text-zinc-600">No item matches “<span x-text="query"></span>”. Try fewer letters.</p>
                    <p x-show="productsError" x-cloak class="mt-2 text-sm text-red-600">Couldn’t load the price list. <button type="button" class="font-semibold underline" @click="loadProducts()">Try again</button></p>
                </div>

                <div x-show="!query" class="mt-4">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Popular</div>
                    <div x-show="!productsReady && !productsError" class="flex flex-wrap gap-2">
                        <template x-for="n in 8"><span class="h-10 w-36 rounded-full bg-zinc-100"></span></template>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="p in popular" :key="p.id">
                            <button type="button" @click="add(p, 1)" class="inline-flex min-h-10 max-w-full items-center gap-1.5 rounded-full border border-zinc-300 bg-white px-4 text-sm font-semibold hover:bg-zinc-50">
                                <span class="truncate" x-text="p.name"></span><span class="shrink-0 whitespace-nowrap font-medium text-zinc-600" x-text="'· ' + jgRupees(p.price_paise)"></span>
                            </button>
                        </template>
                    </div>
                    <p x-show="productsReady && !popular.length" class="text-sm text-zinc-600">No products yet. Add them in Stock &amp; prices.</p>
                </div>
            </section>
        </div>

        {{-- ================= RIGHT: the quote ================= --}}
        <aside class="lg:sticky lg:top-6 lg:self-start">
            <section class="rounded-xl border border-zinc-200 bg-white">
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 lg:px-5">
                    <h2 class="text-[17px] font-semibold">Quotation</h2>
                    <span class="text-sm text-zinc-600"><span x-text="lines.length"></span> item(s)</span>
                </div>

                <div x-show="!lines.length" class="px-5 py-10 text-center">
                    <x-quotations.icon name="doc" class="mx-auto size-8 text-zinc-600" />
                    <p class="mt-2 text-[15px] font-medium text-zinc-700">Add items from the search or the Popular chips.</p>
                    <p x-show="errors.items" class="mt-2 text-sm text-red-600" x-text="errors.items"></p>
                </div>

                <ul class="max-h-[46dvh] divide-y divide-zinc-200 overflow-y-auto">
                    <template x-for="(l, i) in lines" :key="l.key">
                        <li class="px-4 py-3 lg:px-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold" x-text="l.name"></div>
                                    <div class="mt-0.5 text-xs text-zinc-600 tabular-nums">
                                        <span x-show="l.mrp_paise > l.unit_price_paise" class="line-through" x-text="'MRP ' + jgRupees(l.mrp_paise)"></span>
                                        <span x-show="l.mrp_paise > l.unit_price_paise"> · </span>
                                        <template x-if="canEditPrice">
                                            <span>₹<input type="text" inputmode="decimal" :value="(l.unit_price_paise / 100)" @change="setPrice(l, $event.target.value)"
                                                         class="ml-0.5 w-20 rounded border border-zinc-300 px-1 py-0.5 text-xs tabular-nums focus:border-ink focus:outline-none" aria-label="Price"> each</span>
                                        </template>
                                        <template x-if="!canEditPrice"><span x-text="jgRupees(l.unit_price_paise) + ' each'"></span></template>
                                    </div>
                                    <div x-show="lineSaving(l) > 0" class="mt-0.5 text-xs font-semibold text-green-700" x-text="'You save ' + jgRupees(lineSaving(l))"></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-[15px] font-semibold tabular-nums" x-text="jgRupees(l.qty * l.unit_price_paise)"></div>
                                </div>
                            </div>
                            <div class="mt-2 flex items-center justify-between">
                                <div class="inline-flex h-10 items-center rounded-lg border border-zinc-300">
                                    <button type="button" @click="step(i, -1)" class="grid h-10 w-10 place-items-center" aria-label="Less"><x-quotations.icon name="minus" class="size-4" /></button>
                                    <span class="w-8 text-center text-sm font-semibold tabular-nums" x-text="l.qty"></span>
                                    <button type="button" @click="step(i, 1)" class="grid h-10 w-10 place-items-center" aria-label="More"><x-quotations.icon name="plus" class="size-4" /></button>
                                </div>
                                <button type="button" @click="remove(i)" class="min-h-10 px-2 text-sm font-semibold text-zinc-600 hover:text-ink">Remove</button>
                            </div>
                        </li>
                    </template>
                </ul>

                <div class="space-y-3 border-t border-zinc-200 px-4 py-4 lg:px-5">
                    <div>
                        <div class="mb-1.5 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Valid for</div>
                        <div class="flex rounded-lg bg-zinc-100 p-1 text-sm font-semibold">
                            <template x-for="d in [3, 7, 15, 30]">
                                <button type="button" @click="validDays = d" class="h-9 flex-1 rounded-md"
                                        :class="validDays === d ? 'border border-zinc-200 bg-white text-ink' : 'text-zinc-600'" x-text="d + ' days'"></button>
                            </template>
                        </div>
                    </div>
                    <input x-model="notes" maxlength="500" placeholder="Note on the quote (optional)" class="block min-h-11 w-full rounded-lg border border-zinc-300 px-3 text-sm placeholder:text-zinc-500 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink">
                </div>

                <div class="border-t border-zinc-200 px-4 py-4 lg:px-5">
                    <div x-show="savings > 0" class="flex justify-between text-sm text-zinc-600 tabular-nums"><span>MRP total</span><span class="line-through" x-text="jgRupees(total + savings)"></span></div>
                    <div class="flex items-baseline justify-between">
                        <span class="text-sm font-semibold">Total <span class="font-normal text-zinc-600">incl. GST</span></span>
                        <span class="text-[28px] font-bold tabular-nums tracking-[-0.02em] underline decoration-brand-400 decoration-4 underline-offset-4" x-text="jgRupees(total)"></span>
                    </div>
                    <div x-show="savings > 0" class="mt-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm font-semibold text-green-700">
                        MRP <span x-text="jgRupees(total + savings)"></span> → our price <span x-text="jgRupees(total)"></span>, you save <span x-text="jgRupees(savings)"></span>
                    </div>
                    <p x-show="formError" x-cloak class="mt-3 text-sm font-medium text-red-600" x-text="formError"></p>
                    <div class="mt-4 hidden space-y-2 lg:block">
                        <button type="button" @click="submit(true)" :disabled="saving" class="flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-brand-400 text-[15px] font-semibold text-ink hover:bg-[#F2C800] disabled:opacity-60">
                            <x-quotations.icon name="chat" /> Save &amp; send on WhatsApp <kbd class="rounded border border-ink/30 px-1.5 text-[11px]">Ctrl+Enter</kbd>
                        </button>
                        <button type="button" @click="submit(false)" :disabled="saving" class="flex h-10 w-full items-center justify-center rounded-lg border border-zinc-300 bg-white text-sm font-semibold hover:bg-zinc-50 disabled:opacity-60">Save without sending</button>
                    </div>
                </div>
            </section>
            <p class="mt-2 hidden px-1 text-xs text-zinc-600 lg:block">Saving plans the follow-ups automatically (Day 1, 3, 6, 10). WhatsApp Web opens in a new tab with the message and the PDF link.</p>
        </aside>
    </div>

    {{-- Phone: sticky total bar --}}
    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200 bg-white px-4 pt-3 lg:hidden" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
        <div class="mb-2 flex items-baseline justify-between text-sm">
            <span class="text-zinc-600"><span x-text="lines.length"></span> item(s) · incl. GST<span x-show="savings > 0" class="font-semibold text-green-700"> · save <span x-text="jgRupees(savings)"></span></span></span>
            <span class="text-2xl font-bold tabular-nums" x-text="jgRupees(total)"></span>
        </div>
        <div class="grid grid-cols-[auto_1fr] gap-2">
            <button type="button" @click="submit(false)" :disabled="saving" class="h-14 rounded-lg border border-zinc-300 px-4 text-sm font-semibold">Save</button>
            <button type="button" @click="submit(true)" :disabled="saving" class="h-14 rounded-lg bg-brand-400 text-[17px] font-semibold text-ink">Save &amp; send on WhatsApp</button>
        </div>
    </div>
</div>

@include('quotations._product-search-js')
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
window.quoteBuilder = function (cfg) {
    const p = cfg.prefill || {};
    let keySeq = 1;
    return {
        mobile: p.mobile || '',
        lookup: 'idle',
        customer: p.customer || null,
        name: p.name || '',
        carId: p.customer_car_id || null,
        addingCar: false,
        car: { make: '', model: '', reg_no: '' },
        query: '', results: [], hi: 0, searchQty: 1,
        popular: [], productsReady: false, productsError: false,
        lines: (p.items || []).map((l) => ({ ...l, key: keySeq++ })),
        validDays: cfg.validDays || 7,
        notes: p.notes || '',
        canEditPrice: cfg.canEditPrice,
        revisedFrom: p.revised_from_number || null,
        errors: {}, formError: '', saving: false,

        init() {
            this.loadProducts();
            if (this.customer) { this.lookup = 'found'; }
            else if (this.digits.length === 10) { this.onMobile(); }
            this.$nextTick(() => (this.digits.length === 10 ? this.$refs.search : this.$refs.mobile).focus());
        },
        get digits() { let d = this.mobile.replace(/\D/g, ''); if (d.length > 10) d = d.slice(-10); return d; },
        get total() { return this.lines.reduce((s, l) => s + l.qty * l.unit_price_paise, 0); },
        get savings() { return this.lines.reduce((s, l) => s + this.lineSaving(l), 0); },
        lineSaving(l) { return l.mrp_paise && l.mrp_paise > l.unit_price_paise ? (l.mrp_paise - l.unit_price_paise) * l.qty : 0; },
        initials(n) { return String(n || '').split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase(); },

        loadProducts() {
            this.productsError = false;
            jgProductSearch.load(cfg.productsUrl)
                .then(() => { this.productsReady = true; this.popular = jgProductSearch.popular(12); if (this.query) this.runSearch(); })
                .catch(() => { this.productsError = true; });
        },
        runSearch() {
            const r = jgProductSearch.search(this.query, 10);
            this.results = r.results; this.searchQty = r.qty; this.hi = 0;
        },
        add(prod, qty) {
            jgProductSearch.remember(prod);
            const found = this.lines.find((l) => l.product_id === prod.id);
            if (found) found.qty = Math.min(999, found.qty + qty);
            else this.lines.push({ key: keySeq++, product_id: prod.id, name: prod.name, qty, unit_price_paise: prod.price_paise, list_price_paise: prod.price_paise, mrp_paise: prod.mrp_paise, gst_rate: prod.gst_rate });
            this.query = ''; this.results = []; delete this.errors.items;
            this.$nextTick(() => this.$refs.search.focus());
        },
        step(i, d) { const l = this.lines[i]; l.qty = Math.max(1, Math.min(999, l.qty + d)); },
        remove(i) { this.lines.splice(i, 1); },
        setPrice(l, v) {
            const n = parseFloat(String(v).replace(/[₹,\s]/g, ''));
            if (!isNaN(n) && n >= 0) l.unit_price_paise = Math.round(n * 100);
        },

        async onMobile() {
            this.errors.mobile = '';
            if (this.digits.length !== 10) { if (this.lookup !== 'idle') { this.lookup = 'idle'; this.customer = null; this.carId = null; } return; }
            if (this.customer && this.lookup === 'found') return;
            this.lookup = 'loading';
            try {
                const r = await fetch(cfg.lookupUrl + '?mobile=' + this.digits, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (r.status === 404) { this.lookup = 'new'; this.customer = null; this.$nextTick(() => this.$refs.name.focus()); return; }
                if (r.status === 422) { this.lookup = 'idle'; this.errors.mobile = 'Enter a valid 10-digit Indian mobile number.'; return; }
                if (!r.ok) throw new Error();
                const d = (await r.json()).data;
                this.customer = { id: d.id, name: d.name, cars: d.cars.map((c) => ({ id: c.id, label: c.label, reg_no_pretty: c.reg_no_pretty })) };
                this.lookup = 'found';
                if (this.customer.cars.length === 1) this.carId = this.customer.cars[0].id;
                this.$nextTick(() => this.$refs.search.focus());
            } catch (e) {
                this.lookup = 'new';
            }
        },

        key(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); this.submit(true); }
        },

        async submit(send) {
            if (this.saving) return;
            this.errors = {}; this.formError = '';
            if (this.digits.length !== 10) { this.errors.mobile = 'Enter the customer’s 10-digit mobile number. Without it we can’t follow up.'; this.$refs.mobile.focus(); return; }
            if (this.lookup === 'new' && !this.name.trim()) { this.errors.name = 'Enter the customer’s name.'; this.$refs.name.focus(); return; }
            if (!this.lines.length) { this.errors.items = 'Add at least one item.'; this.formError = 'Add at least one item.'; this.$refs.search.focus(); return; }

            // Open the WhatsApp tab now, inside the click, so the browser doesn't block it.
            const tab = send ? window.open('', '_blank') : null;
            this.saving = true;
            const body = {
                mobile: this.digits, name: this.name, customer_id: this.customer?.id || null,
                customer_car_id: this.carId, car: this.addingCar ? this.car : null,
                items: this.lines.map((l) => ({ product_id: l.product_id, name: l.name, qty: l.qty, unit_price_paise: this.canEditPrice ? l.unit_price_paise : null })),
                valid_days: this.validDays, notes: this.notes, send: send ? 1 : 0,
                lead_id: p.lead_id || null, revised_from_id: p.revised_from_id || null,
            };
            try {
                const r = await fetch(cfg.storeUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify(body),
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok) {
                    tab?.close();
                    const errs = d.errors || {};
                    this.errors = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k.split('.')[0], v[0]]));
                    this.formError = Object.values(errs)[0]?.[0] || d.message || 'Could not save. Try again.';
                    return;
                }
                if (tab) tab.location = d.whatsapp_url;
                window.location = d.show_url + (send ? '?sent=1' : '');
            } catch (e) {
                tab?.close();
                this.formError = 'No internet. Nothing was saved. Try again when you are back online.';
            } finally {
                this.saving = false;
            }
        },
    };
};
</script>
</x-layouts.app>
