{{--
    New bill. One page, two layouts sharing one Alpine state (resources/js/modules/bills.js):
    - counter PC (≥1024 px): the one-page keyboard-first POS (design D2);
    - phone: 3 full-screen steps, Customer → Items → Payment (designs M3–M5).
    It uses the bare base layout: the POS needs the whole screen (slim icon rail instead of the
    240 px sidebar) and the phone flow is full-screen without the tab bar (DESIGN.md §6).
--}}
@php
    use Illuminate\Support\Facades\Route;
    $user = auth()->user();
    $printLabel = \App\Models\Setting::get('billing.print_format', 'a4') === 'thermal' ? 'Thermal 80 mm' : 'A4 PDF';
    $rail = array_filter([
        ['New bill (F2)', route('bills.create'), 'plus', true],
        $user->can('invoices.view') ? ['Bills', route('bills.index'), 'receipt', false] : null,
        $user->can('payments.collect') ? ['Collect payment', route('payments.collect'), 'wallet', false] : null,
        Route::has('customers.index') && $user->can('customers.view') ? ['Customers', route('customers.index'), 'users', false] : null,
        ['Home', route('dashboard'), 'home', false],
    ]);
@endphp
<x-layouts.base title="New bill">
<div x-data="billForm(@js($config))" class="bg-stone-50 text-ink">

