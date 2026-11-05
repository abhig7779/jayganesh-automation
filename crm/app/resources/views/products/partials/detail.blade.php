@php
    use App\Support\Money;
    $inPanel = $inPanel ?? false;
    $p = $product;
    $off = $p->discountPercent();
    $rate = (float) $p->gst_rate;
    $rateLabel = rtrim(rtrim(number_format($rate, 2), '0'), '.');
    // GST inside the price (or on top of it), split CGST + SGST for an in-state sale.
    $bp = (int) round($rate * 100);
    $tax = $p->price_includes_gst
        ? $p->price_paise - intdiv($p->price_paise * 10000 + intdiv(10000 + $bp, 2), 10000 + $bp)
        : Money::percent($p->price_paise, $rate);
    $cgst = intdiv($tax, 2);
    $sgst = $tax - $cgst;
    $margin = $p->cost_paise !== null ? $p->price_paise - $p->cost_paise : null;
    $unit = $p->unit ?: 'pcs';
    $state = $p->stockState();
    [$stateCls, $stateText] = match ($state) {
        'out' => ['border-red-200 bg-red-50 text-red-600', 'Out of stock'],
        'low' => ['border-amber-200 bg-amber-50 text-amber-700', 'Low stock'],
        default => ['border-green-200 bg-green-50 text-green-700', 'Adequate stock'],
    };
    $moveLabel = function ($m) {
        $ref = $m->reference_type && class_exists($m->reference_type) ? $m->reference : null;
        $refText = $ref ? (($m->type === 'sale' || $m->type === 'sale_cancel' ? 'Bill ' : '').($ref->number ?? '#'.$ref->getKey())) : null;

        return collect([\App\Models\StockMove::LABELS[$m->type] ?? $m->type, $refText, $m->note && $m->type !== 'opening' ? $m->note : null])->filter()->implode(' · ');
    };
    $when = function ($t) {
        $t = $t->timezone('Asia/Kolkata');

        return $t->isToday() ? 'Today '.$t->format('H:i') : ($t->isYesterday() ? 'Yesterday '.$t->format('H:i') : $t->format('j M'));
    };
