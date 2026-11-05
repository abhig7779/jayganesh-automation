// Job cards: board drag (SortableJS), instant item ticks and retry-safe photo uploads.
// Loaded by app.js only on pages that have a [data-jobcards] element.
import Sortable from 'sortablejs';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function postJson(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        body,
        credentials: 'same-origin',
    });
    let data = {};
    try { data = await res.json(); } catch { /* not JSON (e.g. a proxy error page) */ }
    if (!res.ok) {
        const first = data.errors ? Object.values(data.errors)[0]?.[0] : null;
        const err = new Error(first || data.message || 'Could not save. Check the internet and try again.');
        err.status = res.status;
        throw err;
    }
    return data;
}

function toast(message, link) {
    const el = document.querySelector('[data-toast]');
    if (!el) return;
    el.replaceChildren(document.createTextNode(message));
    if (link) {
        const a = document.createElement('a');
        a.href = '#';
        a.textContent = link.label;
        a.className = 'shrink-0 font-semibold text-[#FFD51C] underline-offset-2 hover:underline';
        a.addEventListener('click', (e) => { e.preventDefault(); link.onClick(); });
        el.append(a);
    }
    el.hidden = false;
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.hidden = true; }, link ? 10000 : 4000);
}

/** Opens the ready message via the POST route (it logs the message, then redirects to wa.me). */
function openWhatsApp(url) {
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = url;
    f.target = '_blank';
    const t = document.createElement('input');
    t.type = 'hidden'; t.name = '_token'; t.value = csrf();
    f.append(t);
    document.body.append(f);
    f.submit();
    f.remove();
}

function initBoard(board) {
    const transitions = JSON.parse(board.dataset.transitions || '{}');
    const canMove = board.dataset.canMove === '1';
    const base = board.dataset.statusUrl;
    let justDragged = false;

    const recount = () => board.querySelectorAll('[data-column]').forEach((col) => {
        const n = col.querySelectorAll('[data-card]').length;
        const badge = col.parentElement.querySelector('[data-count]');
        if (badge) badge.textContent = n;
    });

    board.addEventListener('click', (e) => {
        if (justDragged) { e.preventDefault(); e.stopPropagation(); }
    }, true);

    if (!canMove) return;

    board.querySelectorAll('[data-column]').forEach((col) => {
        Sortable.create(col, {
            group: {
                name: 'jobs',
                pull: () => col.dataset.column !== 'delivered',
                // Only offer columns the status machine allows, so a wrong drop never starts.
                put: (to, from, dragEl) => {
                    const fromStatus = dragEl.dataset.status;
                    return (transitions[fromStatus] || []).includes(to.el.dataset.column);
                },
            },
            filter: '[data-empty], form, button',
            preventOnFilter: false,
            draggable: '[data-card]',
            sort: false,
            animation: 150,
            forceFallback: true,
            fallbackOnBody: true,
            fallbackTolerance: 4,
            ghostClass: 'jc-ghost',
            chosenClass: 'jc-chosen',
            onStart: () => { justDragged = true; },
            onEnd: async (evt) => {
                setTimeout(() => { justDragged = false; }, 150);
                const card = evt.item;
                const to = evt.to.dataset.column;
                const from = evt.from.dataset.column;
                if (to === from) return;
                recount();
                const body = new FormData();
                body.append('status', to);
                card.style.opacity = '0.6';
                try {
                    const data = await postJson(`${base}/${card.dataset.id}/status`, body);
                    card.dataset.status = data.status;
                    card.style.opacity = '';
                    if (data.whatsapp_url) {
                        toast(data.message, { label: 'Send ready on WhatsApp', onClick: () => openWhatsApp(data.whatsapp_url) });
                    } else {
                        toast(data.message);
                    }
                    if (to === 'delivered' || from === 'ready' || to === 'ready') {
                        // Card layout differs per column (WhatsApp button, compact delivered row): refresh it.
                        setTimeout(() => window.location.reload(), data.whatsapp_url ? 6000 : 900);
                    }
                } catch (err) {
                    card.style.opacity = '';
                    evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || evt.from.querySelector('[data-empty]'));
                    recount();
                    toast(err.message);
                }
            },
        });
    });
}

function initTicks(root) {
    root.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-tick]');
        if (!form) return;
        e.preventDefault();
        const box = form.querySelector('[data-box]');
        const nowDone = form.dataset.done !== '1';
        const body = new FormData(form);
        body.set('done', nowDone ? '1' : '0');
        form.dataset.done = nowDone ? '1' : '0';
        form.setAttribute('aria-pressed', nowDone ? 'true' : 'false');
        try {
            const data = await postJson(form.action, body);
            const progress = form.closest('[data-job]')?.querySelector('[data-progress]');
            if (progress) progress.textContent = data.progress;
        } catch (err) {
            form.dataset.done = nowDone ? '0' : '1';
            form.setAttribute('aria-pressed', nowDone ? 'false' : 'true');
            alert(err.message);
        }
        box?.blur();
    });
}

const uuid = () => (crypto.randomUUID ? crypto.randomUUID()
    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    }));

/**
 * Photos: one request per file, each with its own UUID, retried up to 4 times with back-off.
 * The server ignores a UUID it already has, so a retry after a dropped reply never duplicates.
 */
function initPhotos(root) {
    root.querySelectorAll('form[data-photo-upload]').forEach((form) => {
        const input = form.querySelector('input[type=file]');
        const status = form.querySelector('[data-upload-status]');
        const submit = form.querySelector('[data-upload-submit]');
        if (submit) submit.hidden = true;
        if (!input) return;

        input.addEventListener('change', async () => {
            const files = [...input.files];
            if (!files.length) return;
            const stage = form.querySelector('[name=stage]:checked')?.value || form.querySelector('[name=stage]')?.value || 'before';
            let done = 0;
            let failed = 0;
            const say = (t) => { if (status) { status.hidden = false; status.textContent = t; } };

            for (const [i, file] of files.entries()) {
                const id = uuid();
                let ok = false;
                for (let attempt = 0; attempt < 5 && !ok; attempt++) {
                    say(`Uploading ${i + 1} of ${files.length}${attempt ? ` (try ${attempt + 1})` : ''}…`);
                    if (!navigator.onLine) {
                        say('No internet. Waiting to upload…');
                        await new Promise((r) => window.addEventListener('online', r, { once: true }));
                    }
                    const body = new FormData();
                    body.append('_token', csrf());
                    body.append('stage', stage);
                    body.append('photos[]', file);
                    body.append('client_ids[]', id);
                    try {
                        await postJson(form.action, body);
                        ok = true;
                    } catch (err) {
                        if (err.status && err.status < 500 && err.status !== 429 && err.status !== 419) {
                            say(err.message);
                            break; // a validation error won't fix itself
                        }
                        await new Promise((r) => setTimeout(r, 1000 * 2 ** attempt));
                    }
                }
                ok ? done++ : failed++;
            }
            input.value = '';
            if (failed) {
                say(`${done} added, ${failed} not uploaded. Tap Add photo to try those again.`);
                if (done) setTimeout(() => window.location.reload(), 2500);
            } else {
                say(`${done} photo${done === 1 ? '' : 's'} added.`);
                window.location.reload();
            }
        });
    });
}

document.querySelectorAll('[data-jobcards]').forEach((el) => {
    if (el.dataset.jobcards === 'board') initBoard(el);
    initTicks(el);
    initPhotos(el);
});