{{-- ============================== COUNTER PC: one-page POS ============================== --}}
<div class="hidden h-dvh lg:flex">
    <aside class="flex w-16 shrink-0 flex-col items-center bg-ink py-3">
        <a href="{{ route('dashboard') }}" class="mb-4 grid size-10 place-items-center rounded-lg bg-brand-400 text-sm font-black text-ink" title="Jay Ganesh CRM home">JG</a>
        <nav class="flex flex-1 flex-col gap-1">
            @foreach ($rail as [$label, $href, $icon, $active])
                <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" @class([
                    'relative grid size-11 place-items-center rounded-lg',
                    'bg-[#1C1C1C] text-white before:absolute before:-left-2.5 before:h-6 before:w-[3px] before:rounded-r before:bg-brand-400' => $active,
                    'text-zinc-400 hover:bg-white/10 hover:text-white' => ! $active,
                ])><x-bills.icon :name="$icon" class="size-5" /></a>
            @endforeach
        </nav>
        <span class="grid size-9 place-items-center rounded-full bg-zinc-700 text-xs font-bold text-white" title="{{ $user->name }} · {{ $user->roleLabel() }}">{{ collect(preg_split('/\s+/', preg_replace('/[^\p{L}\s]/u', '', $user->name)))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('') }}</span>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- Header --}}
        <header class="flex h-14 shrink-0 items-center gap-4 border-b border-zinc-200 bg-white px-5">
            <h1 class="text-lg font-bold">New bill</h1>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-0.5 text-xs font-medium text-zinc-700">
                <span class="size-1.5 rounded-full" :class="savedAt ? 'bg-green-600' : 'bg-zinc-400'"></span>
                <span x-text="savedAt ? 'Draft saved ' + savedAt : (hasContent ? 'Draft kept on this PC' : 'Bill no. ' + cfg.nextNumber)"></span>
            </span>
            <template x-if="restoredNote">
                <span class="inline-flex items-center gap-2 text-xs text-zinc-700"><span x-text="restoredNote"></span><button type="button" class="font-semibold underline" @click="discardDraft()">Discard</button></span>
            </template>
            <span class="flex-1"></span>
            @if (Route::has('search'))
                <form action="{{ route('search') }}" method="GET" class="hidden w-[340px] xl:block">
                    <label class="flex h-9 items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 text-sm text-zinc-500 focus-within:border-ink">
                        <x-bills.icon name="search" class="size-4" />
                        <input name="q" placeholder="Search customer, bill, car…" class="min-w-0 flex-1 border-0 bg-transparent text-ink focus:outline-none" aria-label="Global search">
                    </label>
                </form>
            @endif
            <a href="{{ route('dashboard') }}" class="inline-flex h-9 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold hover:border-ink"><x-bills.kbd>Esc</x-bills.kbd> Back</a>
        </header>

        <div x-show="!online || pendingRetry" class="flex shrink-0 items-center gap-2 border-b border-amber-200 bg-amber-50 px-5 py-2 text-sm font-medium text-amber-800" role="status">
            <x-bills.icon name="wifi-off" class="size-4" />
            <span x-text="online ? 'Could not reach the server. Your bill is kept here and will save by itself.' : 'No internet. Your bill is saved here. We’ll save it when you’re back online.'"></span>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-[minmax(0,1fr)_minmax(460px,41%)]">
            {{-- Left: products --}}
            <section class="min-h-0 overflow-y-auto px-5 py-4" aria-label="Products">
                @include('bills.partials.search', ['pc' => true])
            </section>

            {{-- Right: the live bill --}}
            <section class="flex min-h-0 flex-col border-l border-zinc-200 bg-white" aria-label="This bill">
                {{-- Only the item list scrolls, so totals, payment and Save stay on screen at 1366x768. --}}
                <div class="flex min-h-0 flex-1 flex-col">
                    <div class="max-h-[45%] shrink-0 overflow-y-auto border-b border-zinc-200 px-4 py-2.5">
                        @include('bills.partials.customer', ['pc' => true])
                    </div>

                    {{-- Items --}}
                    <div class="grid grid-cols-[minmax(0,1fr)_104px_80px_88px] gap-2 border-b border-zinc-200 bg-zinc-100 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">
                        <span>Item</span><span class="text-center">Qty</span><span class="text-right">Price</span><span class="text-right">Total</span>
                    </div>
                    <div class="min-h-[84px] flex-1 overflow-y-auto">
                    <div x-show="!items.length" class="px-4 py-5 text-center text-sm text-zinc-600">
                        <x-bills.icon name="cart" class="mx-auto mb-1 size-7 text-zinc-500" />
                        No items yet. Search on the left, click a tile or scan a barcode.
                    </div>
                    <ul>
                        <template x-for="(l, i) in items" :key="l.key">
                            <li :data-line="l.key" @click="sel = i"
                                :class="sel === i ? 'bg-zinc-50 shadow-[inset_3px_0_0_#111]' : ''"
                                class="group grid grid-cols-[minmax(0,1fr)_104px_80px_88px] items-center gap-2 border-b border-zinc-100 px-4 py-2">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate text-sm font-semibold" x-text="l.name"></span>
                                        <span x-show="outOfStock(l)" class="shrink-0 rounded-full border border-amber-200 bg-amber-50 px-1.5 text-[10px] font-semibold text-amber-700">Not in stock</span>
                                        <span x-show="l.type === 'custom'" class="shrink-0 rounded-full border border-zinc-200 bg-zinc-100 px-1.5 text-[10px] font-semibold text-zinc-600">Custom</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 truncate text-xs text-zinc-600">
                                        <span x-text="[l.brand, l.sku].filter(Boolean).join(' · ') || (l.type === 'service' ? 'Service' : '')"></span>
                                        <span x-show="sel === i" class="hidden xl:inline"><x-bills.kbd>+ / − qty</x-bills.kbd></span>
                                    </div>
                                </div>
                                <div class="flex h-8 items-center justify-center rounded-md border border-zinc-300 bg-white">
                                    <button type="button" class="grid h-full w-8 place-items-center text-zinc-700 hover:bg-zinc-100" @click.stop="setQty(i, l.qty - 1)" aria-label="One less"><x-bills.icon name="minus" class="size-4" /></button>
                                    <input class="h-full w-9 border-0 bg-transparent p-0 text-center text-sm font-semibold tabular-nums focus:outline-none" :value="l.qty" @change="setQty(i, $event.target.value)" inputmode="numeric" aria-label="Quantity">
                                    <button type="button" class="grid h-full w-8 place-items-center text-zinc-700 hover:bg-zinc-100" @click.stop="setQty(i, l.qty + 1)" aria-label="One more"><x-bills.icon name="plus" class="size-4" /></button>
                                </div>
                                <div class="text-right tabular-nums">
                                    <div class="text-[11px] text-zinc-500 line-through" x-show="showMrp(l)" x-text="money(l.mrp_paise)"></div>
                                    <template x-if="cfg.can.editPrice">
                                        <input class="h-7 w-full rounded border border-transparent bg-transparent text-right text-sm text-zinc-700 hover:border-zinc-300 focus:border-ink focus:outline-none" :value="(l.unit_price_paise/100)" @change="setPrice(i, $event.target.value)" inputmode="decimal" aria-label="Price">
                                    </template>
                                    <template x-if="!cfg.can.editPrice"><span class="text-sm text-zinc-700" x-text="money(l.unit_price_paise)"></span></template>
                                </div>
                                <div class="flex items-center justify-end gap-1">
                                    <span class="text-sm font-bold tabular-nums" x-text="money(lineTotal(l))"></span>
                                    <button type="button" class="grid size-6 place-items-center rounded text-zinc-400 opacity-0 hover:bg-red-50 hover:text-red-600 group-hover:opacity-100 focus:opacity-100" @click.stop="removeLine(i)" aria-label="Remove item"><x-bills.icon name="x" class="size-4" /></button>
                                </div>
                            </li>
                        </template>
                    </ul>
                    </div>

                    <div class="shrink-0">
                    {{-- Discount / clear --}}
                    <div class="flex items-center justify-between border-t border-zinc-100 px-4 py-1.5 text-[13px]">
                        <button type="button" class="flex items-center gap-1.5 font-semibold text-ink" @click="disc.open = !disc.open; $nextTick(() => $refs.discValuePc?.focus())">
                            <x-bills.icon name="plus" class="size-4" />
                            <span x-text="billDiscountPaise > 0 ? 'Discount ' + money(billDiscountPaise) + (disc.reason ? ' · ' + disc.reason : '') : 'Add discount'"></span>
                            <x-bills.kbd>D</x-bills.kbd>
                        </button>
                        <button type="button" class="text-zinc-600 hover:text-red-600" @click="clearAll()" x-show="items.length">Clear all</button>
                    </div>
                    <div x-show="disc.open" class="mx-4 mb-2 rounded-lg border border-zinc-200 bg-zinc-50 p-2.5">
                        @include('bills.partials.discount', ['sfx' => 'Pc'])
                    </div>

                    <div class="border-t border-zinc-200 px-4 py-2">
                        @include('bills.partials.totals')
                    </div>

                    <div class="px-4 pb-2">
                        @include('bills.partials.payment', ['pc' => true])
                    </div>
                    </div>
                </div>

                {{-- Pinned: WhatsApp + Save --}}
                <div class="shrink-0 border-t border-zinc-200 px-4 pb-2.5 pt-2">
                    <label class="flex items-center gap-2 text-[13px] text-zinc-700" :class="custState === 'walkin' || custState === 'idle' ? 'opacity-50' : ''">
                        <input type="checkbox" x-model="sendWa" class="size-4 accent-ink" :disabled="custState === 'walkin'">
                        <x-bills.icon name="chat" class="size-4" />
                        Send bill on WhatsApp <span x-show="mobileDigits(mobile).length === 10" class="text-zinc-500" x-text="'to +91 ' + prettyMobile(mobileDigits(mobile))"></span>
                    </label>
                    <p class="mt-1 text-[13px] font-medium text-red-600" x-show="error" x-text="error" role="alert"></p>
                    <p class="mt-1 text-[13px] text-zinc-600" x-show="!error && blocker && items.length" x-text="blocker"></p>
                    <button type="button" @click="save()" :disabled="saving"
                            :class="blocker ? 'opacity-60' : ''"
                            class="mt-2 flex h-12 w-full items-center gap-3 rounded-lg bg-brand-400 px-4 text-[17px] font-semibold text-ink hover:bg-[#F2C800] disabled:cursor-wait">
                        <span x-show="saving" class="size-4 animate-spin rounded-full border-2 border-ink/30 border-t-ink"></span>
                        <x-bills.icon name="send" class="size-5" x-show="!saving" />
                        <span x-text="saveLabel"></span>
                        <span class="flex-1"></span>
                        <x-bills.kbd dark class="h-6 px-2 text-[11px]">Ctrl+Enter</x-bills.kbd>
                    </button>
                </div>
            </section>
        </div>

        <footer class="flex h-9 shrink-0 items-center gap-4 border-t border-zinc-200 bg-white px-5 text-xs text-zinc-600">
            @foreach ([['F2', 'New'], ['/', 'Search'], ['F3', 'Customer'], ['+ / −', 'Qty'], ['Del', 'Remove'], ['D', 'Discount'], ['1–5', 'Pay mode'], ['Ctrl+Enter', 'Save'], ['Esc', 'Back']] as [$k, $l])
                <span class="flex items-center gap-1.5 whitespace-nowrap"><x-bills.kbd>{{ $k }}</x-bills.kbd>{{ $l }}</span>
            @endforeach
            <span class="flex-1"></span>
            <span class="flex items-center gap-1.5 whitespace-nowrap"><x-bills.icon name="printer" class="size-4" />Prints {{ $printLabel }}</span>
        </footer>
    </div>