@endphp
<div x-data="{ adjusting: {{ $errors->hasAny(['new_count', 'reason', 'note']) ? 'true' : 'false' }} }" class="flex min-h-full flex-col" data-product-detail="{{ $p->id }}">
    <div class="flex-1 space-y-4 px-5 py-4">
        {{-- Header --}}
        <div>
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2">
                    @if ($p->brand)
                        <span class="rounded bg-ink px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white">{{ $p->brand->name }}</span>
                    @endif
                    @unless ($p->is_active) <span class="rounded-full border border-red-200 bg-red-50 px-2 text-[11px] font-semibold text-red-600">Hidden</span> @endunless
                </div>
                <div class="flex items-center gap-1">
                    @if ($p->sku) <span class="text-xs text-zinc-600">SKU: <span class="font-semibold text-ink">{{ $p->sku }}</span></span> @endif
                    @if ($inPanel)
                        <button type="button" @click="$dispatch('product-close')" class="-mr-2 inline-flex size-10 items-center justify-center rounded-lg text-zinc-600 hover:bg-zinc-100 lg:hidden" aria-label="Close"><x-icon name="x" /></button>
                    @endif
                </div>
            </div>
            <h2 class="mt-1.5 text-[19px] font-bold leading-6 text-ink">{{ $p->name }}</h2>
            <p class="mt-0.5 text-[13px] text-zinc-600">
                @if ($p->category) Category: <span class="font-medium text-ink-2 text-zinc-700">{{ $p->category->name }}</span> · @endif
                Type: <span class="font-medium text-zinc-700">{{ $p->isService() ? 'Service' : 'Product' }}{{ $unit !== 'pcs' && ! $p->isService() ? ' ('.$unit.')' : '' }}</span>
            </p>
        </div>

        @if ($p->aliasList())
            <div>
                <h3 class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Also called / counter keywords</h3>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($p->aliasList() as $alias)
                        <span class="rounded border border-zinc-200 bg-zinc-100 px-2 py-0.5 text-xs text-zinc-700">{{ $alias }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Price card --}}
        <section class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
            <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                <div class="text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Counter sale price</div>
                @if ($off > 0)
                    <span class="whitespace-nowrap rounded-full border border-green-200 bg-green-50 px-2 py-0.5 text-[11px] font-semibold text-green-700">You save {{ Money::format($p->discountPaise()) }} ({{ $off }}% off)</span>
                @endif
            </div>
            <div class="mt-1 flex flex-wrap items-baseline gap-x-2">
                <span class="text-[34px] font-bold leading-10 tracking-tight tabular-nums text-ink" data-price>{{ Money::format($p->price_paise) }}</span>
                @if ($p->mrp_paise && $off > 0)
                    <s class="text-sm font-medium tabular-nums text-zinc-500">{{ Money::format($p->mrp_paise) }} MRP</s>
                @elseif ($p->mrp_paise)
                    <span class="text-sm tabular-nums text-zinc-600">MRP {{ Money::format($p->mrp_paise) }}</span>
                @endif
            </div>
            <dl class="mt-3 space-y-1.5 border-t border-zinc-200 pt-3 text-[13px]">
                <div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-zinc-600">GST {{ $rateLabel }}% {{ $p->price_includes_gst ? 'included' : 'extra' }}</dt>
                        <dd class="font-medium tabular-nums">{{ $tax > 0 ? Money::format($tax, true) : 'No GST' }}</dd>
                    </div>
                    @if ($tax > 0)
                        <dd class="text-right text-xs tabular-nums text-zinc-600">CGST {{ Money::format($cgst, true) }} + SGST {{ Money::format($sgst, true) }}</dd>
                    @endif
                </div>
                @if ($p->hsn)
                    <div class="flex justify-between gap-3"><dt class="text-zinc-600">{{ $p->isService() ? 'SAC' : 'HSN' }} code</dt><dd class="font-medium tabular-nums">{{ $p->hsn }}</dd></div>
                @endif
                <div class="flex justify-between gap-3"><dt class="text-zinc-600">Warranty</dt><dd @class(['font-medium', 'text-green-700' => $p->warranty])>{{ $p->warranty ?: 'No warranty' }}</dd></div>
                @if ($canMargins && $margin !== null)
                    <div class="flex justify-between gap-3" data-margins><dt class="text-zinc-600">Cost · margin</dt>
                        <dd class="tabular-nums"><span class="font-medium">{{ Money::format($p->cost_paise) }}</span> · <span @class(['font-semibold', 'text-red-600' => $margin < 0])>{{ Money::format($margin) }}</span>@if ($p->price_paise > 0) ({{ intdiv($margin * 100, $p->price_paise) }}%)@endif</dd>
                    </div>
                @endif
            </dl>
        </section>

        @if ($p->compatible_cars)
            <div>
                <h3 class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Compatible cars</h3>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($p->compatible_cars as $car)
                        <span class="rounded border border-zinc-300 bg-white px-2 py-0.5 text-xs font-medium text-ink">{{ $car }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Stock card --}}
        @if ($p->track_stock)
            <section class="rounded-xl border border-zinc-200 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs text-zinc-600">Stock on hand</div>
                        <div class="text-[19px] font-bold tabular-nums">{{ $p->on_hand }} {{ $unit === 'pcs' ? \Illuminate\Support\Str::plural('pc', abs($p->on_hand)) : \Illuminate\Support\Str::plural($unit, abs($p->on_hand)) }}</div>
                    </div>
                    <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $stateCls }}" data-stock="{{ $state }}">{{ $stateText }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between gap-2 rounded-lg bg-zinc-100 px-3 py-2 text-[13px]">
                    <span>Shelf <strong>{{ $p->shelf ?: 'not set' }}</strong></span>
                    <span class="text-zinc-600">Warn at {{ $p->reorder_level }}</span>
                </div>

                <h3 class="mb-1 mt-3 text-[11px] font-semibold uppercase tracking-wider text-zinc-600">Recent movements</h3>
                @if ($moves->isEmpty())
                    <p class="py-2 text-[13px] text-zinc-600">No stock moves yet. Stock changes when you sell, buy or adjust.</p>
                @else
                    <ul class="divide-y divide-zinc-100 text-[13px]">
                        @foreach ($moves as $m)
                            <li class="flex items-center justify-between gap-3 py-1.5">
                                <span class="min-w-0 truncate text-zinc-700" title="{{ $m->creator?->name }}">{{ $when($m->created_at) }} · {{ $moveLabel($m) }}</span>
                                <span @class(['shrink-0 font-semibold tabular-nums', 'text-green-700' => $m->qty > 0, 'text-red-600' => $m->qty < 0])>{{ $m->qty > 0 ? '+' : '' }}{{ $m->qty }}{{ $unit !== 'pcs' ? ' '.\Illuminate\Support\Str::plural($unit, abs($m->qty)) : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        @if ($p->notes)
            <p class="whitespace-pre-line rounded-lg border border-zinc-200 px-3 py-2 text-[13px] text-zinc-700">{{ $p->notes }}</p>
        @endif
    </div>

    {{-- Actions, kept in reach at the bottom of the panel --}}
    <div @class(['space-y-2 border-t border-zinc-200 bg-white px-5 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]', 'sticky bottom-0' => $inPanel])>
        @if ($billUrl)
            <a href="{{ $billUrl }}" class="btn-primary min-h-12 w-full lg:min-h-11">
                <x-icon name="plus" /> Add to new bill <kbd class="rounded bg-white/15 px-1.5 text-[11px] font-semibold">F2</kbd>
            </a>
        @endif
        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn min-h-12 w-full border border-green-600 bg-white text-green-700 hover:bg-green-50 lg:min-h-11">
            <x-icon name="chat" /> Send price on WhatsApp
        </a>
        @if ($canManage)
            <div class="grid grid-cols-2 gap-2">
                @if ($p->track_stock)
                    <button type="button" @click="adjusting = true" class="btn-ghost">Adjust stock</button>
                @endif
                <a href="{{ route('products.edit', $p) }}" @class(['btn-ghost', 'col-span-2' => ! $p->track_stock])>Edit product</a>
            </div>
        @endif
    </div>

    {{-- Stock adjustment sheet --}}
    @if ($canManage && $p->track_stock)
        <div x-show="adjusting" x-cloak class="fixed inset-0 z-[60]" @keydown.escape.stop="adjusting = false">
            <div class="absolute inset-0 bg-ink/40" @click="adjusting = false"></div>
            <form method="POST" action="{{ route('products.adjust', $p) }}"
                  x-data="{ reason: @js(old('reason', 'count')) }"
                  class="absolute inset-x-0 bottom-0 rounded-t-2xl bg-white p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_rgba(0,0,0,.08)] sm:inset-x-auto sm:bottom-auto sm:left-1/2 sm:top-1/2 sm:w-[440px] sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl">
                @csrf
                <div class="mx-auto -mt-2 mb-3 h-1 w-9 rounded-full bg-zinc-300 sm:hidden"></div>
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-[22px] font-bold leading-7">Adjust stock</h3>
                        <p class="text-sm text-zinc-600">{{ $p->name }} · now {{ $p->on_hand }} {{ $unit }}</p>
                    </div>
                    <button type="button" @click="adjusting = false" class="inline-flex size-10 items-center justify-center rounded-lg text-zinc-600 hover:bg-zinc-100" aria-label="Close"><x-icon name="x" /></button>
                </div>
                <label class="label" for="new_count_{{ $p->id }}">New count</label>
                <input id="new_count_{{ $p->id }}" name="new_count" type="number" inputmode="numeric" class="input text-lg font-semibold tabular-nums" value="{{ old('new_count', $p->on_hand) }}" required x-init="$watch('adjusting', v => v && $nextTick(() => { $el.focus(); $el.select() }))">
                @error('new_count') <p class="error">{{ $message }}</p> @enderror

                <div class="label mt-4">Reason</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($reasons as $key => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="reason" value="{{ $key }}" x-model="reason" class="peer sr-only">
                            <span class="inline-flex min-h-10 items-center rounded-full border border-zinc-300 px-3.5 text-sm font-medium peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-400">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                @error('reason') <p class="error">{{ $message }}</p> @enderror

                <label class="label mt-4" for="note_{{ $p->id }}">Note <span class="font-normal text-zinc-600" x-text="reason === 'other' ? '(needed)' : '(optional)'"></span></label>
                <input id="note_{{ $p->id }}" name="note" class="input" maxlength="150" value="{{ old('note') }}" placeholder="e.g. Box found damaged in store room">
                @error('note') <p class="error">{{ $message }}</p> @enderror

                <button class="btn-accent mt-5 min-h-12 w-full">Save new count</button>
            </form>
        </div>
    @endif
</div>
