/**
 * Instant product search for the counter (bills, and later quotations).
 *
 * Loads every active product once from GET /bills/product-index (cached by the browser with
 * ETag / Last-Modified) and searches in memory, so there is no server round trip per keystroke.
 * 5,000 products search in a few milliseconds.
 *
 * Rules (mirrored in app/Support/SearchText.php, keep both in step):
 *  - text is lowercased; dots dropped ("l.e.d" = "led"); a hyphen/slash/apostrophe between
 *    letters or digits is dropped ("h-4" = "h4"); other punctuation is a space; "7 d" = "7d"
 *    and "h 4" = "h4".
 *  - every typed word must match the START of a word in the name, brand, SKU or aliases;
 *    words of 4+ letters also match with one typo ("gleem" → Gleam, "seet" → Seat).
 *  - an exact SKU / barcode match wins outright.
 *  - ranking: exact word > prefix > typo, then recently used on this PC, then sold count,
 *    and in-stock above out-of-stock.
 *  - "2*gleam" or "gleam*2" asks for quantity 2.
 */

const SINGLE_LETTER = /^\p{L}$/u;
const DIGITS = /^\d+$/;

export function tokens(text) {
    let s = String(text ?? '').toLowerCase();
    s = s.replace(/\./g, '');
    s = s.replace(/(?<=[\p{L}\p{N}])[-'’](?=[\p{L}\p{N}])/gu, '');
    s = s.replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
    if (!s) return [];
    const raw = s.split(/\s+/);
    const out = [];
    for (let i = 0; i < raw.length; i++) {
        const t = raw[i];
        const next = raw[i + 1];
        if (next !== undefined && ((DIGITS.test(t) && SINGLE_LETTER.test(next)) || (SINGLE_LETTER.test(t) && DIGITS.test(next)))) {
            out.push(t + next);
            i++;
            continue;
        }
        out.push(t);
    }
    return out;
}

export function normalize(text) {
    return tokens(text).join(' ');
}

/** SKU / barcode compare key. */
export function code(text) {
    return String(text ?? '').toLowerCase().replace(/[^a-z0-9]/g, '');
}

/** True when a and b differ by at most one insert, delete, substitution or adjacent swap. */
export function withinOneEdit(a, b) {
    if (a === b) return true;
    const la = a.length;
    const lb = b.length;
    if (Math.abs(la - lb) > 1) return false;
    let i = 0;
    while (i < la && i < lb && a[i] === b[i]) i++;
    if (la === lb) {
        if (a.slice(i + 1) === b.slice(i + 1)) return true; // substitution
        return a[i] === b[i + 1] && a[i + 1] === b[i] && a.slice(i + 2) === b.slice(i + 2); // swap
    }
    return la > lb ? a.slice(i + 1) === b.slice(i) : a.slice(i) === b.slice(i + 1);
}

/** "2*gleam" / "gleam*2" / "2 x gleam" → { qty: 2, term: 'gleam' } */
export function parseQty(query) {
    const q = String(query ?? '').trim();
    let m = q.match(/^(\d{1,4})\s*[*x×]\s*(.+)$/i);
    if (m && !/^\d+$/.test(m[2].trim())) return { qty: Math.max(1, parseInt(m[1], 10)), term: m[2].trim() };
    m = q.match(/^(.+?)\s*[*×]\s*(\d{1,4})$/);
    if (m) return { qty: Math.max(1, parseInt(m[2], 10)), term: m[1].trim() };
    return { qty: 1, term: q };
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

/** Map the compact index row to a friendly product object. */
function expand(r) {
    return {
        id: r.i,
        name: r.n,
        sku: r.s || null,
        brand: r.b || null,
        type: r.t === 's' ? 'service' : 'product',
        price_paise: r.p,
        mrp_paise: r.m || null,
        gst_rate: r.g,
        price_includes_gst: r.x !== 0,
        on_hand: r.h,
        track_stock: r.k === 1,
        sold_count: r.c || 0,
        unit: r.u || 'pcs',
        aliases: r.a || '',
    };
}

/** Accept a compact index row or a full ProductResource row. */
function fromAny(r) {
    if (r && r.i !== undefined) return expand(r);
    return {
        ...r,
        id: r.id,
        brand: r.brand || null,
        sku: r.sku || null,
        aliases: Array.isArray(r.aliases) ? r.aliases.join(', ') : (r.aliases || ''),
        sold_count: r.sold_count || 0,
        gst_rate: Number(r.gst_rate) || 0,
        price_includes_gst: r.price_includes_gst !== false,
    };
}

export function inStock(p) {
    return !p.track_stock || p.on_hand > 0;
}

export class ProductSearch {
    constructor({ url, recentKey = 'jg.recentProducts', recentMax = 8 } = {}) {
        this.url = url;
        this.recentKey = recentKey;
        this.recentMax = recentMax;
        this.products = [];
        this.entries = [];
        this.byIdMap = new Map();
        this.byCode = new Map();
        this.loadedAt = 0;
        this.loading = null;
    }

    /** Fetch the index (browser revalidates with ETag). Resolves to the product count. */
    load(force = false) {
        if (this.loading && !force) return this.loading;
        this.loading = fetch(this.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-cache' })
            .then((r) => {
                if (!r.ok) throw new Error('Could not load products (' + r.status + ')');
                return r.json();
            })
            .then((data) => {
                // bills.product-index sends compact rows {products:[{i,n,…}]}; the products API
                // (/api/v1/products/index) sends full rows {data:[{id,name,…}]}. Both work.
                this.setProducts((data.products || data.data || []).map(fromAny));
                this.loadedAt = Date.now();
                return this.products.length;
            })
            .finally(() => {
                this.loading = null;
            });
        return this.loading;
    }

    /** Re-fetch when the tab regains focus and the copy is older than maxAgeMs. */
    refreshIfStale(maxAgeMs = 60_000) {
        if (Date.now() - this.loadedAt > maxAgeMs) return this.load().catch(() => null);
        return Promise.resolve(this.products.length);
    }

    setProducts(products) {
        this.products = products;
        this.byIdMap = new Map(products.map((p) => [p.id, p]));
        this.byCode = new Map();
        this.entries = products.map((p) => {
            const words = [...new Set(tokens([p.name, p.brand, p.sku, p.aliases].filter(Boolean).join(' ')))];
            if (p.sku) this.byCode.set(code(p.sku), p);
            return { p, words };
        });
    }

    byId(id) {
        return this.byIdMap.get(Number(id)) || null;
    }

    /** Exact SKU / barcode match, or null. */
    exact(term) {
        const c = code(term);
        return c.length >= 2 ? this.byCode.get(c) || null : null;
    }

    recentIds() {
        try {
            return JSON.parse(localStorage.getItem(this.recentKey) || '[]').filter(Number.isFinite);
        } catch {
            return [];
        }
    }

    /** Remember a product as used on this PC (for Recent and ranking). */
    touch(id) {
        try {
            const ids = [Number(id), ...this.recentIds().filter((x) => x !== Number(id))].slice(0, 50);
            localStorage.setItem(this.recentKey, JSON.stringify(ids));
        } catch {
            /* private mode: ranking just skips the recent boost */
        }
    }

    recent(n = this.recentMax) {
        return this.recentIds().map((id) => this.byId(id)).filter(Boolean).slice(0, n);
    }

    popular(n = 12) {
        return [...this.products].sort((a, b) => b.sold_count - a.sold_count || a.name.localeCompare(b.name)).slice(0, n);
    }

    /**
     * @returns {Array<{product, score, match: 'code'|'exact'|'prefix'|'fuzzy', html: string}>}
     */
    search(query, limit = 8) {
        const term = parseQty(query).term;
        const qWords = tokens(term);
        if (!qWords.length) return [];

        const hit = this.exact(term);
        const recent = this.recentIds();
        const recentRank = new Map(recent.map((id, i) => [id, i]));
        const out = [];

        for (const e of this.entries) {
            let quality = 0;
            let worst = 3;
            let ok = true;
            for (const qw of qWords) {
                let best = 0;
                for (const w of e.words) {
                    if (w === qw) { best = 3; break; }
                    if (best < 2 && w.startsWith(qw)) best = 2;
                    else if (best < 1 && qw.length >= 4 && (
                        withinOneEdit(qw, w.slice(0, qw.length)) ||
                        withinOneEdit(qw, w.slice(0, qw.length + 1)) ||
                        (w.length >= qw.length - 1 && withinOneEdit(qw, w.slice(0, qw.length - 1)))
                    )) best = 1;
                }
                if (!best) { ok = false; break; }
                quality += best;
                worst = Math.min(worst, best);
            }
            if (!ok && e.p !== hit) continue;

            const p = e.p;
            let score = quality * 1000 + worst * 300;
            if (p === hit) score += 1_000_000;
            if (recentRank.has(p.id)) score += 200 - recentRank.get(p.id) * 3;
            score += Math.min(150, Math.log1p(p.sold_count) * 20);
            if (inStock(p)) score += 120;
            out.push({ product: p, score, match: p === hit ? 'code' : worst === 3 ? 'exact' : worst === 2 ? 'prefix' : 'fuzzy', words: qWords });
        }

        out.sort((a, b) => b.score - a.score || a.product.name.localeCompare(b.product.name));
        return out.slice(0, limit).map((r) => ({ ...r, html: highlight(r.product.name, r.words) }));
    }
}

/** Bold the letters of each word in `text` that a query word matched (prefix or typo). */
export function highlight(text, qWords) {
    return String(text ?? '').split(/(\s+)/).map((part) => {
        if (!part.trim()) return part;
        const norm = tokens(part).join('');
        let len = 0;
        for (const qw of qWords) {
            if (norm.startsWith(qw)) len = Math.max(len, qw.length);
            else if (qw.length >= 4 && withinOneEdit(qw, norm.slice(0, qw.length))) len = Math.max(len, Math.min(qw.length, norm.length));
        }
        if (!len) return escapeHtml(part);
        // Walk the original characters until `len` letters/digits are covered.
        let seen = 0;
        let cut = 0;
        while (cut < part.length && seen < len) {
            if (/[\p{L}\p{N}]/u.test(part[cut])) seen++;
            cut++;
        }
        return '<b>' + escapeHtml(part.slice(0, cut)) + '</b>' + escapeHtml(part.slice(cut));
    }).join('');
}

/**
 * Barcode scanners type very fast and end with Enter. Feed every keydown; isScan() is true
 * when the last 4+ characters arrived less than ~35 ms apart on average.
 */
export class ScanDetector {
    constructor(maxAvgMs = 35, minChars = 4) {
        this.maxAvgMs = maxAvgMs;
        this.minChars = minChars;
        this.times = [];
    }

    key(e) {
        if (e.key && e.key.length === 1) {
            const now = performance.now();
            if (this.times.length && now - this.times[this.times.length - 1] > 120) this.times = [];
            this.times.push(now);
        } else if (e.key !== 'Enter' && e.key !== 'Shift') {
            this.times = [];
        }
    }

    isScan() {
        const t = this.times;
        if (t.length < this.minChars) return false;
        return (t[t.length - 1] - t[0]) / (t.length - 1) <= this.maxAvgMs;
    }

    reset() {
        this.times = [];
    }
}

/** Same rounding as App\\Services\\Quotations\\QuoteTotals::inclusive (integer paise). */
export function inclusivePaise(pricePaise, gstRate, alreadyInclusive) {
    const p = Number(pricePaise) || 0;
    if (alreadyInclusive !== false) return p;
    const bp = Math.round((Number(gstRate) || 0) * 100);
    return p + Math.floor((p * bp * 2 + 10000) / 20000);
}

/** Indian money from paise: 123450 → "₹1,234.50". */
export function rupees(paise) {
    const neg = paise < 0;
    const p = Math.abs(Math.round(paise));
    const r = Math.floor(p / 100);
    const f = p % 100;
    let s = String(r);
    if (s.length > 3) s = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + s.slice(-3);
    return (neg ? '−' : '') + '₹' + s + (f ? '.' + String(f).padStart(2, '0') : '');
}

/**
 * The quotation screens' small API over the same matcher (window.jgProductSearch):
 *   load(url) → Promise · search(q, limit) → { qty, exact, results } · popular(n) · remember(p)
 * Products come back with price_paise GST-inclusive (what the customer pays per unit) and the
 * stored price in base_price_paise.
 */
export function createProductSearch() {
    const engine = new ProductSearch({ recentKey: 'jg.recentProducts' });
    const withPrice = (p) => (p.base_price_paise !== undefined ? p : Object.assign(p, {
        base_price_paise: p.price_paise,
        price_paise: inclusivePaise(p.price_paise, p.gst_rate, p.price_includes_gst),
        on_hand: p.track_stock === false ? null : p.on_hand,
    }));
    let loadedUrl = null;
    let loading = null;

    return {
        engine,
        load(url) {
            if (loading && loadedUrl === url) return loading;
            loadedUrl = url;
            engine.url = url;
            loading = engine.load(true).then((n) => { engine.products.forEach(withPrice); return n; })
                .catch((e) => { loading = null; throw e; });
            return loading;
        },
        get ready() { return engine.products.length > 0; },
        byId: (id) => engine.byId(id),
        popular: (n = 12) => engine.popular(n),
        recent: (n = 8) => engine.recent(n),
        remember: (p) => engine.touch(p?.id ?? p),
        search(query, limit = 12) {
            const { qty } = parseQty(query);
            const rows = engine.search(query, limit);
            return { qty, exact: rows[0]?.match === 'code', results: rows.map((r) => r.product) };
        },
    };
}

if (typeof window !== 'undefined') {
    window.ProductSearch = ProductSearch;
    window.jgProductSearch = window.jgProductSearch || createProductSearch();
    window.jgRupees = window.jgRupees || rupees;
}