</div>

{{-- ============================== PHONE: 3 steps ============================== --}}
<div class="min-h-dvh pb-32 lg:hidden">
    <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white" style="padding-top: env(safe-area-inset-top)">
        <div class="flex h-14 items-center gap-2 px-2">
            <button type="button" @click="back()" class="grid size-11 place-items-center rounded-lg text-ink" aria-label="Back"><x-bills.icon name="chevron-left" class="size-6" /></button>
            <h1 class="flex-1 text-[17px] font-semibold">New bill</h1>
            <span class="pr-3 text-[13px] font-medium text-zinc-600">Step <span x-text="step"></span> of 3</span>
        </div>
        <div class="grid grid-cols-3 gap-1 px-4 pb-2">
            <template x-for="n in 3" :key="n"><span class="h-1 rounded-full" :class="n <= step ? 'bg-ink' : 'bg-zinc-200'"></span></template>
        </div>
    </header>
    <div x-show="!online || pendingRetry" class="flex items-start gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-[13px] font-medium text-amber-800" role="status">
        <x-bills.icon name="wifi-off" class="mt-0.5 size-4 shrink-0" />
        <span>No internet. Your bill is saved here. We’ll save it when you’re back online.</span>
    </div>
    <template x-if="restoredNote">
        <div class="flex items-center justify-between border-b border-zinc-200 bg-white px-4 py-2 text-[13px] text-zinc-700"><span x-text="restoredNote"></span><button type="button" class="font-semibold underline" @click="discardDraft()">Discard</button></div>
    </template>

    {{-- Step 1: customer --}}
    <section x-show="step === 1" class="px-4 pt-5">
        @include('bills.partials.customer', ['pc' => false])
        <template x-if="custState === 'found' && carSuggestions.length">
            <p class="mt-3 text-[13px] text-zinc-600">Recent for this car: <span x-text="carSuggestions.slice(0,3).map(p => p.name).join(', ')"></span></p>
        </template>
    </section>

    {{-- Step 2: items --}}
    <section x-show="step === 2" class="px-4 pt-3">
        <div class="mb-3 flex items-center justify-between rounded-lg border border-zinc-200 bg-white px-3 py-2 text-[13px]">
            <span class="truncate"><span class="font-semibold" x-text="customerLabel"></span><span class="text-zinc-600" x-show="selectedCarLabel" x-text="' · ' + selectedCarLabel"></span></span>
            <button type="button" class="ml-2 shrink-0 font-semibold underline" @click="step = 1">Change</button>
        </div>
        @include('bills.partials.search', ['pc' => false])

        <div class="mt-5" x-show="items.length">
            <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">In this bill · <span x-text="items.length"></span></h3>
            <ul class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                <template x-for="(l, i) in items" :key="l.key">
                    <li class="relative border-b border-zinc-100 last:border-0" x-data="{ dx: 0, x0: null }"
                        @touchstart.passive="x0 = $event.touches[0].clientX" @touchmove.passive="if (x0 !== null) dx = Math.min(0, Math.max(-96, $event.touches[0].clientX - x0))"
                        @touchend="if (dx < -64) removeLine(i); dx = 0; x0 = null">
                        <div class="absolute inset-y-0 right-0 flex w-24 items-center justify-center bg-ink text-sm font-semibold text-white">Remove</div>
                        <div class="relative flex min-h-[76px] items-center gap-3 bg-white px-3 py-2 transition-transform" :style="'transform: translateX(' + dx + 'px)'">
                            <div class="min-w-0 flex-1">
                                <div class="text-[15px] font-semibold leading-snug" x-text="l.name"></div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[13px] text-zinc-600">
                                    <span x-show="showMrp(l)" class="text-zinc-500 line-through" x-text="money(l.mrp_paise)"></span>
                                    <span x-text="money(l.unit_price_paise) + ' each'"></span>
                                    <span x-show="outOfStock(l)" class="rounded-full border border-amber-200 bg-amber-50 px-1.5 text-[11px] font-semibold text-amber-700">Not in stock</span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <div class="flex h-10 w-[104px] items-center rounded-lg border border-zinc-300">
                                    <button type="button" class="grid h-full w-9 place-items-center" @click="setQty(i, l.qty - 1)" aria-label="One less"><x-bills.icon name="minus" class="size-4" /></button>
                                    <span class="flex-1 text-center font-semibold tabular-nums" x-text="l.qty"></span>
                                    <button type="button" class="grid h-full w-9 place-items-center" @click="setQty(i, l.qty + 1)" aria-label="One more"><x-bills.icon name="plus" class="size-4" /></button>
                                </div>
                                <span class="text-[15px] font-bold tabular-nums" x-text="money(lineTotal(l))"></span>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
            <p class="mt-1.5 text-xs text-zinc-500">Swipe a row left to remove it.</p>
            <button type="button" class="mt-3 flex h-11 items-center gap-1.5 text-[15px] font-semibold" @click="disc.open = !disc.open">
                <x-bills.icon name="plus" class="size-4" /><span x-text="billDiscountPaise > 0 ? 'Discount ' + money(billDiscountPaise) : 'Discount'"></span>
            </button>
            <div x-show="disc.open" class="rounded-xl border border-zinc-200 bg-white p-3">@include('bills.partials.discount', ['sfx' => 'Phone'])</div>
        </div>
    </section>

    {{-- Step 3: payment --}}
    <section x-show="step === 3" class="px-4 pt-5">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-600">To pay</p>
        <p class="mt-1 inline-block border-b-4 border-brand-400 text-[40px] font-bold leading-tight tracking-tight tabular-nums" x-text="money(t.total)"></p>
        <p class="mt-1 text-[13px] text-zinc-600"><span x-text="pieces"></span> pcs · <span x-show="cfg.gst.registered">GST <span x-text="money(t.tax)"></span> included · </span><span x-text="customerLabel"></span></p>
        <p class="mt-1 text-[13px] font-semibold text-green-700" x-show="t.savings > 0">You save <span x-text="money(t.savings)"></span></p>
        <div class="mt-5">@include('bills.partials.payment', ['pc' => false])</div>
        <label class="mt-4 flex min-h-14 items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4" :class="custState === 'walkin' ? 'opacity-50' : ''">
            <span><span class="block text-[15px] font-semibold">Send bill on WhatsApp</span><span class="text-[13px] text-zinc-600" x-text="mobileDigits(mobile).length === 10 ? '+91 ' + prettyMobile(mobileDigits(mobile)) : 'Needs a mobile number'"></span></span>
            <input type="checkbox" x-model="sendWa" :disabled="custState === 'walkin'" class="peer sr-only">
            <span class="relative h-7 w-12 shrink-0 rounded-full bg-zinc-300 transition peer-checked:bg-ink after:absolute after:left-1 after:top-1 after:size-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span>
        </label>
        <details class="mt-4 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-[13px]">
            <summary class="cursor-pointer font-semibold">Bill details</summary>
            <div class="mt-3">@include('bills.partials.totals')</div>
        </details>
        <p class="mt-4 text-center text-xs text-zinc-500">Bill no. will be <span class="font-semibold text-zinc-700" x-text="cfg.nextNumber"></span></p>
    </section>

    {{-- Sticky bottom bar --}}
    <div class="fixed inset-x-0 bottom-0 z-20 border-t border-zinc-200 bg-white px-4 pt-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
        <p class="mb-2 text-[13px] font-medium text-red-600" x-show="error" x-text="error" role="alert"></p>
        <template x-if="step === 1">
            <button type="button" @click="next()" class="flex h-14 w-full items-center justify-center gap-2 rounded-lg bg-brand-400 text-[17px] font-semibold text-ink active:bg-[#F2C800]">Next: Items <x-bills.icon name="arrow-right" class="size-5" /></button>
        </template>
        <template x-if="step === 2">
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] text-zinc-600"><span x-text="items.length"></span> item<span x-show="items.length !== 1">s</span> · Total incl. GST</div>
                    <div class="text-2xl font-bold tabular-nums" x-text="money(t.total)"></div>
                </div>
                <button type="button" @click="next()" class="flex h-14 items-center gap-2 rounded-lg bg-brand-400 px-5 text-[17px] font-semibold text-ink active:bg-[#F2C800]">Next: Payment</button>
            </div>
        </template>
        <template x-if="step === 3">
            <div>
                <p class="mb-2 text-[13px] text-zinc-600" x-show="!error && blocker" x-text="blocker"></p>
                <button type="button" @click="save()" :disabled="saving" :class="blocker ? 'opacity-60' : ''" class="flex h-14 w-full items-center justify-center gap-2 rounded-lg bg-brand-400 text-[17px] font-semibold text-ink active:bg-[#F2C800]">
                    <span x-show="saving" class="size-4 animate-spin rounded-full border-2 border-ink/30 border-t-ink"></span>
                    <span x-text="saveLabel"></span>
                </button>
            </div>
        </template>
    </div>
