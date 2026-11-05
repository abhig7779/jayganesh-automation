@props(['docked' => false])
{{-- Product detail.
     docked: a right-hand column on the PC (always visible, D6 design) and a bottom sheet on the phone.
     not docked: a bottom sheet on the phone and a right drawer on the PC.
     Rows open it with hx-get="{{ route('products.show', $p) }}" hx-target="#product-panel" and the
     'product-open' window event. --}}
<div x-data="{ open: false }"
     x-on:product-open.window="open = true"
     x-on:product-close.window="open = false"
     x-on:keydown.escape.window="if (open) { open = false }"
     @class(['contents' => $docked])>
    <div x-show="open" x-cloak x-transition.opacity @click="open = false"
         @class(['fixed inset-0 z-40 bg-ink/40', 'lg:hidden' => $docked, 'lg:bg-ink/20' => ! $docked])></div>
    <aside role="dialog" aria-label="Product detail"
           @if ($docked) :class="open ? 'max-lg:!flex' : ''" @else x-show="open" x-cloak @endif
           @class([
               'flex flex-col bg-white',
               'max-lg:hidden' => $docked,
               'max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-50 max-lg:max-h-[88dvh] max-lg:rounded-t-2xl max-lg:shadow-[0_-8px_24px_rgba(0,0,0,.08)]',
               'lg:sticky lg:top-4 lg:h-[calc(100dvh-13rem)] lg:min-h-[480px] lg:overflow-hidden lg:rounded-xl lg:border lg:border-zinc-200' => $docked,
               'lg:fixed lg:inset-y-0 lg:right-0 lg:z-50 lg:w-[480px] lg:border-l lg:border-zinc-200' => ! $docked,
           ])>
        <div class="mx-auto mt-2 h-1 w-9 shrink-0 rounded-full bg-zinc-300 lg:hidden"></div>
        <div id="product-panel" class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
            @if (trim($slot) !== '')
                {{ $slot }}
            @else
                <div class="space-y-3 p-5" aria-busy="true">
                    <div class="h-5 w-2/3 rounded bg-zinc-100"></div>
                    <div class="h-9 w-1/3 rounded bg-zinc-100"></div>
                    <div class="h-24 rounded bg-zinc-100"></div>
                </div>
            @endif
        </div>
    </aside>
</div>
