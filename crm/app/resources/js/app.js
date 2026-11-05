import htmx from 'htmx.org';
import Alpine from 'alpinejs';

window.htmx = htmx;
window.Alpine = Alpine;

// Send Laravel's CSRF token with every htmx request.
document.addEventListener('htmx:configRequest', (e) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token) e.detail.headers['X-CSRF-TOKEN'] = token;
});

const isVisible = (el) => !!el && el.offsetParent !== null;
const typingIn = (el) => el && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable);

/*
 * Global search box (components/ui/search-input, global mode). htmx fills the results panel;
 * this handles the keyboard: ↑ ↓ to move, Enter to open the highlighted or first result,
 * Esc to close. With no results, Enter submits to the full search page.
 */
Alpine.data('searchBox', () => ({
    open: false,
    active: -1,
    items() {
        return Array.from(this.$refs.results.querySelectorAll('[data-search-result]'));
    },
    move(step) {
        const items = this.items();
        if (!items.length) return;
        this.open = true;
        this.active = (this.active + step + items.length) % items.length;
        items.forEach((el, i) => el.setAttribute('aria-selected', i === this.active ? 'true' : 'false'));
        items[this.active].scrollIntoView({ block: 'nearest' });
    },
    go() {
        const items = this.items();
        const target = items[this.active] ?? items[0];
        if (target?.href && this.open) {
            window.location = target.href;
        } else {
            this.$root.submit();
        }
    },
    close() {
        this.open = false;
        this.active = -1;
    },
}));

/*
 * Counter shortcuts (UX-FLOWS §0.11): F2 = new bill, "/" = focus the search box,
 * Esc inside a field = leave the field.
 */
document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !typingIn(document.activeElement)) {
        const search = Array.from(document.querySelectorAll('[data-search]')).find(isVisible);
        if (search) {
            e.preventDefault();
            search.focus();
            search.select?.();
        }
    }
    // F-keys: F2 new bill (from any page); on Home, the action tiles carry data-fkey (F4, F6–F9).
    if (/^F\d+$/.test(e.key)) {
        const selector = e.key === 'F2' ? '[data-shortcut="new-bill"], [data-fkey="F2"]' : `[data-fkey="${e.key}"]`;
        const link = Array.from(document.querySelectorAll(selector)).find((a) => a.href);
        if (link) {
            e.preventDefault();
            window.location = link.href;
        }
    }
});

// Table rows with data-href open on click (PC tables), without nesting links in every cell.
document.addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-href]');
    if (row && !e.target.closest('a, button, input, label')) {
        window.location = row.dataset.href;
    }
});

// Module scripts (resources/js/modules/*.js) register their Alpine components on 'alpine:init',
// so a module adds JS without editing this file.
import.meta.glob('./modules/*.js', { eager: true });

Alpine.start();

// Job cards module: board drag, ticks and photo uploads, loaded only where needed.
if (document.querySelector('[data-jobcards]')) import('./jobcards.js');