</div>

{{-- ============================== Shared overlays ============================== --}}
{{-- Undo / messages --}}
<div class="pointer-events-none fixed inset-x-0 bottom-28 z-40 flex flex-col items-center gap-2 px-4 lg:bottom-14">
    <template x-if="undo">
        <div class="pointer-events-auto flex w-full max-w-md items-center justify-between gap-4 rounded-lg bg-ink px-4 py-3 text-sm font-medium text-white">
            <span class="truncate">Removed <span x-text="undo.line.name"></span></span>
            <button type="button" class="font-semibold text-brand-400" @click="undoRemove()">Undo</button>
        </div>
    </template>
    <template x-if="toast">
        <div class="pointer-events-auto w-full max-w-md rounded-lg px-4 py-3 text-sm font-medium" :class="toast.tone === 'bad' ? 'border border-red-200 bg-red-50 text-red-700' : 'bg-ink text-white'" x-text="toast.msg" role="status"></div>
    </template>
</div>

{{-- Walk-in confirm (sheet on phone, dialog on PC) --}}
<div x-show="walkInSheet" x-cloak class="fixed inset-0 z-50 flex items-end justify-center lg:items-center" @keydown.escape.window="walkInSheet = false">
    <div class="absolute inset-0 bg-ink/40" @click="walkInSheet = false"></div>
    <div class="relative w-full max-w-md rounded-t-2xl bg-white p-5 shadow-[0_-8px_24px_rgba(0,0,0,.08)] lg:rounded-xl">
        <div class="mx-auto mb-4 h-1 w-9 rounded-full bg-zinc-300 lg:hidden"></div>
        <h2 class="text-[22px] font-bold">Walk-in without number?</h2>
        <p class="mt-2 text-[15px] text-zinc-700">No WhatsApp bill without a number, and no pay-later. The owner wants numbers saved when possible.</p>
        <div class="mt-5 grid gap-2">
            <button type="button" class="h-12 rounded-lg border border-zinc-300 bg-white text-[15px] font-semibold" @click="walkInSheet = false; $nextTick(() => (window.matchMedia('(min-width:1024px)').matches ? $refs.mobilePc : $refs.mobilePhone)?.focus())">Enter the number</button>
            <button type="button" class="h-12 rounded-lg bg-ink text-[15px] font-semibold text-white" @click="confirmWalkIn()">Continue as walk-in</button>
        </div>
    </div>
</div>

{{-- Big UPI QR --}}
<div x-show="qrOpen" x-cloak class="fixed inset-0 z-50 flex items-end justify-center lg:items-center">
    <div class="absolute inset-0 bg-ink/40" @click="qrOpen = false"></div>
    <div class="relative w-full max-w-sm rounded-t-2xl bg-white p-6 text-center lg:rounded-xl">
        <h2 class="text-[22px] font-bold">Scan to pay <span x-text="money(qrAmount)"></span></h2>
        <p class="mt-1 text-sm text-zinc-600" x-text="cfg.upi.id"></p>
        <template x-if="qrOpen"><img :src="qrUrl(320)" alt="UPI QR code" class="mx-auto mt-4 size-64"></template>
        <p class="mt-3 text-sm text-zinc-600">Any UPI app: GPay, PhonePe, Paytm, BHIM.</p>
        <button type="button" class="mt-5 h-12 w-full rounded-lg border border-zinc-300 text-[15px] font-semibold" @click="qrOpen = false">Done</button>
    </div>
</div>
</div>
</x-layouts.base>
