{{-- Log result controls. Used in the PC detail pane and the phone bottom sheet; both bind to the same Alpine state. --}}
<div>
    <div class="grid grid-cols-2 gap-2 xl:grid-cols-3">
        <template x-for="(o, i) in outcomeList.slice(0, 6)" :key="o.key">
            <button type="button" @click="pick(o.key)"
                    class="flex min-h-12 items-center gap-2.5 rounded-lg border px-3 text-left text-sm font-semibold transition lg:min-h-11"
                    :class="outcome === o.key
                        ? (o.lost ? 'border-red-600 bg-red-600 text-white' : 'border-ink bg-ink text-white')
                        : (o.lost ? 'border-zinc-300 bg-white text-red-700 hover:border-red-300 hover:bg-red-50' : 'border-zinc-300 bg-white text-ink hover:border-zinc-400 hover:bg-zinc-50')">
                <kbd class="hidden size-6 shrink-0 place-items-center rounded-md border text-xs font-semibold tabular-nums lg:grid"
                     :class="outcome === o.key ? 'border-white/40 text-white' : 'border-zinc-300 bg-zinc-100 text-ink'" x-text="i + 1"></kbd>
                <span x-text="o.label"></span>
            </button>
        </template>
    </div>
    <button type="button" @click="pick('wrong_number')" class="mt-1 inline-flex min-h-11 items-center text-sm font-semibold underline-offset-2 hover:underline"
            :class="outcome === 'wrong_number' ? 'text-red-600 underline' : 'text-zinc-600'">
        <span class="hidden lg:inline">7 · </span>Wrong number
    </button>

    <div x-show="needsDate" x-cloak class="mt-4">
        <div class="mb-2 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600" x-text="outcome === 'coming_on' ? 'Coming on' : 'Call back on'"></div>
        <div class="flex flex-wrap items-center gap-2">
            <template x-for="d in quickDates" :key="d.label">
                <button type="button" @click="date = d.value" class="min-h-11 rounded-full border px-4 text-sm font-semibold"
                        :class="date === d.value ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white text-ink hover:bg-zinc-50'" x-text="d.label"></button>
            </template>
            <input type="date" x-model="date" :min="today" class="min-h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm tabular-nums focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink" aria-label="Pick a date">
        </div>
    </div>

    <div class="mt-4">
        <input type="text" x-model="note" maxlength="500" placeholder="Note (optional): price they got elsewhere, what they asked…"
               class="block min-h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm placeholder:text-zinc-500 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink">
    </div>

    <p x-show="error" x-cloak class="mt-2 text-sm font-medium text-red-600" x-text="error"></p>

    <button type="button" @click="save()" :disabled="saving"
            class="mt-4 flex min-h-14 w-full items-center justify-center gap-2 rounded-lg bg-brand-400 px-4 text-base font-semibold text-ink hover:bg-[#F2C800] disabled:opacity-60 lg:min-h-12">
        <svg x-show="saving" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="3"/></svg>
        <span x-text="saveLabel"></span>
        <kbd class="ml-1 hidden rounded border border-ink/30 px-1.5 text-[11px] lg:inline" x-show="outcome">Enter</kbd>
    </button>
</div>
