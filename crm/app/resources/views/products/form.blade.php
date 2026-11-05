@php
    use App\Support\Money;
    $editing = $product->exists;
    $rupees = fn (?int $paise) => $paise === null ? '' : rtrim(rtrim(number_format($paise / 100, 2, '.', ''), '0'), '.');
    $gst = (string) (int) old('gst_rate', (int) $product->gst_rate);
    $aliases = old('aliases', $product->aliases);
    $advancedErrors = $errors->hasAny(['sku', 'hsn', 'unit', 'reorder_level', 'shelf', 'warranty', 'compatible_cars', 'notes', 'cost']);
@endphp
<x-layouts.app :title="$editing ? 'Edit '.$product->name : 'Add product'">
    <x-slot:header>
        <div>
            <a href="{{ $editing ? route('products.show', $product) : route('products.index') }}" class="text-sm font-semibold text-zinc-700 hover:text-ink">← {{ $editing ? $product->name : 'Stock & prices' }}</a>
            <h1 class="mt-1 text-[22px] font-bold leading-7">{{ $editing ? 'Edit product' : 'Add product' }}</h1>
        </div>
    </x-slot:header>

    <form method="POST" action="{{ $editing ? route('products.update', $product) : route('products.store') }}"
          class="max-w-3xl space-y-5 rounded-xl border border-zinc-200 bg-white p-5 lg:p-6"
          x-data="{
              type: @js(old('type', $product->type)),
              mrp: @js((string) old('mrp', $rupees($product->mrp_paise))),
              price: @js((string) old('price', $rupees($product->price_paise))),
              num(v) { const n = parseFloat(String(v).replace(/[₹,\s]/g, '')); return isNaN(n) ? null : n },
              inr(n) { return '₹' + Math.round(n).toLocaleString('en-IN') },
              get saving() { const m = this.num(this.mrp), p = this.num(this.price); return m && p !== null && m > p ? m - p : 0 },
              get tooHigh() { const m = this.num(this.mrp), p = this.num(this.price); return m && p !== null && p > m },
              get percent() { return this.saving ? Math.floor(this.saving * 100 / this.num(this.mrp)) : 0 },
          }">
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- Basics --}}
        <div>
            <label class="label" for="name">Name (as on the bill)</label>
            <input class="input text-[15px]" id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="160" autofocus placeholder="DHC Gleam 2.0 LED headlight H4">
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div x-data="{
                chips: {{ \Illuminate\Support\Js::from(array_values(array_filter(array_map('trim', explode(',', (string) $aliases))))) }},
                draft: '',
                add() { this.draft.split(',').map(s => s.trim().toLowerCase()).filter(s => s && !this.chips.includes(s)).forEach(s => this.chips.push(s)); this.draft = '' },
             }">
            <label class="label" for="alias-input">Also called <span class="font-normal text-zinc-600">(other names staff type)</span></label>
            <div class="flex min-h-11 flex-wrap items-center gap-1.5 rounded-xl border border-zinc-300 bg-white px-2 py-1.5 focus-within:border-ink focus-within:ring-2 focus-within:ring-brand-400">
                <template x-for="(chip, i) in chips" :key="chip">
                    <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 py-0.5 pl-2.5 pr-1 text-[13px] text-zinc-800">
                        <span x-text="chip"></span>
                        <button type="button" class="inline-flex size-5 items-center justify-center rounded-full text-zinc-600 hover:bg-zinc-200" @click="chips.splice(i, 1)" :aria-label="'Remove ' + chip">×</button>
                    </span>
                </template>
                <input id="alias-input" x-model="draft" @keydown.enter.prevent="add()" @keydown.comma.prevent="add()" @blur="add()"
                       @keydown.backspace="if (draft === '' && chips.length) chips.pop()"
                       class="min-w-[10rem] flex-1 border-0 bg-transparent px-1 text-[15px] outline-none sm:text-sm" placeholder="gleam, h4 led, headlight bulb…">
            </div>
            <input type="hidden" name="aliases" :value="chips.join(', ')" value="{{ $aliases }}">
            <p class="mt-1 text-xs text-zinc-600">Press Enter or comma after each word. Hinglish spellings help too: "seat kavar", "mat".</p>
            @error('aliases') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="brand_name">Brand</label>
                <input class="input" id="brand_name" name="brand_name" list="brand-list" value="{{ old('brand_name', $product->brand?->name) }}" maxlength="80" autocomplete="off" placeholder="Pick or type a new brand">
                <datalist id="brand-list">@foreach ($brands as $b)<option value="{{ $b }}">@endforeach</datalist>
                @error('brand_name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="category_name">Category</label>
                <input class="input" id="category_name" name="category_name" list="category-list" value="{{ old('category_name', $product->category?->name) }}" maxlength="80" autocomplete="off" placeholder="Pick or type a new category">
                <datalist id="category-list">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
                @error('category_name') <p class="error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <span class="label">This is a</span>
            <div class="inline-flex rounded-lg bg-zinc-100 p-1">
                @foreach (['product' => 'Product (has stock)', 'service' => 'Service (fitting, labour)'] as $val => $label)
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="{{ $val }}" x-model="type" class="peer sr-only">
                        <span class="inline-flex min-h-10 items-center rounded-md px-3.5 text-sm font-medium text-zinc-600 peer-checked:bg-white peer-checked:font-semibold peer-checked:text-ink peer-checked:shadow-[0_0_0_1px_#E4E4E7] peer-focus-visible:ring-2 peer-focus-visible:ring-brand-400">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('type') <p class="error">{{ $message }}</p> @enderror
        </div>

        {{-- Prices --}}
        <fieldset class="rounded-xl border border-zinc-200 bg-zinc-50 p-4" @disabled(! $canPrice)>
            <legend class="px-1 text-xs font-semibold uppercase tracking-wider text-zinc-600">Price</legend>
            @unless ($canPrice)
                <p class="mb-3 text-sm text-zinc-700">Only the owner can change prices. Current: <strong class="tabular-nums">{{ Money::format($product->price_paise) }}</strong>@if ($product->mrp_paise) (normal {{ Money::format($product->mrp_paise) }})@endif.</p>
            @endunless
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label" for="mrp">Normal price (MRP)</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500">₹</span>
                        <input class="input pl-7 text-right tabular-nums" id="mrp" name="mrp" x-model="mrp" inputmode="decimal" placeholder="6,499">
                    </div>
                    @error('mrp') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="price">Our price (selling)</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500">₹</span>
                        <input class="input pl-7 text-right text-[17px] font-semibold tabular-nums" id="price" name="price" x-model="price" inputmode="decimal" @required(! $editing && $canPrice) placeholder="4,999" :class="tooHigh && 'border-red-600'">
                    </div>
                    @error('price') <p class="error">{{ $message }}</p> @enderror
                    <p class="error" x-show="tooHigh" x-cloak>Our price is more than the normal price.</p>
                </div>
                <div>
                    <label class="label" for="gst_rate">GST</label>
                    <select class="input" id="gst_rate" name="gst_rate" @disabled(! $canPrice)>
                        @foreach ([0, 5, 12, 18, 28] as $rate)
                            <option value="{{ $rate }}" @selected($gst === (string) $rate)>{{ $rate }}%</option>
                        @endforeach
                    </select>
                    @error('gst_rate') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <p class="mt-3 text-sm font-medium" aria-live="polite">
                <template x-if="saving > 0"><span class="text-green-700"><span x-text="percent"></span>% off · customer saves <span x-text="inr(saving)"></span></span></template>
                <template x-if="saving === 0 && !tooHigh"><span class="text-zinc-600">Fill both to show the discount on the bill.</span></template>
            </p>
            <label class="mt-2 flex items-center gap-2 text-sm text-zinc-700">
                <input type="hidden" name="price_includes_gst" value="0">
                <input type="checkbox" name="price_includes_gst" value="1" class="size-4 rounded border-zinc-300" @checked(old('price_includes_gst', $product->price_includes_gst ?? true))>
                Prices include GST
            </label>
        </fieldset>
        @unless ($canPrice)
            {{-- The GST select is disabled with the fieldset; send the current rate. --}}
            <input type="hidden" name="gst_rate" value="{{ (int) $product->gst_rate }}">
        @endunless

        @unless ($editing)
            <div x-show="type === 'product'" class="max-w-xs">
                <label class="label" for="opening_stock">Stock in the shop now</label>
                <input class="input tabular-nums" id="opening_stock" name="opening_stock" type="number" min="0" inputmode="numeric" value="{{ old('opening_stock', 0) }}">
                @error('opening_stock') <p class="error">{{ $message }}</p> @enderror
            </div>
        @endunless

        {{-- More details --}}
        <details class="group rounded-xl border border-zinc-200" @if ($advancedErrors) open @endif>
            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between px-4 text-sm font-semibold">
                More details <span class="font-normal text-zinc-600">HSN, shelf, warranty, fits cars, code{{ $canCost ? ', cost' : '' }}</span>
            </summary>
            <div class="grid gap-4 border-t border-zinc-200 p-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="hsn"><span x-text="type === 'service' ? 'SAC code' : 'HSN code'">HSN code</span></label>
                    <input class="input tabular-nums" id="hsn" name="hsn" inputmode="numeric" value="{{ old('hsn', $product->hsn) }}" maxlength="8" placeholder="85122010">
                    @error('hsn') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="sku">Code / SKU</label>
                    <input class="input uppercase" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" maxlength="40" placeholder="DHC-GLM2-H4">
                    @error('sku') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div x-show="type === 'product'">
                    <label class="label" for="reorder_level">Warn me when stock is at</label>
                    <input class="input tabular-nums" id="reorder_level" name="reorder_level" type="number" min="0" inputmode="numeric" value="{{ old('reorder_level', $product->reorder_level) }}">
                    @error('reorder_level') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="unit">Unit</label>
                    <input class="input" id="unit" name="unit" list="unit-list" value="{{ old('unit', $product->unit) }}" maxlength="10">
                    <datalist id="unit-list"><option value="pcs"><option value="pair"><option value="set"><option value="kit"><option value="ft"><option value="job"><option value="panel"></datalist>
                    @error('unit') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="shelf">Shelf</label>
                    <input class="input" id="shelf" name="shelf" value="{{ old('shelf', $product->shelf) }}" maxlength="40" placeholder="A3">
                    @error('shelf') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="warranty">Warranty</label>
                    <input class="input" id="warranty" name="warranty" value="{{ old('warranty', $product->warranty) }}" maxlength="80" placeholder="1 year">
                    @error('warranty') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="label" for="compatible_cars">Fits cars <span class="font-normal text-zinc-600">(comma between models)</span></label>
                    <input class="input" id="compatible_cars" name="compatible_cars" value="{{ old('compatible_cars', implode(', ', $product->compatible_cars ?? [])) }}" maxlength="500" placeholder="Hyundai Creta, Kia Seltos, Tata Nexon">
                    @error('compatible_cars') <p class="error">{{ $message }}</p> @enderror
                </div>
                @if ($canCost)
                    <div>
                        <label class="label" for="cost">Cost price <span class="font-normal text-zinc-600">(only you see this)</span></label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500">₹</span>
                            <input class="input pl-7 text-right tabular-nums" id="cost" name="cost" inputmode="decimal" value="{{ old('cost', $rupees($product->cost_paise)) }}">
                        </div>
                        @error('cost') <p class="error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <label class="label" for="notes">Notes for staff</label>
                    <textarea class="input min-h-20 py-2" id="notes" name="notes" maxlength="1000">{{ old('notes', $product->notes) }}</textarea>
                    @error('notes') <p class="error">{{ $message }}</p> @enderror
                </div>
                @if ($editing)
                    <label class="flex items-center gap-2 text-sm sm:col-span-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="size-4 rounded border-zinc-300" @checked(old('is_active', $product->is_active))>
                        Show this item (untick to hide it from search and bills)
                    </label>
                @endif
            </div>
        </details>

        <div class="flex flex-col-reverse gap-2 border-t border-zinc-200 pt-4 sm:flex-row sm:justify-end">
            <a href="{{ $editing ? route('products.show', $product) : route('products.index') }}" class="btn-ghost">Cancel</a>
            <button class="btn-accent min-h-12 sm:min-h-11" :disabled="tooHigh">{{ $editing ? 'Save changes' : 'Save product' }}</button>
        </div>
    </form>
</x-layouts.app>
