{{-- Bill discount: flat ₹ or %, a reason chip, and the Sales cap warning. --}}
<div class="flex items-center gap-2">
    <div class="grid grid-cols-2 rounded-md bg-zinc-200 p-0.5 text-xs font-semibold">
        <button type="button" class="h-8 rounded px-3" :class="disc.mode === 'flat' ? 'bg-white text-ink' : 'text-zinc-600'" @click="disc.mode = 'flat'">₹</button>
        <button type="button" class="h-8 rounded px-3" :class="disc.mode === 'pct' ? 'bg-white text-ink' : 'text-zinc-600'" @click="disc.mode = 'pct'">%</button>
    </div>
    <input x-ref="discValue{{ $sfx ?? '' }}" x-model="disc.value" inputmode="decimal" :placeholder="disc.mode === 'pct' ? 'e.g. 5' : 'e.g. 500'"
           @keydown.enter.prevent="disc.open = false" aria-label="Discount"
           class="h-9 min-w-0 flex-1 rounded-md border border-zinc-300 bg-white px-2 text-right font-semibold tabular-nums focus:border-ink focus:outline-none">
    <button type="button" class="h-9 rounded-md bg-ink px-3 text-sm font-semibold text-white" @click="disc.open = false">Done</button>
</div>
<div class="mt-2 flex flex-wrap gap-1.5">
    <template x-for="r in ['Festival offer', 'Regular customer', 'Bargain']" :key="r">
        <button type="button" class="h-8 rounded-full border px-3 text-xs font-semibold" :class="disc.reason === r ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white text-ink'" @click="disc.reason = (disc.reason === r ? '' : r)" x-text="r"></button>
    </template>
    <button type="button" x-show="disc.value" class="h-8 px-2 text-xs font-semibold text-zinc-600 underline" @click="disc.value = ''; disc.reason = ''">Remove discount</button>
</div>
<p class="mt-2 text-xs font-semibold text-red-600" x-show="discountOverLimit">
    That is <span x-text="discountPercent.toFixed(1)"></span>% off. Above <span x-text="cfg.limits.discountPercent"></span>% needs the Owner’s approval (ask Admin).
</p>
