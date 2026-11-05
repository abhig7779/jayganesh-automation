/**
 * Billing screens (New bill POS + phone 3 steps, Bill done). Registered as Alpine components.
 * Loaded by app.js through import.meta.glob('./modules/*.js').
 */
import { ProductSearch, ScanDetector, parseQty, inStock } from '../product-search.js';
import { calculate, money, toPaise } from '../bill-totals.js';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'd' + Date.now().toString(36) + Math.random().toString(36).slice(2));
const isDesktop = () => window.matchMedia('(min-width: 1024px)').matches;
const typingIn = (el) => el && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable);

/** 10-digit Indian mobile from anything typed (98231 07676, +91…, 098…), or null. */
export function mobileDigits(v) {
    let d = String(v ?? '').replace(/\D/g, '');
    if (d.length === 12 && d.startsWith('91')) d = d.slice(2);
    if (d.length === 11 && d.startsWith('0')) d = d.slice(1);
    return d;
}
const prettyMobile = (d) => (d.length > 5 ? d.slice(0, 5) + ' ' + d.slice(5, 10) : d);

function json(url, opts = {}) {
    return fetch(url, {
        credentials: 'same-origin',
        ...opts,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), ...(opts.body ? { 'Content-Type': 'application/json' } : {}), ...(opts.headers || {}) },
    });
}

