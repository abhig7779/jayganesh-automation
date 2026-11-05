<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
/*
 * Follow-ups work queue (Alpine). PC: list left, selected customer right, keyboard driven.
 * Phone: rows with big buttons and a Log result bottom sheet. Saving goes through htmx
 * (htmx.ajax → FollowUpController@log, which answers with an HX-Trigger event).
 */
window.followupsQueue = function (cfg) {
    const iso = (d) => {
        const z = (n) => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate());
    };
    const plus = (n) => { const d = new Date(); d.setDate(d.getDate() + n); return iso(d); };

    return {
        items: [...cfg.rows, ...cfg.upcoming].map((r) => ({ ...r, leaving: false })),
        outcomeList: cfg.outcomes,
        selId: null,
        filter: 'all',
        search: '',
        msg: '',
        sheet: false,
        outcome: null,
        date: '',
        note: '',
        error: '',
        saving: false,
        toast: null,
        toastTimer: null,
        today: plus(0),

        init() {
            const want = this.items.find((r) => r.id === cfg.select);
            if (want) {
                if (want.bucket === 'upcoming') this.filter = 'upcoming';
                this.select(want.id);
                if (window.matchMedia('(max-width: 1023px)').matches) this.sheet = true;
            } else if (this.visible.length) {
                this.select(this.visible[0].id);
            }
            window.addEventListener('htmx:responseError', (e) => this.onError(e.detail));
        },

        matches(r, f) {
            const due = r.bucket !== 'upcoming';
            switch (f) {
                case 'overdue': return r.bucket === 'overdue';
                case 'today': return r.bucket === 'today';
                case 'upcoming': return r.bucket === 'upcoming';
                case 'quotes': return due && r.type === 'quotation';
                case 'enquiries': return due && r.type === 'lead';
                case 'mine': return due && r.assigned_to === cfg.me;
                default: return due;
            }
        },
        get filters() {
            const live = this.items.filter((r) => !r.leaving);
            return [['all', 'All'], ['overdue', 'Overdue'], ['today', 'Today'], ['upcoming', 'Upcoming'], ['quotes', 'Quotes'], ['enquiries', 'Enquiries'], ['mine', 'Mine']]
                .map(([key, label]) => ({ key, label, count: live.filter((r) => this.matches(r, key)).length }));
        },
        get visible() {
            const q = this.search.trim().toLowerCase().replace(/\s+/g, '');
            return this.items.filter((r) => {
                if (r.leaving && r.id !== this._leavingId) return false;
                if (q) {
                    const hay = [r.name, r.plate, r.mobile, r.asked, r.number, r.car_model].join(' ').toLowerCase().replace(/\s+/g, '');
                    return hay.includes(q);
                }
                return this.matches(r, this.filter);
            });
        },
        setFilter(f) {
            this.filter = f;
            this.$nextTick(() => { if (!this.visible.find((r) => r.id === this.selId) && this.visible.length) this.select(this.visible[0].id); });
        },
        waHref() {
            const r = this.current;
            if (!r) return '#';
            return this.msg && this.msg !== r.message ? r.wa_url + '?text=' + encodeURIComponent(this.msg) : r.wa_url;
        },
        get current() {
            return this.items.find((r) => r.id === this.selId) || null;
        },
        get needsDate() {
            return this.outcome === 'coming_on' || this.outcome === 'call_later';
        },
        get quickDates() {
            return this.outcome === 'coming_on'
                ? [{ label: 'Today', value: plus(0) }, { label: 'Tomorrow', value: plus(1) }, { label: 'In 2 days', value: plus(2) }]
                : [{ label: 'Tomorrow', value: plus(1) }, { label: 'In 3 days', value: plus(3) }, { label: 'Next week', value: plus(7) }];
        },
        get saveLabel() {
            if (!this.outcome) return 'Pick what they said';
            const o = this.outcomeList.find((x) => x.key === this.outcome);
            return 'Save: ' + o.label.replace('…', '');
        },
        countOf(bucket) {
            return this.items.filter((r) => r.bucket === bucket && !r.leaving).length;
        },

        select(id) {
            if (this.selId !== id) this.reset();
            this.selId = id;
            this.msg = this.current ? this.current.message : '';
            // PC only: keep the selected row in view inside the queue (phones scroll the page themselves).
            if (window.matchMedia('(min-width: 1024px)').matches) {
                this.$nextTick(() => document.getElementById('fu-' + id)?.scrollIntoView({ block: 'nearest', inline: 'nearest' }));
            }
        },
        openSheet(id) {
            this.select(id);
            this.sheet = true;
        },
        reset() {
            this.outcome = null; this.date = ''; this.note = ''; this.error = '';
        },
        pick(key) {
            this.outcome = key;
            this.error = '';
            if (this.needsDate && !this.date) this.date = this.quickDates[1].value;
            if (!this.needsDate) this.date = '';
        },
        move(delta) {
            const list = this.visible;
            if (!list.length) return;
            const i = list.findIndex((r) => r.id === this.selId);
            const next = list[Math.max(0, Math.min(list.length - 1, (i < 0 ? 0 : i + delta)))];
            this.select(next.id);
        },

        key(e) {
            const tag = document.activeElement?.tagName;
            const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag);
            if (e.key === 'Escape') { if (this.sheet) { this.sheet = false; } else { this.reset(); } document.activeElement?.blur(); return; }
            if (typing) {
                if (e.key === 'Enter' && tag === 'INPUT' && this.outcome) { e.preventDefault(); this.save(); }
                return;
            }
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            const k = e.key.toLowerCase();
            if (e.key === 'ArrowDown' || k === 'j') { e.preventDefault(); this.move(1); }
            else if (e.key === 'ArrowUp' || k === 'k') { e.preventDefault(); this.move(-1); }
            else if (k === 'w' && this.current) { e.preventDefault(); this.current.contacted = true; window.open(this.waHref(), '_blank', 'noopener'); }
            else if (k === 'c' && this.current) { e.preventDefault(); this.current.contacted = true; window.location = this.current.call_url; }
            else if (k === 'l' && this.current) {
                e.preventDefault();
                if (window.matchMedia('(max-width: 1023px)').matches) { this.sheet = true; }
                else { this.$refs.logPanel?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); this.$refs.logPanel?.querySelector('button')?.focus(); }
            }
            else if (/^[1-9]$/.test(e.key) && this.current) {
                const o = this.outcomeList[parseInt(e.key, 10) - 1];
                if (o) { e.preventDefault(); this.pick(o.key); }
            }
            else if (e.key === 'Enter' && this.outcome && this.current) { e.preventDefault(); this.save(); }
        },

        save() {
            const row = this.current;
            if (!row || this.saving) return;
            if (!this.outcome) { this.error = 'Pick what the customer said.'; return; }
            if (this.needsDate && !this.date) { this.error = 'Pick a date.'; return; }
            this.saving = true;
            this.error = '';
            window.htmx.ajax('POST', row.log_url, {
                swap: 'none',
                values: { outcome: this.outcome, date: this.needsDate ? this.date : '', note: this.note },
                headers: { Accept: 'application/json' },
            }).finally(() => { this.saving = false; });
        },

        onLogged(d) {
            const idx = this.items.findIndex((r) => r.id === d.id);
            if (idx < 0) return;
            const row = this.items[idx];
            const list = this.visible;
            const pos = list.findIndex((r) => r.id === row.id);
            this._leavingId = row.id;
            row.leaving = true;
            this.sheet = false;
            this.reset();
            const after = this.visible.filter((r) => r.id !== row.id);
            if (after.length) this.select(after[Math.min(pos, after.length - 1)].id); else this.selId = null;
            setTimeout(() => { const i = this.items.indexOf(row); if (i >= 0) this.items.splice(i, 1); }, 320);
            this.showToast({ message: d.message, undo_url: d.undo_url, row, index: idx });
        },
        undo() {
            const t = this.toast;
            if (!t) return;
            clearTimeout(this.toastTimer);
            this.toast = null;
            window.htmx.ajax('POST', t.undo_url, { swap: 'none', headers: { Accept: 'application/json' } });
            this._restoring = t;
        },
        onRestored(d) {
            const t = this._restoring;
            if (!t || t.row.id !== d.id) return;
            t.row.leaving = false;
            if (t.row.bucket === 'upcoming') this.filter = 'upcoming';
            if (!this.items.includes(t.row)) this.items.splice(Math.min(t.index, this.items.length), 0, t.row);
            this.select(t.row.id);
            this._restoring = null;
        },
        onError(detail) {
            let msg = 'Could not save. Check the internet and try again.';
            try {
                const body = JSON.parse(detail.xhr.responseText);
                msg = Object.values(body.errors || {})[0]?.[0] || body.message || msg;
            } catch (e) { /* not JSON */ }
            this.error = msg;
            this.saving = false;
            this.showToast({ message: msg });
        },
        showToast(t) {
            clearTimeout(this.toastTimer);
            this.toast = t;
            this.toastTimer = setTimeout(() => { this.toast = null; }, 10000);
        },
    };
};
</script>
