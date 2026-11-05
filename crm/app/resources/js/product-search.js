/*
 * Instant item search (UX-FLOWS owner decision 2). The whole product list is fetched once from
 * the products index (GET /api/v1/products/index, ETag: the browser revalidates and gets a 304
 * when nothing changed) and every keystroke is matched in memory, with no server wait.
 *
 * Matching:
 *   - every typed word must match the start of a word in the name, brand, SKU or an
 *     "Also called" alias ("seat kavar", "music system");
 *   - a word of 4+ letters may have one typo (substitution, extra/missing letter or a swap),
 *     so "gleem" finds Gleam; exact prefixes always rank above typo matches;
 *   - an exact SKU or barcode wins outright and is flagged `exact` (the caller may add it at once);
 *   - "2*gleam" (also 2x / 2×) means quantity 2;
 *   - ties go to the best seller.
 *
 * Used by quotations (create + asked-price sheet) and, later, billing's POS:
 *   import { createProductSearch } from './product-search';
 *   const s = createProductSearch();
 *   await s.load(url);
 *   s.search('2*gleem', 10)   → { qty: 2, exact: false, results: [product…] }
 *   s.popular(12) / s.recent(8) / s.remember(product) / s.byId(id)
 * app.js also exposes one shared instance as window.jgProductSearch for inline Alpine components.
 *
 * Each product keeps the API fields and gains:
 *   price_paise       GST-inclusive selling price (what the customer pays per unit)
 *   base_price_paise  the stored price as entered (see price_includes_gst)
 */

export const normalize = (s) => String(s ?? '').toLowerCase().normalize('NFKD')
    .replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9.]+/g, ' ').replace(/(^|\s)\.+|\.+(\s|$)/g, ' ').trim();

export const words = (s) => normalize(s).split(' ').filter(Boolean);

const compact = (s) => normalize(s).replace(/[\s.]+/g, '');

/** Same rounding as App\Services\Quotations\QuoteTotals::inclusive (integer paise, half-up). */
export function inclusivePaise(pricePaise, gstRate, alreadyInclusive) {
    const p = Number(pricePaise) || 0;
    if (alreadyInclusive !== false) return p;
    const bp = Math.round((Number(gstRate) || 0) * 100);
    return p + Math.floor((p * bp * 2 + 10000) / 20000);
}

/** True when a and b differ by at most one edit (insert, delete, substitute, or swap of neighbours). */
export function withinOneEdit(a, b) {
    if (a === b) return true;
    const la = a.length, lb = b.length;
    if (Math.abs(la - lb) > 1) return false;
    let i = 0;
    while (i < la && i < lb && a[i] === b[i]) i++;
    if (la === lb) {
        if (a.slice(i + 1) === b.slice(i + 1)) return true; // substitution
        return a[i] === b[i + 1] && a[i + 1] === b[i] && a.slice(i + 2) === b.slice(i + 2); // swap
    }
    return la > lb ? a.slice(i + 1) === b.slice(i) : a.slice(i) === b.slice(i + 1);
}

/** How a typed word matches a product word: 2 = prefix, 1 = prefix with one typo, 0 = no match. */
export function matchWord(typed, word) {
    if (word.startsWith(typed)) return 2;
    if (typed.length < 4) return 0;
    const n = typed.length;
    for (const len of [n, n - 1, n + 1]) {
        if (len > 0 && len <= word.length && withinOneEdit(typed, word.slice(0, len))) return 1;
    }
    return 0;
}

/** Splits "2*gleam" / "2 x gleam" / "2×gleam" into { qty: 2, text: 'gleam' }. */
export function parseQuantity(query) {
    const q = String(query ?? '').trim();
    const m = q.match(/^(\d{1,3})\s*[*x×]\s*(\S.*)$/i);
    return m ? { qty: Math.max(1, parseInt(m[1], 10)), text: m[2] } : { qty: 1, text: q };
}