export function billForm(cfg) {
    const search = new ProductSearch({ url: cfg.urls.products });
    const scan = new ScanDetector();
    const draftStore = 'jg.billDraft.' + cfg.userId;

    return {
        cfg,
        money,
        mobileDigits,
        step: 1,
        draftKey: uuid(),

        // products
        loaded: false,
        loadError: null,
        q: '',
        results: [],
        active: 0,
        noMatch: false,
        custom: { open: false, name: '', price: '', gst: cfg.defaultGst },

        // customer
        mobile: '',
        custState: 'idle', // idle | short | loading | found | new | walkin | error
        cust: null,
        ctx: null,
        carId: null,
        newCust: { name: '', model: '', make: '', reg: '', source: 'walk_in' },
        modelOpen: false,
        modelSuggest: [],
        walkInSheet: false,
        quotationId: null,

        // items
        items: [],
        sel: -1,
        undo: null,

        // discount
        disc: { open: false, mode: 'flat', value: '', reason: '' },

        // payment
        mode: 'cash',
        cashGiven: '',
        split: { cash: '', upi: '', card: '' },
        laterPaid: '',
        ref: '',
        sendWa: true,
        qrOpen: false,

        // state
        saving: false,
        pendingRetry: false,
        error: null,
        online: navigator.onLine,
        savedAt: null,
        restoredNote: null,
        toast: null,
        lastSyncJson: '',

        // --------------------------------------------------------------------- lifecycle
        init() {
            search.load().then(() => {
                this.loaded = true;
                this.applyPrefillItems();
                this.refreshResults();
            }).catch((e) => { this.loadError = e.message; });

            window.addEventListener('focus', () => search.refreshIfStale().then(() => this.refreshResults()));
            window.addEventListener('online', () => { this.online = true; if (this.pendingRetry) this.save(); });
            window.addEventListener('offline', () => { this.online = false; });
            window.addEventListener('keydown', (e) => this.onKey(e));

            if (cfg.prefill.active) this.applyPrefill();
            else this.restoreDraft();

            this.$watch('snapshotJson', (v) => this.persistLocal(v));
            setInterval(() => this.syncServer(), 8000);
            this.$nextTick(() => this.focusFirst());
        },

        focusFirst() {
            if (isDesktop()) {
                (this.custState === 'idle' ? this.$refs.mobilePc : this.$refs.searchPc)?.focus();
            } else if (this.step === 1) {
                this.$refs.mobilePhone?.focus();
            }
        },

        // --------------------------------------------------------------------- products
        get popular() { return this.loaded ? search.popular(12) : []; },
        get recent() { return this.loaded ? search.recent(8) : []; },
        get carModelName() {
            if (this.custState === 'found' && this.carId) return this.cust?.cars?.find((c) => c.id === this.carId)?.model || null;
            if (this.custState === 'new' && this.newCust.model) return this.newCust.model;
            return null;
        },
        get carSuggestions() {
            if (!this.loaded) return [];
            const ids = this.custState === 'found' && this.carId ? (this.ctx?.car_suggestions?.[this.carId] || []) : this.modelSuggest;
            return ids.map((id) => search.byId(id)).filter(Boolean).slice(0, 6);
        },
        inStock,
        stockLabel(p) {
            if (p.type === 'service') return 'Service';
            if (!p.track_stock) return '';
            return p.on_hand > 0 ? p.on_hand + ' in stock' : 'Out of stock';
        },
        stockClass(p) {
            if (p.type === 'service' || !p.track_stock) return 'border-zinc-200 bg-zinc-100 text-zinc-600';
            if (p.on_hand <= 0) return 'border-red-200 bg-red-50 text-red-600';
            return p.on_hand <= 3 ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-green-200 bg-green-50 text-green-700';
        },

        refreshResults() {
            if (!this.loaded) return;
            const term = this.q.trim();
            this.results = term ? search.search(term, 8) : [];
            this.noMatch = term.length >= 2 && this.results.length === 0;
            if (this.active >= this.results.length) this.active = 0;
        },

        onSearchInput() {
            this.active = 0;
            this.custom.open = false;
            this.refreshResults();
        },

        onSearchKey(e) {
            scan.key(e);
            if (e.key === 'ArrowDown') { e.preventDefault(); this.active = Math.min(this.active + 1, Math.max(0, this.results.length - 1)); return; }
            if (e.key === 'ArrowUp') { e.preventDefault(); this.active = Math.max(0, this.active - 1); return; }
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); if (this.q) { this.q = ''; this.onSearchInput(); } else e.target.blur(); return; }
            if (this.q === '' && (e.key === '+' || e.key === '=' || e.key === '-')) { e.preventDefault(); this.bumpSel(e.key === '-' ? -1 : 1); return; }
            if (e.key === 'Enter' && !(e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                this.enterSearch(scan.isScan());
                scan.reset();
            }
        },

        /** Enter in the search box: exact code (barcode) first, then the highlighted result. */
        enterSearch(scanned = false) {
            const { qty, term } = parseQty(this.q);
            if (!term) return;
            const hit = search.exact(term);
            if (hit) { this.addProduct(hit, qty); return; }
            if (scanned) { this.flash('No product with code ' + term + '. Check the barcode or add it in Stock.', 'bad'); this.q = ''; this.onSearchInput(); return; }
            this.refreshResults();
            const r = this.results[this.active];
            if (r) this.addProduct(r.product, qty);
        },

        pick(p, qty = 1) {
            this.addProduct(p, qty);
        },

        addProduct(p, qty = 1) {
            const i = this.items.findIndex((l) => l.product_id === p.id);
            if (i >= 0) {
                this.items[i].qty += qty;
                this.sel = i;
            } else {
                this.items.push({
                    key: uuid(), product_id: p.id, name: p.name, brand: p.brand, sku: p.sku, type: p.type,
                    unit_price_paise: p.price_paise, list_price_paise: p.price_paise, mrp_paise: p.mrp_paise,
                    gst_rate: p.gst_rate, price_includes_gst: p.price_includes_gst,
                    on_hand: p.on_hand, track_stock: p.track_stock, qty, priceText: '',
                });
                this.sel = this.items.length - 1;
            }
            search.touch(p.id);
            this.q = '';
            this.onSearchInput();
            this.error = null;
            this.$nextTick(() => {
                const el = isDesktop() ? this.$refs.searchPc : this.$refs.searchPhone;
                if (el && document.activeElement !== this.$refs.mobilePc) el.focus();
                document.querySelector('[data-line="' + this.items[this.sel]?.key + '"]')?.scrollIntoView({ block: 'nearest' });
            });
        },

        addCustom() {
            const price = toPaise(this.custom.price);
            if (!this.custom.name.trim() || !Number.isFinite(price) || price < 0) { this.flash('Enter a name and a price for the custom item.', 'bad'); return; }
            this.items.push({
                key: uuid(), product_id: null, name: this.custom.name.trim(), brand: null, sku: null, type: 'custom',
                unit_price_paise: price, list_price_paise: price, mrp_paise: null, gst_rate: Number(this.custom.gst),
                price_includes_gst: true, on_hand: 0, track_stock: false, qty: 1, priceText: '',
            });
            this.sel = this.items.length - 1;
            this.custom = { open: false, name: '', price: '', gst: cfg.defaultGst };
            this.q = '';
            this.onSearchInput();
        },

        outOfStock(l) {
            return l.track_stock && l.on_hand < l.qty;
        },

        setQty(i, qty) {
            const l = this.items[i];
            if (!l) return;
            qty = parseInt(qty, 10);
            if (!Number.isFinite(qty)) return;
            if (qty < 1) { this.removeLine(i); return; }
            l.qty = Math.min(qty, 9999);
        },
        bumpSel(d) { if (this.sel >= 0) this.setQty(this.sel, this.items[this.sel].qty + d); },

        setPrice(i, v) {
            const p = toPaise(v);
            if (Number.isFinite(p) && p >= 0) this.items[i].unit_price_paise = p;
        },

        removeLine(i) {
            const [line] = this.items.splice(i, 1);
            if (!line) return;
            if (this.sel >= this.items.length) this.sel = this.items.length - 1;
            clearTimeout(this.undo?.timer);
            this.undo = { line, index: i, timer: setTimeout(() => { this.undo = null; }, 10000) };
        },
        undoRemove() {
            if (!this.undo) return;
            clearTimeout(this.undo.timer);
            this.items.splice(Math.min(this.undo.index, this.items.length), 0, this.undo.line);
            this.sel = this.undo.index;
            this.undo = null;
        },
        clearAll() {
            if (!this.items.length) return;
            if (!confirm('Remove all ' + this.items.length + ' items from this bill?')) return;
            this.items = [];
            this.sel = -1;
        },

        // --------------------------------------------------------------------- customer
        onMobileInput() {
            const d = mobileDigits(this.mobile);
            if (d.length === 10 && /^[6-9]/.test(d)) {
                if (this.cust?.mobile !== '+91' + d) this.lookup(d);
            } else {
                if (['found', 'new', 'error'].includes(this.custState)) this.resetCustomer(false);
                this.custState = d.length ? 'short' : 'idle';
            }
        },
        get mobileError() {
            const d = mobileDigits(this.mobile);
            if (!d.length || this.custState === 'loading') return null;
            if (d.length === 10 && !/^[6-9]/.test(d)) return 'Indian mobile numbers start with 6, 7, 8 or 9.';
            if (d.length > 10) return 'That is more than 10 digits.';
            return null;
        },
        async lookup(d) {
            this.custState = 'loading';
            const mobile = '+91' + d;
            try {
                const [res, ctxRes] = await Promise.all([
                    json(cfg.urls.lookup + '?mobile=' + encodeURIComponent(mobile)),
                    json(cfg.urls.context + '?mobile=' + encodeURIComponent(mobile)),
                ]);
                if (mobileDigits(this.mobile) !== d) return; // typed on meanwhile
                // The customers API answers with the login cookie only when this host is in
                // SANCTUM_STATEFUL_DOMAINS; if not, the billing context carries the same customer.
                const ctx = ctxRes.ok ? await ctxRes.json() : null;
                if (!res.ok && res.status !== 404 && (ctx || ctxRes.status === 404)) {
                    if (ctx) {
                        this.applyFound(ctx.customer, ctx);
                        return;
                    }
                }
                if (res.status === 404 || (!res.ok && ctxRes.status === 404)) {
                    this.cust = null; this.ctx = null; this.carId = null;
                    this.custState = 'new';
                    this.sendWa = true;
                    this.$nextTick(() => (isDesktop() ? this.$refs.newNamePc : this.$refs.newNamePhone)?.focus());
                    return;
                }
                if (!res.ok) throw new Error('lookup ' + res.status);
                const body = await res.json();
                this.applyFound(body.data || body, ctx);
            } catch (e) {
                this.custState = navigator.onLine ? 'error' : 'idle';
                if (!navigator.onLine) this.online = false;
            }
        },
        applyFound(customer, ctx) {
            this.cust = customer;
            this.ctx = ctx;
            const cars = (this.cust.cars || []).map((c) => ({ ...c, reg_pretty: c.reg_pretty ?? c.reg_no_pretty ?? c.reg_no }));
            // Context has the display-ready plates; prefer them.
            this.cust.cars = ctx?.customer?.cars?.length ? ctx.customer.cars : cars;
            const list = this.cust.cars;
            this.carId = cfg.prefill.customer_car_id && list.some((c) => c.id === cfg.prefill.customer_car_id)
                ? cfg.prefill.customer_car_id : (list.length === 1 ? list[0].id : (this.carId && list.some((c) => c.id === this.carId) ? this.carId : null));
            this.sendWa = this.cust.whatsapp_opt_in !== false;
            this.custState = 'found';
            if (isDesktop()) this.$nextTick(() => this.$refs.searchPc?.focus());
        },
        resetCustomer(clearMobile = true) {
            if (clearMobile) this.mobile = '';
            this.cust = null; this.ctx = null; this.carId = null;
            this.newCust = { name: '', model: '', make: '', reg: '', source: 'walk_in' };
            this.modelSuggest = [];
            this.custState = 'idle';
        },
        changeCustomer() {
            this.resetCustomer();
            this.step = 1;
            this.$nextTick(() => (isDesktop() ? this.$refs.mobilePc : this.$refs.mobilePhone)?.focus());
        },
        confirmWalkIn() {
            this.resetCustomer();
            this.custState = 'walkin';
            this.walkInSheet = false;
            this.sendWa = false;
            if (this.mode === 'later') this.mode = 'cash';
            if (!isDesktop()) this.step = 2;
            else this.$nextTick(() => this.$refs.searchPc?.focus());
        },
        get modelMatches() {
            const q = this.newCust.model.trim().toLowerCase();
            const list = cfg.carModels;
            if (!q) return list.slice(0, 8);
            return list.filter((m) => m.label.toLowerCase().includes(q)).slice(0, 8);
        },
        pickModel(m) {
            this.newCust.model = m.model;
            this.newCust.make = m.make;
            this.modelOpen = false;
            json(cfg.urls.modelSuggestions + '?model=' + encodeURIComponent(m.model))
                .then((r) => (r.ok ? r.json() : { products: [] }))
                .then((d) => { this.modelSuggest = d.products || []; })
                .catch(() => {});
        },
        get selectedCarLabel() {
            const c = this.cust?.cars?.find((x) => x.id === this.carId);
            if (c) return c.model + (c.reg_pretty ? ' · ' + c.reg_pretty : '');
            if (this.custState === 'new' && this.newCust.model) return this.newCust.model + (this.newCust.reg ? ' · ' + this.newCust.reg.toUpperCase() : '');
            return '';
        },
        get customerLabel() {
            if (this.custState === 'found') return this.cust.name;
            if (this.custState === 'new') return this.newCust.name || 'New customer';
            if (this.custState === 'walkin') return 'Walk-in (no number)';
            return '';
        },
        get customerReady() {
            return this.custState === 'found' || this.custState === 'walkin' || (this.custState === 'new' && this.newCust.name.trim().length >= 2);
        },
        prettyMobile,

        // --------------------------------------------------------------------- money
        get billDiscountPaise() {
            if (this.disc.mode === 'pct') {
                const p = parseFloat(this.disc.value);
                if (!Number.isFinite(p) || p <= 0) return 0;
                const gross = calculate(this.lines(), 0, this.gstOpts).gross;
                return Math.round((gross * Math.min(p, 100)) / 100);
            }
            const v = toPaise(this.disc.value);
            return Number.isFinite(v) ? v : 0;
        },
        get gstOpts() { return { registered: cfg.gst.registered, intra: true }; },
        lines() {
            return this.items.map((l) => ({ qty: l.qty, unit_price_paise: l.unit_price_paise, gst_rate: l.gst_rate, price_includes_gst: l.price_includes_gst, mrp_paise: l.mrp_paise }));
        },
        get t() { return calculate(this.lines(), this.billDiscountPaise, this.gstOpts); },
        get pieces() { return this.items.reduce((s, l) => s + l.qty, 0); },
        get gstLabel() {
            const r = this.t.rates;
            if (!cfg.gst.registered) return 'No GST (bill of supply)';
            if (r.length === 1) return 'GST (CGST ' + r[0] / 2 + '% + SGST ' + r[0] / 2 + '%)';
            return 'GST (CGST + SGST)';
        },
        /** Discount vs shelf price, % (for the Sales cap). */
        get discountPercent() {
            const list = calculate(this.items.map((l) => ({ ...l, unit_price_paise: l.list_price_paise })), 0, this.gstOpts).before;
            if (list <= 0) return 0;
            return ((list - this.t.before) * 100) / list;
        },
        get discountOverLimit() {
            return cfg.limits.discountPercent !== null && this.discountPercent > cfg.limits.discountPercent + 1e-9;
        },
        lineTotal(l) { return calculate([{ qty: l.qty, unit_price_paise: l.unit_price_paise, gst_rate: l.gst_rate, price_includes_gst: l.price_includes_gst }], 0, this.gstOpts).before; },
        unitIncl(l) { return calculate([{ qty: 1, unit_price_paise: l.unit_price_paise, gst_rate: l.gst_rate, price_includes_gst: l.price_includes_gst }], 0, this.gstOpts).before; },
        showMrp(l) { return l.mrp_paise && l.mrp_paise > this.unitIncl(l); },

        setMode(m) {
            if (m === 'later' && this.custState === 'walkin') { this.flash('To leave a due, enter the customer’s mobile number.', 'bad'); return; }
            this.mode = m;
            if (m === 'split' && !this.split.cash && !this.split.upi && !this.split.card) {
                this.split.cash = '';
                this.split.upi = '';
            }
        },
        get payments() {
            const total = this.t.total;
            switch (this.mode) {
                case 'cash': return [{ mode: 'cash', amount_paise: total }];
                case 'upi': return [{ mode: 'upi', amount_paise: total, reference: this.ref || null }];
                case 'card': return [{ mode: 'card', amount_paise: total, reference: this.ref || null }];
                case 'split': return ['cash', 'upi', 'card'].map((m) => ({ mode: m, amount_paise: toPaise(this.split[m]) || 0 })).filter((p) => p.amount_paise > 0);
                case 'later': { const p = toPaise(this.laterPaid) || 0; return p > 0 ? [{ mode: 'cash', amount_paise: p }] : []; }
            }
            return [];
        },
        get paidNow() { return this.payments.reduce((s, p) => s + p.amount_paise, 0); },
        get remaining() { return this.t.total - this.paidNow; },
        get change() {
            const g = toPaise(this.cashGiven);
            return Number.isFinite(g) && g > this.t.total ? g - this.t.total : 0;
        },
        get dueOverLimit() {
            return this.mode === 'later' && cfg.limits.duePaise !== null && this.remaining > cfg.limits.duePaise;
        },
        fillSplit(m) {
            const others = ['cash', 'upi', 'card'].filter((x) => x !== m).reduce((s, x) => s + (toPaise(this.split[x]) || 0), 0);
            const left = this.t.total - others;
            if (left > 0) this.split[m] = (left / 100).toFixed(left % 100 ? 2 : 0);
        },
        get qrAmount() {
            if (this.mode === 'split') return toPaise(this.split.upi) || 0;
            return this.mode === 'upi' ? this.t.total : 0;
        },
        qrUrl(size = 220) {
            return cfg.urls.qr + '?amount_paise=' + this.qrAmount + '&size=' + size;
        },

        /** Why Save is blocked, in plain words (null = ready). */
        get blocker() {
            if (!this.items.length) return 'Add at least one item.';
            if (this.custState === 'new' && this.newCust.name.trim().length < 2) return 'Enter the new customer’s name.';
            if (this.custState === 'loading') return 'Checking the mobile number…';
            if (this.custState === 'short') return 'Mobile number needs 10 digits.';
            if (this.discountOverLimit) return 'Discount over ' + cfg.limits.discountPercent + '% needs the Owner’s approval.';
            if (this.mode === 'split' && this.remaining !== 0) return this.remaining > 0 ? 'Split amounts are ' + money(this.remaining) + ' short.' : 'Split amounts are ' + money(-this.remaining) + ' over the total.';
            if (this.mode === 'later' && this.remaining < 0) return 'Paid now is more than the total.';
            if (this.dueOverLimit) return 'A due over ' + money(cfg.limits.duePaise) + ' needs an Admin or the Owner.';
            if (this.mode === 'later' && this.custState !== 'found' && this.custState !== 'new') return 'To leave a due, enter the customer’s mobile number.';
            return null;
        },
        get saveLabel() {
            if (this.saving) return 'Saving…';
            return this.sendWa && this.custState !== 'walkin' && this.custState !== 'idle' ? 'Save bill & send WhatsApp' : 'Save bill';
        },

        // --------------------------------------------------------------------- save
        payload() {
            const p = {
                draft_key: this.draftKey,
                quotation_id: this.quotationId,
                items: this.items.map((l) => ({
                    product_id: l.product_id,
                    name: l.product_id ? undefined : l.name,
                    gst_rate: l.product_id ? undefined : l.gst_rate,
                    qty: l.qty,
                    unit_price_paise: l.unit_price_paise,
                })),
                bill_discount_paise: this.billDiscountPaise,
                discount_reason: this.disc.reason || null,
                payments: this.payments,
                send_whatsapp: this.sendWa,
            };
            if (this.custState === 'found') {
                p.customer_id = this.cust.id;
                p.customer_car_id = this.carId;
                if (!this.carId && this.newCust.model) {
                    p.customer = { car: { make: this.newCust.make, model: this.newCust.model, reg_no: this.newCust.reg || null } };
                }
            } else if (this.custState === 'new') {
                p.customer = {
                    mobile: '+91' + mobileDigits(this.mobile),
                    name: this.newCust.name.trim(),
                    source: this.newCust.source,
                    car: this.newCust.model ? { make: this.newCust.make, model: this.newCust.model, reg_no: this.newCust.reg || null } : null,
                };
            }
            return p;
        },

        async save() {
            if (this.saving) return;
            const b = this.blocker;
            if (b) { this.flash(b, 'bad'); return; }
            if (this.custState === 'idle' && !confirm('No mobile number: the customer gets no WhatsApp bill. Save as walk-in?')) return;
            this.saving = true;
            this.error = null;
            try {
                const res = await json(cfg.urls.store, { method: 'POST', body: JSON.stringify(this.payload()) });
                if (res.status === 422) {
                    const body = await res.json();
                    this.error = Object.values(body.errors || {}).flat()[0] || body.message || 'Please check the bill.';
                    this.saving = false;
                    return;
                }
                if (res.status === 419 || res.status === 401) {
                    this.error = 'You were signed out. Sign in again in a new tab; this bill is kept here.';
                    this.saving = false;
                    return;
                }
                if (res.status === 403) { this.error = 'You are not allowed to save bills.'; this.saving = false; return; }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                this.pendingRetry = false;
                this.dropDraft();
                window.location.href = data.redirect;
            } catch (e) {
                // Network trouble: keep everything, retry with the SAME draft key (never two bills).
                this.saving = false;
                this.pendingRetry = true;
                this.online = navigator.onLine;
                this.error = this.online ? 'Could not reach the server. Trying again…' : null;
                setTimeout(() => { if (this.pendingRetry && navigator.onLine) this.save(); }, 5000);
            }
        },

        newBill(ask = true) {
            if (ask && this.items.length && !confirm('Start a new bill? This one will be cleared.')) return;
            this.dropDraft();
            window.location.href = window.location.pathname;
        },

        // --------------------------------------------------------------------- drafts
        get snapshotJson() {
            return JSON.stringify({
                draftKey: this.draftKey, mobile: this.mobile, custState: this.custState === 'loading' ? 'idle' : this.custState,
                carId: this.carId, newCust: this.newCust, items: this.items, disc: this.disc, mode: this.mode,
                split: this.split, laterPaid: this.laterPaid, sendWa: this.sendWa, quotationId: this.quotationId, step: this.step,
            });
        },
        get hasContent() { return this.items.length > 0 || mobileDigits(this.mobile).length > 0; },
        persistLocal(v) {
            try {
                if (this.hasContent) localStorage.setItem(draftStore, JSON.stringify({ at: Date.now(), state: JSON.parse(v) }));
                else localStorage.removeItem(draftStore);
            } catch { /* storage full or blocked */ }
        },
        async syncServer() {
            if (!this.hasContent || !navigator.onLine || this.saving) return;
            const v = this.snapshotJson;
            if (v === this.lastSyncJson) return;
            try {
                const res = await json(cfg.urls.draft, { method: 'PUT', body: JSON.stringify({ draft_key: this.draftKey, payload: JSON.parse(v), item_count: this.items.length, total_paise: this.t.total }) });
                if (res.ok) {
                    const d = await res.json();
                    this.lastSyncJson = v;
                    if (d.saved) this.savedAt = d.at;
                    if (d.billed) this.dropDraft();
                }
            } catch { /* offline: local copy is enough */ }
        },
        dropDraft() {
            try { localStorage.removeItem(draftStore); } catch { /* ignore */ }
            json(cfg.urls.draft, { method: 'DELETE' }).catch(() => {});
        },
        applyState(s) {
            this.draftKey = s.draftKey || this.draftKey;
            this.mobile = s.mobile || '';
            this.newCust = { ...this.newCust, ...(s.newCust || {}) };
            this.items = Array.isArray(s.items) ? s.items : [];
            this.disc = { ...this.disc, ...(s.disc || {}) };
            this.mode = s.mode || 'cash';
            this.split = { ...this.split, ...(s.split || {}) };
            this.laterPaid = s.laterPaid || '';
            this.sendWa = s.sendWa !== false;
            this.quotationId = s.quotationId || null;
            this.sel = this.items.length - 1;
            if (s.custState === 'walkin') this.custState = 'walkin';
            const d = mobileDigits(this.mobile);
            if (d.length === 10) {
                const carId = s.carId;
                this.lookup(d).then(() => { if (carId && this.cust?.cars?.some((c) => c.id === carId)) this.carId = carId; });
            }
            if (!isDesktop()) this.step = Math.min(s.step || 1, this.items.length ? 3 : 2);
        },
        restoreDraft() {
            let local = null;
            try { local = JSON.parse(localStorage.getItem(draftStore) || 'null'); } catch { local = null; }
            if (local?.state && (local.state.items?.length || local.state.mobile)) {
                this.applyState(local.state);
                this.restoredNote = 'Draft restored';
                return;
            }
            json(cfg.urls.draft).then((r) => (r.ok ? r.json() : null)).then((d) => {
                if (d?.draft?.payload && !this.hasContent) {
                    this.applyState(d.draft.payload);
                    this.restoredNote = 'Draft restored from your last device';
                }
            }).catch(() => {});
        },
        discardDraft() {
            this.dropDraft();
            this.restoredNote = null;
            this.items = [];
            this.sel = -1;
            this.resetCustomer();
            this.disc = { open: false, mode: 'flat', value: '', reason: '' };
            this.draftKey = uuid();
            this.step = 1;
        },

        // --------------------------------------------------------------------- prefill
        applyPrefill() {
            const p = cfg.prefill;
            this.quotationId = p.quotation_id || null;
            if (p.customer?.mobile) {
                this.mobile = prettyMobile(p.customer.mobile.replace(/^\+91/, ''));
                this.lookup(mobileDigits(this.mobile));
            }
            if (!isDesktop()) this.step = p.quotation_id ? 3 : (p.customer ? 2 : 1);
        },
        applyPrefillItems() {
            if (!cfg.prefill.active) return;
            for (const it of cfg.prefill.items) {
                const prod = search.byId(it.product_id);
                if (!prod) continue;
                this.addProduct(prod, it.qty);
                const line = this.items[this.sel];
                if (it.unit_price_paise !== null && it.unit_price_paise !== undefined) line.unit_price_paise = it.unit_price_paise;
            }
            if (cfg.prefill.quotation_id) this.restoredNote = 'Items copied from the quotation';
        },

        // --------------------------------------------------------------------- phone steps
        next() {
            if (this.step === 1) {
                if (this.custState === 'idle') { this.walkInSheet = true; return; }
                if (!this.customerReady) { this.flash(this.custState === 'new' ? 'Enter the customer’s name.' : 'Mobile number needs 10 digits.', 'bad'); return; }
                this.step = 2;
            } else if (this.step === 2) {
                if (!this.items.length) { this.flash('Add at least one item.', 'bad'); return; }
                this.step = 3;
            }
            window.scrollTo(0, 0);
        },
        back() {
            if (this.step > 1) { this.step--; window.scrollTo(0, 0); } else window.location.href = cfg.urls.home;
        },

        // --------------------------------------------------------------------- keyboard
        digitBuf: '',
        digitTimer: null,
        onKey(e) {
            const typing = typingIn(document.activeElement);
            if (e.key === 'F2') { e.preventDefault(); this.newBill(); return; }
            if (e.key === 'F3') { e.preventDefault(); (isDesktop() ? this.$refs.mobilePc : this.$refs.mobilePhone)?.focus(); return; }
            if (e.key === 'F4' || (e.key === '/' && !typing)) { e.preventDefault(); (isDesktop() ? this.$refs.searchPc : this.$refs.searchPhone)?.focus(); return; }
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); this.save(); return; }
            if (e.key === 'Escape') {
                if (this.qrOpen || this.disc.open || this.walkInSheet || this.custom.open) { this.qrOpen = false; this.disc.open = false; this.walkInSheet = false; this.custom.open = false; return; }
                if (typing) { document.activeElement.blur(); return; }
                if (this.undo) { this.undo = null; return; }
                // Nothing open or focused: back to Home (the draft is kept).
                if (isDesktop()) { this.persistLocal(this.snapshotJson); window.location.href = cfg.urls.home; }
                return;
            }
            if (typing || e.ctrlKey || e.metaKey || e.altKey || !isDesktop()) return;

            // Keys on the page itself (nothing focused).
            if (e.key === '+' || e.key === '=') { e.preventDefault(); this.bumpSel(1); return; }
            if (e.key === '-') { e.preventDefault(); this.bumpSel(-1); return; }
            if (e.key === 'Delete' || e.key === 'Backspace') { if (this.sel >= 0) { e.preventDefault(); this.removeLine(this.sel); } return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); this.sel = Math.min(this.sel + 1, this.items.length - 1); return; }
            if (e.key === 'ArrowUp') { e.preventDefault(); this.sel = Math.max(this.sel - 1, 0); return; }
            if (e.key === 'd' || e.key === 'D') { e.preventDefault(); this.disc.open = true; this.$nextTick(() => this.$refs.discValuePc?.focus()); return; }
            if (e.key === 'q' || e.key === 'Q') { if (this.qrAmount > 0 && cfg.upi.id) { e.preventDefault(); this.qrOpen = !this.qrOpen; } return; }

            // A digit could be a pay-mode key (1–5) or the start of a barcode scan.
            if (e.key.length === 1 && /[0-9A-Za-z]/.test(e.key)) {
                e.preventDefault();
                this.digitBuf += e.key;
                clearTimeout(this.digitTimer);
                if (this.digitBuf.length >= 2) {
                    // Fast typing = scanner (or someone typing a name): send it to the search box.
                    this.q = this.digitBuf;
                    this.digitBuf = '';
                    this.onSearchInput();
                    scan.reset();
                    for (let i = 0; i < 4; i++) scan.key({ key: 'x' });
                    this.$refs.searchPc?.focus();
                    return;
                }
                this.digitTimer = setTimeout(() => {
                    const k = this.digitBuf;
                    this.digitBuf = '';
                    const modes = { 1: 'cash', 2: 'upi', 3: 'card', 4: 'split', 5: 'later' };
                    if (modes[k]) this.setMode(modes[k]);
                    else if (k) { this.q = k; this.onSearchInput(); this.$refs.searchPc?.focus(); }
                }, 60);
            }
        },

        flash(msg, tone = 'ink') {
            clearTimeout(this.toast?.timer);
            this.toast = { msg, tone, timer: setTimeout(() => { this.toast = null; }, 4000) };
        },
    };
}

