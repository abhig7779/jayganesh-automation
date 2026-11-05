/**
 * Live bill totals for the New bill screens. Mirrors app/Services/Billing/GstCalculator.php
 * (integer paise, inclusive/exclusive lines, bill discount spread by value, round-off to the
 * rupee). The server recalculates on save and its numbers are the ones stored.
 */

const div = (a, b) => Math.floor((2 * a + b) / (2 * b)); // half-up, non-negative
const pct = (paise, bp) => Math.floor((2 * paise * bp + 10000) / 20000); // Money::percent

export function allocate(amount, weights) {
    const total = weights.reduce((s, w) => s + w, 0);
    if (amount <= 0 || total <= 0) return weights.map(() => 0);
    const parts = weights.map((w) => Math.floor((amount * w) / total));
    const rem = weights.map((w, i) => [(amount * w) % total, i]);
    let left = amount - parts.reduce((s, p) => s + p, 0);
    rem.sort((a, b) => b[0] - a[0] || a[1] - b[1]);
    for (const [, i] of rem) {
        if (left-- <= 0) break;
        parts[i]++;
    }
    return parts;
}

/**
 * @param {Array<{qty:number, unit_price_paise:number, gst_rate:number, price_includes_gst?:boolean, mrp_paise?:number|null}>} lines
 */
export function calculate(lines, billDiscount = 0, { registered = true, intra = true } = {}) {
    const prepared = lines.map((l) => {
        const bp = registered ? Math.round(Number(l.gst_rate || 0) * 100) : 0;
        const incl = l.price_includes_gst !== false || bp === 0;
        const gross = l.unit_price_paise * l.qty;
        const exTax = (t) => (intra ? 2 * pct(t, bp / 2) : pct(t, bp));
        return { ...l, bp, incl, basis: gross, grossIncl: incl ? gross : gross + exTax(gross) };
    });
    const weights = prepared.map((p) => p.grossIncl);
    const disc = Math.min(Math.max(0, billDiscount | 0), weights.reduce((s, w) => s + w, 0));
    const shares = allocate(disc, weights);

    let taxable = 0, cgst = 0, sgst = 0, igst = 0, before = 0, gross = 0, mrpSave = 0;
    const rates = new Set();
    let cgstLead = 0; // odd paise alternate so CGST and SGST totals differ by at most 1 paisa
    prepared.forEach((p, i) => {
        const share = shares[i];
        let t, c = 0, s = 0, g = 0, lineTotal;
        if (p.incl) {
            lineTotal = p.grossIncl - share;
            t = div(lineTotal * 10000, 10000 + p.bp);
            const tax = lineTotal - t;
            if (intra) {
                const half = Math.floor(tax / 2), odd = tax % 2, toC = odd && cgstLead <= 0;
                c = half + (toC ? odd : 0);
                s = half + (toC ? 0 : odd);
                cgstLead += c - s;
            } else g = tax;
        } else {
            t = p.basis - (share > 0 ? div(share * 10000, 10000 + p.bp) : 0);
            if (intra) { c = pct(t, p.bp / 2); s = c; } else g = pct(t, p.bp);
            lineTotal = t + c + s + g;
        }
        if (p.bp > 0) rates.add(p.bp / 100);
        taxable += t; cgst += c; sgst += s; igst += g; before += lineTotal; gross += p.grossIncl;
        if (p.mrp_paise) mrpSave += Math.max(0, p.mrp_paise * p.qty - p.grossIncl);
    });
    const total = Math.floor((before + 50) / 100) * 100;
    return {
        taxable, cgst, sgst, igst, tax: cgst + sgst + igst,
        gross, before, discount: gross - before, roundOff: total - before, total,
        mrpSave, savings: mrpSave + (gross - before), mrpValue: before + mrpSave + (gross - before),
        rates: [...rates].sort((a, b) => a - b),
    };
}

/** ₹1,23,456 (paise shown only when not zero). */
export function money(paise, { decimals = false } = {}) {
    const neg = paise < 0;
    const abs = Math.abs(Math.round(paise));
    const r = Math.floor(abs / 100);
    const p = abs % 100;
    let s = '₹' + r.toLocaleString('en-IN');
    if (decimals || p) s += '.' + String(p).padStart(2, '0');
    return (neg ? '−' : '') + s;
}

/** "1,499.50" / "1499" → paise (NaN when not a number). */
export function toPaise(v) {
    const s = String(v ?? '').replace(/[,₹\s]/g, '');
    if (s === '') return 0;
    if (!/^\d+(\.\d{1,2})?$/.test(s)) return NaN;
    const [w, f = ''] = s.split('.');
    return parseInt(w, 10) * 100 + parseInt((f + '00').slice(0, 2), 10);
}
