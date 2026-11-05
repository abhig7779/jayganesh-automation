<script>
/*
 * Item search: ONE boundary the quotation screens call.
 *   jgProductSearch.load(url)          → Promise (fetches the JSON list once)
 *   jgProductSearch.search(q, limit)   → { qty, results: [product…] }
 *   jgProductSearch.popular(limit)     → top products by sold_count
 * MERGE NOTE (for the lead): billing ships resources/js/product-search.js (typo-tolerant,
 * recent/popular, bills.product-index). Point these three functions at it and delete this file.
 * This simple version: every typed word must prefix-match a word of the name / SKU / brand,
 * an exact SKU wins, then the best sellers. "2*gleam" adds 2.
 */
window.jgProductSearch = window.jgProductSearch || (function () {
    let list = [];
    let loading = null;
    const norm = (s) => String(s || '').toLowerCase().normalize('NFKD').replace(/[^a-z0-9.]+/g, ' ').trim();
    const words = (s) => norm(s).split(' ').filter(Boolean);

    return {
        load(url) {
            loading = loading || fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then((d) => {
                    list = d.products.map((p) => ({ ...p, _w: [...words(p.name), ...words(p.brand), ...words(p.sku)], _sku: norm(p.sku) }));
                    return list;
                })
                .catch((e) => { loading = null; throw e; });
            return loading;
        },
        get ready() { return list.length > 0; },
        popular(limit = 12) {
            return [...list].sort((a, b) => b.sold_count - a.sold_count).slice(0, limit);
        },
        search(query, limit = 12) {
            let q = String(query || '').trim();
            let qty = 1;
            const m = q.match(/^(\d{1,3})\s*[*x×]\s*(.+)$/i);
            if (m) { qty = Math.max(1, parseInt(m[1], 10)); q = m[2]; }
            const typed = words(q);
            if (!typed.length) return { qty, results: [] };
            const sku = norm(q);
            const scored = [];
            for (const p of list) {
                let score = 0;
                if (p._sku && p._sku === sku) score = 1e9;
                else {
                    let ok = true;
                    for (const t of typed) {
                        if (!p._w.some((w) => w.startsWith(t))) { ok = false; break; }
                    }
                    if (!ok) continue;
                    score = 1000 + (p._w[0]?.startsWith(typed[0]) ? 500 : 0) + Math.min(p.sold_count, 499);
                }
                scored.push([score, p]);
            }
            scored.sort((a, b) => b[0] - a[0] || a[1].name.localeCompare(b[1].name));
            return { qty, results: scored.slice(0, limit).map((x) => x[1]) };
        },
    };
})();

window.jgRupees = function (paise) {
    const neg = paise < 0; paise = Math.abs(Math.round(paise));
    const r = Math.floor(paise / 100), f = paise % 100;
    let s = String(r);
    if (s.length > 3) s = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + s.slice(-3);
    return (neg ? '−' : '') + '₹' + s + (f ? '.' + String(f).padStart(2, '0') : '');
};
</script>