/** Bill done: polls the WhatsApp status and handles Share. */
export function billDone(cfg) {
    return {
        status: cfg.initialStatus,
        automatic: cfg.automatic,
        manualLink: cfg.manualLink,
        polls: 0,
        init() {
            if (cfg.sendWhatsapp && this.automatic) this.poll();
        },
        poll() {
            json(cfg.statusUrl).then((r) => (r.ok ? r.json() : null)).then((d) => {
                if (d) { this.status = d.whatsapp; this.manualLink = d.manual_link; }
                if (++this.polls < 40 && !['read', 'failed'].includes(this.status)) setTimeout(() => this.poll(), this.polls < 10 ? 1500 : 5000);
            }).catch(() => setTimeout(() => this.poll(), 5000));
        },
        async share() {
            try {
                if (navigator.canShare) {
                    const res = await fetch(cfg.pdfUrl, { credentials: 'same-origin' });
                    const file = new File([await res.blob()], cfg.fileName, { type: 'application/pdf' });
                    if (navigator.canShare({ files: [file] })) {
                        await navigator.share({ files: [file], title: cfg.title, text: cfg.text });
                        return;
                    }
                }
                if (navigator.share) { await navigator.share({ title: cfg.title, text: cfg.text, url: cfg.publicUrl }); return; }
                await navigator.clipboard.writeText(cfg.publicUrl);
                alert('Link to the PDF copied. Paste it in any chat.');
            } catch (e) {
                if (e?.name !== 'AbortError') window.open(cfg.pdfUrl, '_blank');
            }
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('billForm', billForm);
    window.Alpine.data('billDone', billDone);
});
