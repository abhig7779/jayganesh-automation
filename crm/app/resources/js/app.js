import htmx from 'htmx.org';
import Alpine from 'alpinejs';

window.htmx = htmx;
window.Alpine = Alpine;

// Send Laravel's CSRF token with every htmx request.
document.addEventListener('htmx:configRequest', (e) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token) e.detail.headers['X-CSRF-TOKEN'] = token;
});

// Counter shortcuts. F2 = new bill (once billing ships), "/" = focus the page search box.
document.addEventListener('keydown', (e) => {
    const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
    if (e.key === '/' && !typing) {
        const search = document.querySelector('[data-search]');
        if (search) { e.preventDefault(); search.focus(); }
    }
    if (e.key === 'F2') {
        const link = document.querySelector('[data-shortcut="new-bill"]');
        if (link?.href) { e.preventDefault(); window.location = link.href; }
    }
});

Alpine.start();
