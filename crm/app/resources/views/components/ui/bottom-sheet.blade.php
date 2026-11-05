{{--
    <x-ui.bottom-sheet name="add-car" title="Add car">
        …content…
        <x-slot:footer><button class="btn-accent w-full">Save car</button></x-slot:footer>
    </x-ui.bottom-sheet>
    Open it from anywhere:  <button type="button" x-data @click="$dispatch('sheet', 'add-car')">…</button>
    Close: Esc, the ×, the backdrop, or $dispatch('sheet-close').
    Phone: slides up from the bottom (16 px top radius, grab handle, sticky footer, safe-area aware).
    PC (≥1024 px): the same markup shows as a centred 480 px dialog. `open` starts it open
    (e.g. after a validation error inside it). Wrap the slot + footer in your own <form> by
    passing `form` attributes: <x-ui.bottom-sheet … :form="['action' => $url, 'method' => 'POST']">.
--}}
@props(['name', 'title', 'open' => false, 'footer' => null, 'form' => null])
<div x-data="{ open: @js((bool) $open) }"
     x-on:sheet.window="if ($event.detail === @js($name)) { open = true; $nextTick(() => $refs.panel?.querySelector('[autofocus], input:not([type=hidden]):not([type=radio]), textarea')?.focus()) }"
     x-on:sheet-close.window="open = false"
     x-on:keydown.escape.window="open = false"
     {{ $attributes }}>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center lg:items-center lg:p-6" role="dialog" aria-modal="true" aria-label="{{ $title }}">
            <div x-show="open" x-transition.opacity.duration.150ms class="absolute inset-0 bg-ink/40" @click="open = false"></div>
            <{{ $form ? 'form' : 'div' }} x-ref="panel" x-show="open"
                 @if ($form) action="{{ $form['action'] }}" method="{{ strtoupper($form['method'] ?? 'POST') === 'GET' ? 'GET' : 'POST' }}" @endif
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full lg:translate-y-2 lg:opacity-0" x-transition:enter-end="translate-y-0 lg:opacity-100"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0 lg:opacity-100" x-transition:leave-end="translate-y-full lg:translate-y-2 lg:opacity-0"
                 class="relative flex max-h-[88dvh] w-full flex-col rounded-t-2xl bg-surface shadow-sheet lg:max-w-[480px] lg:rounded-xl lg:shadow-pop">
                @if ($form && strtoupper($form['method'] ?? 'POST') !== 'GET')
                    @csrf
                    @if (! in_array(strtoupper($form['method'] ?? 'POST'), ['GET', 'POST'])) @method($form['method']) @endif
                @endif
                <div class="mx-auto mt-2 h-1 w-9 shrink-0 rounded-full bg-line-strong lg:hidden"></div>
                <div class="flex shrink-0 items-center justify-between gap-3 px-4 pt-3 pb-2 lg:px-5 lg:pt-4">
                    <h2 class="text-[22px] leading-7 font-bold tracking-[-0.01em] lg:text-lg">{{ $title }}</h2>
                    <button type="button" class="btn-icon -mr-2" @click="open = false" aria-label="Close"><x-icon name="x" class="size-6" /></button>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 pb-4 lg:px-5">{{ $slot }}</div>
                @if ($footer)
                    <div class="shrink-0 border-t border-line px-4 pt-3 lg:px-5 lg:pb-4" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">{{ $footer }}</div>
                @else
                    <div class="shrink-0" style="height: env(safe-area-inset-bottom)"></div>
                @endif
            </{{ $form ? 'form' : 'div' }}>
        </div>
    </template>
</div>
