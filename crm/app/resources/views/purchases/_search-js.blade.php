<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
/*
 * New purchase screen. The whole stock-item list is fetched once and searched in the browser
 * (UX-FLOWS owner decision 2): every typed word must start a word of the name / brand / code /
 * "also called" names, one typo is forgiven on words of 4+ letters, an exact code or barcode
 * wins, and "10*gleam" adds ten.
 * MERGE NOTE: when billing's resources/js/product-search.js lands, point search() at it.
 */
window.jgRupees = window.jgRupees || function (paise) {
    const neg = paise < 0; paise = Math.abs(Math.round(paise));
    const r = Math.floor(paise / 100), f = paise % 100;
    let s = String(r);
    if (s.length > 3) s = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + s.slice(-3);
    return (neg ? '−' : '') + '₹' + s + (f ? '.' + String(f).padStart(2, '0') : '');
};

window.purchaseForm = function (init) {
    const norm = (s) => String(s || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9.]+/g, ' ').trim();
    const words = (s) => norm(s).split(' ').filter(Boolean);
    // true when a and b differ by at most one edit (insert, delete or change)
    const near = (a, b) => {
        if (Math.abs(a.length - b.length) > 1) return false;
        let i = 0, j = 0, edits = 0;
        while (i < a.length && j < b.length) {
            if (a[i] === b[j]) { i++; j++; continue; }
            if (++edits > 1) return false;
            if (a.length > b.length) i++; else if (b.length > a.length) j++; else { i++; j++; }
        }
        return edits + (a.length - i) + (b.length - j) <= 1;
    };
    const toPaise = (v) => {
        const n = parseFloat(String(v ?? '').replace(/[,₹\s]/g, ''));
        return Number.isFinite(n) ? Math.round(n * 100) : 0;
    };
    const gstOf = (paise, rate) => {
        const bp = Math.round(parseFloat(rate || 0) * 100);
        return Math.floor((paise * bp * 2 + 10000) / 20000);
    };
    let seq = 0;

    return {
        list: [], ready: false, loadError: false,
        query: '', open: false, active: 0, submitting: false, paid: init.paid || '',
        newSupplier: init.newSupplier,
        gstRates: init.gstRates,
        lines: (init.items || []).map((l) => ({ ...l, key: ++seq, hint: '' })),

        boot() {
            fetch(init.productsUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => { if (!r.ok) throw new Error(r.status); return r.json(); })
                .then((d) => {
                    this.list = d.products.map((p) => ({ ...p, _w: [...words(p.name), ...words(p.brand), ...words(p.sku), ...words(p.aliases)], _sku: norm(p.sku) }));
                    this.ready = true;
                })
                .catch(() => { this.loadError = true; });
        },

        get parsed() {
            let q = this.query.trim(), qty = 1;
            const m = q.match(/^(\d{1,5})\s*[*x×]\s*(.+)$/i);
            if (m) { qty = Math.max(1, parseInt(m[1], 10)); q = m[2]; }
            return { q, qty };
        },

        get results() {
            if (!this.ready) return [];
            const { q } = this.parsed;
            const typed = words(q);
            if (!typed.length) return [...this.list].sort((a, b) => b.sold_count - a.sold_count).slice(0, 8);
            const sku = norm(q), out = [];
            for (const p of this.list) {
                let score;
                if (p._sku && p._sku === sku) score = 1e9;
                else {
                    let ok = true, fuzzy = 0;
                    for (const t of typed) {
                        if (p._w.some((w) => w.startsWith(t))) continue;
                        if (t.length >= 4 && p._w.some((w) => near(w.slice(0, t.length), t) || near(w, t))) { fuzzy++; continue; }
                        ok = false; break;
                    }
                    if (!ok) continue;
                    score = 1000 - fuzzy * 400 + (p._w[0]?.startsWith(typed[0]) ? 300 : 0) + Math.min(p.sold_count, 299);
                }
                out.push([score, p]);
            }
            out.sort((a, b) => b[0] - a[0] || a[1].name.localeCompare(b[1].name));
            return out.slice(0, 12).map((x) => x[1]);
        },

        move(step) {
            const n = this.results.length;
            if (!n) return;
            this.open = true;
            this.active = (this.active + step + n) % n;
        },

        pick(p) {
            if (!p) return;
            const { qty } = this.parsed;
            const existing = this.lines.find((l) => l.product_id === p.id);
            if (existing) {
                existing.qty = String((parseInt(existing.qty, 10) || 0) + qty);
            } else {
                this.lines.push({
                    key: ++seq, product_id: p.id, name: p.name, qty: String(qty),
                    cost: p.cost_paise ? (p.cost_paise / 100).toFixed(2).replace(/\.00$/, '') : '',
                    gst_rate: String(Math.round(p.gst_rate)),
                    hint: [p.sku, 'in stock ' + p.on_hand + ' ' + (p.unit || 'pcs'), p.cost_paise ? 'last cost ' + jgRupees(p.cost_paise) : 'no cost yet'].filter(Boolean).join(' · '),
                });
            }
            this.query = ''; this.active = 0; this.open = false;
            this.$nextTick(() => { this.$refs.search.focus(); this.open = false; }); // stay in the box, list closed until typing
        },

        remove(i) { this.lines.splice(i, 1); this.$refs.search.focus(); },

        lineTaxable(l) { return (parseInt(l.qty, 10) || 0) * toPaise(l.cost); },
        lineTotal(l) { const t = this.lineTaxable(l); return t + gstOf(t, l.gst_rate); },
        get subtotal() { return this.lines.reduce((s, l) => s + this.lineTaxable(l), 0); },
        get gst() { return this.lines.reduce((s, l) => s + gstOf(this.lineTaxable(l), l.gst_rate), 0); },
        get totalQty() { return this.lines.reduce((s, l) => s + (parseInt(l.qty, 10) || 0), 0); },
        get due() { return Math.max(0, this.subtotal + this.gst - toPaise(this.paid)); },
    };
};
</script>