function prepare(raw) {
    const aliases = Array.isArray(raw.aliases) ? raw.aliases : String(raw.aliases ?? '').split(',').filter((a) => a.trim());
    const nameWords = words(raw.name);
    const basePrice = Number(raw.base_price_paise ?? raw.price_paise) || 0;
    const price = raw.base_price_paise !== undefined ? Number(raw.price_paise) || 0
        : inclusivePaise(basePrice, raw.gst_rate, raw.price_includes_gst);
    return {
        ...raw,
        aliases,
        brand: raw.brand ?? null,
        base_price_paise: basePrice,
        price_paise: price,
        mrp_paise: raw.mrp_paise ?? null,
        gst_rate: Number(raw.gst_rate) || 0,
        sold_count: Number(raw.sold_count) || 0,
        on_hand: raw.track_stock === false ? null : (raw.on_hand ?? null),
        _name: nameWords,
        _words: [...new Set([...nameWords, ...words(raw.brand), ...words(raw.sku), ...aliases.flatMap(words)])],
        _sku: compact(raw.sku),
        _barcode: compact(raw.barcode),
    };
}

export function createProductSearch({ storageKey = 'jg.recent-products', recentMax = 12 } = {}) {
    let list = [];
    let ids = new Map();
    let loading = null;
    let loadedUrl = null;

    const readRecent = () => {
        try { return JSON.parse(window.localStorage.getItem(storageKey) || '[]'); } catch { return []; }
    };

    const api = {
        /** Fetches the list once per URL (accepts {data:[…]} from the products API or {products:[…]}). */
        load(url) {
            if (loading && loadedUrl === url) return loading;
            loadedUrl = url;
            loading = fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then((r) => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then((d) => api.setProducts(d.data ?? d.products ?? []))
                .catch((e) => { loading = null; throw e; });
            return loading;
        },
        /** For tests and pages that embed the list. */
        setProducts(products) {
            list = products.map(prepare);
            ids = new Map(list.map((p) => [p.id, p]));
            return list;
        },
        get ready() { return list.length > 0; },
        get products() { return list; },
        byId(id) { return ids.get(Number(id)) ?? null; },

        popular(limit = 12) {
            return [...list].sort((a, b) => b.sold_count - a.sold_count || a.name.localeCompare(b.name)).slice(0, limit);
        },
        recent(limit = 8) {
            return readRecent().map((id) => ids.get(id)).filter(Boolean).slice(0, limit);
        },
        remember(product) {
            const id = Number(product?.id ?? product);
            if (!id) return;
            const next = [id, ...readRecent().filter((x) => x !== id)].slice(0, recentMax);
            try { window.localStorage.setItem(storageKey, JSON.stringify(next)); } catch { /* private mode */ }
        },

        search(query, limit = 12) {
            const { qty, text } = parseQuantity(query);
            const typed = words(text);
            if (!typed.length) return { qty, exact: false, results: [] };

            const code = compact(text);
            const exact = code.length >= 3 ? list.find((p) => (p._sku && p._sku === code) || (p._barcode && p._barcode === code)) : null;

            const scored = [];
            for (const p of list) {
                if (p === exact) continue;
                let score = 0;
                let ok = true;
                for (const t of typed) {
                    let best = 0;
                    for (const w of p._words) {
                        const m = matchWord(t, w);
                        if (m > best) best = m;
                        if (best === 2) break;
                    }
                    if (!best) { ok = false; break; }
                    score += best === 2 ? 1000 : 400;
                }
                if (!ok) continue;
                if (p._name[0] && matchWord(typed[0], p._name[0]) === 2) score += 300; // name starts with it
                if (p._sku && p._sku.startsWith(code)) score += 200;
                scored.push([score + Math.min(p.sold_count, 199), p]);
            }
            scored.sort((a, b) => b[0] - a[0] || a[1].name.localeCompare(b[1].name));

            const results = scored.slice(0, exact ? limit - 1 : limit).map((x) => x[1]);
            return { qty, exact: !!exact, results: exact ? [exact, ...results] : results };
        },
    };

    return api;
}

/** Indian money from paise: 123450 → "₹1,234.50", 4999900 → "₹49,999". */
export function rupees(paise) {
    const neg = paise < 0;
    const p = Math.abs(Math.round(paise));
    const r = Math.floor(p / 100), f = p % 100;
    let s = String(r);
    if (s.length > 3) s = s.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + s.slice(-3);
    return (neg ? '−' : '') + '₹' + s + (f ? '.' + String(f).padStart(2, '0') : '');
}

export default createProductSearch;
