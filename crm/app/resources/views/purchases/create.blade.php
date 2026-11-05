@php
    $init = [
        'productsUrl' => route('purchases.product-index'),
        'items' => $oldItems,
        'gstRates' => $gstRates,
        'newSupplier' => (bool) old('new_supplier'),
        'paid' => (string) old('paid', ''),
    ];
@endphp
<x-layouts.app title="New purchase" wide>
    <div class="mx-auto max-w-[1280px]">
    <x-ui.page-header title="New purchase" subtitle="Enter the supplier bill. Saving adds the stock and updates the cost price." :back="route('purchases.index')" back-label="Purchases" />

    @include('purchases._search-js')

    <form method="POST" action="{{ route('purchases.store') }}" enctype="multipart/form-data"
          x-data="purchaseForm(@js($init))" x-init="boot()" @submit="submitting = true"
          @keydown.ctrl.enter.prevent="$el.requestSubmit()" @keydown.meta.enter.prevent="$el.requestSubmit()"
          class="grid grid-cols-1 gap-5 *:min-w-0 lg:grid-cols-12">
        @csrf

        <div class="space-y-5 lg:col-span-8">
            {{-- 1. Supplier and bill --}}
            <section class="card">
                <h2 class="section-label mb-3">Supplier bill</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <div class="mb-1.5 flex items-center justify-between">
                            <label class="label mb-0" :for="newSupplier ? 'new_supplier' : 'supplier_id'">Supplier</label>
                            <button type="button" class="text-[13px] font-semibold text-ink underline-offset-4 hover:underline" @click="newSupplier = !newSupplier"
                                    x-text="newSupplier ? 'Pick from the list' : '+ New supplier'"></button>
                        </div>
                        <template x-if="!newSupplier">
                            <select id="supplier_id" name="supplier_id" class="input" @if (! old('new_supplier')) autofocus @endif>
                                <option value="">Choose the supplier…</option>
                                @foreach ($suppliers as $s)
                                    <option value="{{ $s->id }}" @selected((int) old('supplier_id', $selectedSupplier) === $s->id)>{{ $s->name }}{{ $s->mobile ? ' · '.\App\Support\Phone::pretty($s->mobile) : '' }}</option>
                                @endforeach
                            </select>
                        </template>
                        <template x-if="newSupplier">
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1.4fr_1fr]">
                                <input id="new_supplier" name="new_supplier" value="{{ old('new_supplier') }}" class="input" placeholder="Supplier name, e.g. Pune Auto Distributors" maxlength="120">
                                <input name="new_supplier_mobile" value="{{ old('new_supplier_mobile') }}" class="input" inputmode="tel" placeholder="Mobile (optional)" aria-label="Supplier mobile">
                            </div>
                        </template>
                        @error('supplier_id') <p class="error">{{ $message }}</p> @enderror
                        @error('new_supplier') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="bill_no">Supplier bill no.</label>
                        <input id="bill_no" name="bill_no" value="{{ old('bill_no') }}" class="input" placeholder="As printed on their bill" maxlength="60">
                        @error('bill_no') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label" for="bill_date">Bill date</label>
                        <input id="bill_date" type="date" name="bill_date" value="{{ old('bill_date', $today) }}" max="{{ $today }}" class="input" required>
                        @error('bill_date') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="photo">Photo of the supplier bill <span class="font-normal text-muted">(optional, only staff with purchases access can open it)</span></label>
                        <input id="photo" type="file" name="photo" accept="image/*,application/pdf" capture="environment"
                               class="block w-full text-sm text-ink-2 file:mr-3 file:min-h-10 file:rounded-lg file:border file:border-line-strong file:bg-surface file:px-4 file:text-sm file:font-semibold file:text-ink hover:file:bg-sunken">
                        @error('photo') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- 2. Items --}}
            <section class="card p-0 lg:p-0">
                <div class="border-b border-line p-4 lg:p-5">
                    <h2 class="section-label mb-3">Items</h2>
                    <div class="relative" @click.outside="open = false">
                        <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-faint" />
                        <input x-ref="search" type="search" x-model="query" @input="open = true; active = 0" @focus="open = true"
                               @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                               @keydown.enter.prevent="pick(results[active])" @keydown.escape="open = false"
                               class="input pl-10" placeholder="Type item name, code or scan barcode. 10*gleam adds 10" autocomplete="off"
                               aria-label="Search items to add" data-search>
                        <div x-show="open && (results.length || query.trim())" x-cloak
                             class="absolute inset-x-0 top-full z-20 mt-1 max-h-80 overflow-y-auto rounded-xl border border-line bg-surface py-1 shadow-pop">
                            <template x-if="loadError"><p class="px-4 py-3 text-sm font-medium text-bad">Couldn't load the item list. Check the internet and reload.</p></template>
                            <template x-if="!loadError && !ready"><p class="px-4 py-3 text-sm text-muted">Loading items…</p></template>
                            <template x-if="ready && !query.trim()"><p class="px-4 pt-2 pb-1 section-label">Popular</p></template>
                            <template x-for="(p, i) in results" :key="p.id">
                                <button type="button" @click="pick(p)" @mouseenter="active = i"
                                        :class="i === active ? 'bg-sunken' : ''"
                                        class="flex w-full items-center gap-3 px-4 py-2.5 text-left">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-ink" x-text="p.name"></span>
                                        <span class="block truncate text-xs text-muted" x-text="[p.brand, p.sku].filter(Boolean).join(' · ')"></span>
                                    </span>
                                    <span class="text-right text-xs text-muted">
                                        <span class="block tnum" x-text="'In stock ' + p.on_hand"></span>
                                        <span class="block tnum" x-text="p.cost_paise ? 'Last cost ' + jgRupees(p.cost_paise) : 'No cost yet'"></span>
                                    </span>
                                </button>
                            </template>
                            <template x-if="ready && query.trim() && !results.length">
                                <p class="px-4 py-3 text-sm text-ink-2">No item called “<span x-text="query"></span>”. Add it on the Stock page first, then come back.</p>
                            </template>
                        </div>
                    </div>
                    @error('items') <p class="error">{{ $message }}</p> @enderror
                </div>

                <template x-if="!lines.length">
                    <x-ui.empty-state icon="box" text="No items yet. Search above and press Enter to add each item from the bill." compact />
                </template>

                <div x-show="lines.length" x-cloak>
                    {{-- PC: one row per line --}}
                    <table class="table-pc hidden table-fixed lg:table">
                        <colgroup><col><col class="w-[88px]"><col class="w-[136px]"><col class="w-[92px]"><col class="w-[120px]"><col class="w-[52px]"></colgroup>
                        <thead><tr><th>Item</th><th class="!px-2 text-right">Qty</th><th class="!px-2 text-right" title="Cost per unit, before GST">Cost / unit</th><th class="!px-2">GST</th><th class="text-right">Line total</th><th></th></tr></thead>
                        <tbody>
                            <template x-for="(l, i) in lines" :key="l.key">
                                <tr>
                                    <td class="!py-2">
                                        <div class="truncate font-semibold text-ink" x-text="l.name" :title="l.name"></div>
                                        <div class="truncate text-xs text-muted" x-text="l.hint" :title="l.hint"></div>
                                    </td>
                                    <td class="!px-2"><input type="number" min="1" step="1" class="input h-9 min-h-9 px-2 text-right tnum" x-model="l.qty" :aria-label="'Qty for ' + l.name" :data-qty="i"></td>
                                    <td class="!px-2">
                                        <div class="relative"><span class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-sm text-muted">₹</span>
                                        <input inputmode="decimal" class="input h-9 min-h-9 pl-6 pr-2 text-right tnum" x-model="l.cost" :aria-label="'Cost for ' + l.name" @keydown.enter.prevent="$refs.search.focus()"></div>
                                    </td>
                                    <td class="!px-2">
                                        <select class="input h-9 min-h-9 px-2" x-model="l.gst_rate" :aria-label="'GST for ' + l.name">
                                            <template x-for="r in gstRates" :key="r"><option :value="String(r)" x-text="r + '%'" :selected="String(r) === String(l.gst_rate)"></option></template>
                                        </select>
                                    </td>
                                    <td class="tnum text-right font-semibold text-ink" x-text="jgRupees(lineTotal(l))"></td>
                                    <td class="!px-1 text-right"><button type="button" class="btn-icon size-9" @click="remove(i)" :aria-label="'Remove ' + l.name"><x-icon name="x" class="size-4" /></button></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    {{-- Phone: stacked --}}
                    <ul class="divide-y divide-line lg:hidden">
                        <template x-for="(l, i) in lines" :key="'m' + l.key">
                            <li class="space-y-2 px-4 py-3">
                                <div class="flex items-start gap-2">
                                    <div class="min-w-0 flex-1"><div class="font-semibold text-ink" x-text="l.name"></div><div class="text-xs text-muted" x-text="l.hint"></div></div>
                                    <button type="button" class="btn-icon -mt-2 -mr-2" @click="remove(i)" :aria-label="'Remove ' + l.name"><x-icon name="x" class="size-5" /></button>
                                </div>
                                <div class="grid grid-cols-[80px_1fr_84px] gap-2">
                                    <input type="number" min="1" class="input text-right tnum" x-model="l.qty" aria-label="Qty">
                                    <input inputmode="decimal" class="input text-right tnum" x-model="l.cost" placeholder="Cost ₹" aria-label="Cost per unit before GST">
                                    <select class="input px-2" x-model="l.gst_rate" aria-label="GST"><template x-for="r in gstRates" :key="r"><option :value="String(r)" x-text="r + '%'" :selected="String(r) === String(l.gst_rate)"></option></template></select>
                                </div>
                                <div class="text-right text-sm font-semibold tnum" x-text="jgRupees(lineTotal(l))"></div>
                            </li>
                        </template>
                    </ul>

                    {{-- What is posted --}}
                    <template x-for="(l, i) in lines" :key="'h' + l.key">
                        <div>
                            <input type="hidden" :name="'items[' + i + '][product_id]'" :value="l.product_id">
                            <input type="hidden" :name="'items[' + i + '][qty]'" :value="l.qty">
                            <input type="hidden" :name="'items[' + i + '][cost]'" :value="l.cost">
                            <input type="hidden" :name="'items[' + i + '][gst_rate]'" :value="l.gst_rate">
                        </div>
                    </template>
                </div>
                @if ($errors->has('items.*'))
                    <div class="border-t border-line px-4 py-3 lg:px-5">
                        @foreach (collect($errors->get('items.*'))->flatten()->unique() as $msg) <p class="error mt-0">{{ $msg }}</p> @endforeach
                    </div>
                @endif
            </section>
        </div>

        {{-- 3. Totals and save --}}
        <aside class="lg:col-span-4">
            <div class="card space-y-4 lg:sticky lg:top-24">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted"><span x-text="lines.length"></span> <span x-text="lines.length === 1 ? 'item' : 'items'"></span> · <span x-text="totalQty"></span> pcs</dt><dd class="tnum font-medium" x-text="jgRupees(subtotal)"></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">GST input</dt><dd class="tnum font-medium" x-text="jgRupees(gst)"></dd></div>
                    <div class="flex items-end justify-between border-t border-line pt-3"><dt class="text-[15px] font-semibold">Bill total</dt><dd class="tnum text-2xl font-bold tracking-[-0.01em]" x-text="jgRupees(subtotal + gst)"></dd></div>
                </dl>

                <div>
                    <label class="label" for="paid">Paid now <span class="font-normal text-muted">(leave empty if on credit)</span></label>
                    <div class="grid grid-cols-[1fr_110px] gap-2">
                        <div class="relative"><span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted">₹</span>
                            <input id="paid" name="paid" x-model="paid" inputmode="decimal" class="input pl-7 text-right tnum" placeholder="0"></div>
                        <select name="paid_mode" class="input" aria-label="Paid by">
                            @foreach ($modes as $k => $label) <option value="{{ $k }}" @selected(old('paid_mode', 'cash') === $k)>{{ $label }}</option> @endforeach
                        </select>
                    </div>
                    <button type="button" class="mt-1.5 text-[13px] font-semibold text-ink underline-offset-4 hover:underline" x-show="subtotal > 0" @click="paid = (Math.round(subtotal + gst) / 100).toFixed(2).replace(/\.00$/, '')">Paid in full</button>
                    @error('paid') <p class="error">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-[13px] font-medium" x-show="subtotal > 0" :class="due > 0 ? 'text-bad' : 'text-ok'" x-text="due > 0 ? 'Goes to supplier balance: ' + jgRupees(due) : 'Nothing left to pay'"></p>
                </div>

                <div>
                    <label class="label" for="note">Note <span class="font-normal text-muted">(optional)</span></label>
                    <input id="note" name="note" value="{{ old('note') }}" class="input" maxlength="500" placeholder="e.g. 2 boxes short, will come Monday">
                </div>

                <button class="btn-accent w-full" :disabled="!lines.length || submitting">
                    <x-icon name="check" class="size-5" stroke="2" /> <span x-text="submitting ? 'Saving…' : 'Save & add to stock'">Save &amp; add to stock</span>
                    <span class="kbd hidden border-ink/20 bg-transparent text-ink/70 xl:inline-flex">Ctrl ↵</span>
                </button>
                <p class="text-xs text-muted">Stock goes up for every item and its cost price becomes the cost on this bill.</p>
            </div>
        </aside>
    </form>
    </div>
</x-layouts.app>
