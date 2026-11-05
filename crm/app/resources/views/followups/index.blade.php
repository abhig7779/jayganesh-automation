<x-layouts.app title="Follow-ups">
@php
    $outcomeOrder = ['coming_on', 'call_later', 'no_answer', 'wants_better_price', 'bought_elsewhere', 'not_interested', 'wrong_number'];
    $outcomeList = collect($outcomeOrder)->map(fn ($k) => ['key' => $k, 'label' => $outcomes[$k], 'lost' => in_array($k, \App\Models\FollowUp::LOST_OUTCOMES, true)])->values();
    $kbd = 'rounded border border-zinc-300 bg-white px-1 text-[11px] font-semibold text-ink';
@endphp
<div x-data="followupsQueue({ rows: @js($rows), upcoming: @js($upcoming), outcomes: @js($outcomeList), select: @js((int) request('select')), me: @js(auth()->id()) })"
     @keydown.window="key($event)" @followup-logged.window="onLogged($event.detail)" @followup-restored.window="onRestored($event.detail)">

    {{-- ===== Header: title, due pill, filter chips, search ===== --}}
    <div class="mb-4 flex flex-col gap-3 min-[1400px]:flex-row min-[1400px]:items-center min-[1400px]:justify-between">
        <div class="flex items-center gap-3">
            <h1 class="whitespace-nowrap text-[28px] font-bold leading-tight tracking-[-0.02em] lg:text-[22px]">Follow-ups</h1>
            <span x-show="countOf('today') + countOf('overdue') > 0" class="whitespace-nowrap rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-600 tabular-nums">
                <span x-text="countOf('today') + countOf('overdue')"></span> due today<span x-show="countOf('overdue')"> · <span x-text="countOf('overdue')"></span> overdue</span>
            </span>
        </div>
        <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
            <div class="-mx-4 flex gap-1.5 overflow-x-auto px-4 lg:mx-0 lg:px-0">
                <template x-for="f in filters" :key="f.key">
                    <button type="button" @click="setFilter(f.key)" class="flex h-10 shrink-0 flex-col items-center justify-center rounded-lg px-3 text-xs font-semibold leading-tight"
                            :class="filter === f.key ? 'bg-ink text-white' : 'bg-white text-ink hover:bg-zinc-100 lg:bg-transparent'">
                        <span x-text="f.label"></span>
                        <span class="tabular-nums" :class="filter === f.key ? 'text-white/80' : (f.key === 'overdue' && f.count ? 'text-red-600' : 'text-zinc-600')" x-text="f.count"></span>
                    </button>
                </template>
            </div>
            <div class="relative">
                <x-quotations.icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-500" />
                <input data-search x-model="search" @keydown.escape="search = ''; $el.blur()" placeholder="Search customer, car number or quote…"
                       class="h-10 w-full rounded-lg border border-zinc-300 bg-white pl-9 pr-8 text-sm placeholder:text-zinc-500 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink lg:w-72">
                <kbd class="absolute right-2 top-1/2 hidden -translate-y-1/2 {{ $kbd }} lg:block">/</kbd>
            </div>
        </div>
    </div>

    {{-- ===== Empty state ===== --}}
    <div x-show="!items.length" x-cloak class="rounded-xl border border-zinc-200 bg-white px-6 py-12 text-center">
        <x-quotations.icon name="check" class="mx-auto size-8 text-zinc-600" />
        <p class="mt-3 text-[15px] font-medium text-zinc-700">Nobody to chase. Every quote and enquiry is up to date.</p>
        @can('quotations.manage')<a href="{{ route('quotations.create') }}" class="mt-4 inline-flex min-h-11 items-center rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold hover:bg-zinc-50">+ New quotation</a>@endcan
    </div>

    <div x-show="items.length" class="lg:grid lg:grid-cols-[minmax(320px,380px)_1fr] lg:overflow-hidden lg:rounded-xl lg:border lg:border-zinc-200 lg:bg-white">

        {{-- ================= Left: work queue ================= --}}
        <div class="lg:flex lg:h-[calc(100dvh-160px)] lg:min-h-[560px] lg:flex-col lg:border-r lg:border-zinc-200">
            <div class="hidden items-center justify-between border-b border-zinc-200 bg-zinc-100 px-4 py-2 lg:flex">
                <span class="text-[11px] font-semibold uppercase tracking-[0.06em] text-zinc-600">Work queue</span>
                <span class="whitespace-nowrap text-[11px] text-zinc-600"><kbd class="{{ $kbd }}">↑↓</kbd> move <kbd class="{{ $kbd }}">W</kbd> WhatsApp <kbd class="{{ $kbd }}">C</kbd> call <kbd class="{{ $kbd }}">L</kbd> log</span>
            </div>
            <div class="space-y-2 lg:flex-1 lg:space-y-0 lg:overflow-y-auto">
                <template x-for="row in visible" :key="row.id">
                    <article :id="'fu-' + row.id" @click="select(row.id)"
                             class="overflow-hidden rounded-xl border bg-white transition-all duration-300 lg:cursor-pointer lg:rounded-none lg:border-0 lg:border-b lg:border-l-[3px] lg:border-b-zinc-200"
                             :class="[
                                row.leaving ? 'max-h-0 -translate-x-6 opacity-0' : 'max-h-96',
                                row.overdue ? 'border-red-200 lg:border-l-red-600' : 'border-zinc-200 lg:border-l-transparent',
                                selId === row.id ? 'lg:bg-zinc-100' : 'lg:hover:bg-[#FAFAFA]',
                                selId === row.id && !row.overdue ? 'lg:!border-l-ink' : ''
                             ]">
                        <div class="px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="truncate text-[15px] font-semibold" x-text="row.name"></span>
                                    <span x-show="row.plate" class="shrink-0 rounded border border-zinc-300 px-1.5 py-px text-[10px] font-semibold tabular-nums tracking-wide" x-text="row.plate"></span>
                                </div>
                                <span x-show="row.initials" class="grid size-6 shrink-0 place-items-center rounded-md bg-ink text-[10px] font-bold text-white" :title="row.assignee" x-text="row.initials"></span>
                            </div>
                            <div class="mt-0.5 truncate text-[13px] text-zinc-700">
                                <span x-show="row.car_model" x-text="row.car_model + ' · '"></span><span class="font-semibold text-ink" x-text="row.kind_label + (row.value ? ' ' + row.value : '')"></span><span class="text-zinc-600" x-text="' · ' + row.asked"></span>
                            </div>
                            <div class="mt-1 flex items-center justify-between gap-2 text-xs">
                                <span class="flex items-center gap-1 font-semibold" :class="row.overdue ? 'text-red-600' : 'text-zinc-600'">
                                    <x-quotations.icon name="clock" class="size-3.5" x-show="row.overdue" />
                                    <span x-text="row.overdue ? 'Overdue ' + row.due_label.replace(' late', '') : row.step_label + ' · ' + (row.bucket === 'today' ? 'due today' : row.due_label)"></span>
                                </span>
                                <span class="text-zinc-600" x-text="(row.type === 'quotation' ? 'Quoted ' : 'Asked ') + row.created_label"></span>
                            </div>
                            {{-- Phone: big buttons on every row --}}
                            <div class="mt-3 grid grid-cols-3 gap-2 lg:hidden">
                                <a :href="row.wa_url" target="_blank" rel="noopener" @click.stop="row.contacted = true"
                                   class="flex min-h-12 items-center justify-center gap-1.5 rounded-lg border border-green-700 bg-white text-sm font-semibold text-green-700"><x-quotations.icon name="chat" />WhatsApp</a>
                                <a :href="row.call_url" @click.stop="row.contacted = true"
                                   class="flex min-h-12 items-center justify-center gap-1.5 rounded-lg border border-zinc-300 bg-white text-sm font-semibold"><x-quotations.icon name="phone" />Call</a>
                                <button type="button" @click.stop="openSheet(row.id)" class="flex min-h-12 items-center justify-center rounded-lg bg-ink text-sm font-semibold text-white">Log result</button>
                            </div>
                        </div>
                    </article>
                </template>
                <p x-show="items.length && !visible.length" class="px-4 py-8 text-center text-sm text-zinc-600">
                    <span x-show="search">Nothing matches “<span x-text="search"></span>”.</span>
                    <span x-show="!search">Nothing in this filter. <button type="button" class="font-semibold underline" @click="setFilter('all')">Show all</button></span>
                </p>
            </div>
        </div>

        {{-- ================= Right: selected customer ================= --}}
        <div class="hidden bg-[#FAFAF9] lg:block lg:h-[calc(100dvh-160px)] lg:min-h-[560px] lg:overflow-y-auto">
            <template x-if="current">
                <div class="space-y-4 p-5">
                    {{-- Customer header --}}
                    <section class="rounded-xl border border-zinc-200 bg-white p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-[22px] font-bold tracking-[-0.01em]" x-text="current.name"></h2>
                                    <span x-show="current.plate" class="rounded border border-zinc-300 px-1.5 py-px text-[11px] font-semibold tabular-nums tracking-wide" x-text="current.plate"></span>
                                    <span x-show="current.car_model" class="text-sm font-medium text-zinc-600" x-text="current.car_model"></span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-[13px] font-medium text-zinc-600">
                                    <span class="flex items-center gap-1 tabular-nums"><x-quotations.icon name="phone" class="size-3.5" /><span x-text="current.mobile"></span></span>
                                    <span x-show="current.area" x-text="current.area"></span>
                                    <span x-show="current.assignee">Follow-up by <span class="text-ink" x-text="current.assignee"></span></span>
                                    <span x-show="current.last_outcome">Last: <span class="text-ink" x-text="current.last_outcome"></span></span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a :href="waHref()" target="_blank" rel="noopener" @click="current.contacted = true"
                                   class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-green-700 bg-white px-3 text-sm font-semibold text-green-700 hover:bg-green-50"><x-quotations.icon name="chat" class="size-4" />WhatsApp <kbd class="rounded border border-green-700/40 px-1 text-[11px]">W</kbd></a>
                                <a :href="current.call_url" @click="current.contacted = true"
                                   class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold hover:bg-zinc-50"><x-quotations.icon name="phone" class="size-4" />Call <kbd class="{{ $kbd }}">C</kbd></a>
                                <a :href="current.show_url" class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold hover:bg-zinc-50"
                                   x-text="current.type === 'quotation' ? 'Open quote' : 'Open lead'"></a>
                                <template x-if="current.convert_url">
                                    <form method="POST" :action="current.convert_url">@csrf
                                        <button class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-ink px-3 text-sm font-semibold text-white hover:bg-zinc-800"><x-quotations.icon name="receipt" class="size-4" />Convert to bill</button>
                                    </form>
                                </template>
                                <template x-if="current.quote_url">
                                    <a :href="current.quote_url" class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-ink px-3 text-sm font-semibold text-white hover:bg-zinc-800"><x-quotations.icon name="doc" class="size-4" />Make quotation</a>
                                </template>
                            </div>
                        </div>
                    </section>

                    {{-- Quote card --}}
                    <section class="rounded-xl border border-zinc-200 bg-white p-5">
                        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                            <h3 class="text-[17px] font-semibold">
                                <span x-text="current.number ? 'Quote ' + current.number : (current.kind_label + ' · ' + current.since)"></span>
                            </h3>
                            <span class="text-xs font-medium text-zinc-600" x-text="current.number ? ('Made ' + current.created_label + ' · ' + current.valid_label) : ''"></span>
                        </div>
                        <template x-if="current.lines.length">
                            <table class="w-full text-[13px]">
                                <thead class="text-left text-[11px] font-semibold uppercase tracking-[0.06em] text-zinc-600">
                                    <tr class="border-b border-zinc-200"><th class="pb-2">Item</th><th class="pb-2 text-center">Qty</th><th class="pb-2 text-right">MRP</th><th class="pb-2 text-right">Our price</th></tr>
                                </thead>
                                <tbody>
                                    <template x-for="l in current.lines">
                                        <tr class="border-b border-zinc-200">
                                            <td class="py-2.5 pr-2 font-medium" x-text="l.name"></td>
                                            <td class="py-2.5 text-center tabular-nums" x-text="l.qty"></td>
                                            <td class="py-2.5 text-right tabular-nums text-zinc-500"><span class="line-through" x-text="l.mrp || ''"></span><span x-show="!l.mrp">—</span></td>
                                            <td class="py-2.5 text-right font-semibold tabular-nums" x-text="l.total"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>
                        <p x-show="!current.lines.length" class="text-[15px] font-medium" x-text="current.asked"></p>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <span x-show="current.savings" class="rounded-full border border-green-200 bg-green-50 px-3 py-1 text-xs font-semibold text-green-700">
                                You save <span x-text="current.savings"></span> (<span x-text="current.savings_pct"></span>% off MRP)
                            </span>
                            <span x-show="!current.savings"></span>
                            <div class="flex items-baseline gap-3" x-show="current.value">
                                <span class="text-xs font-medium text-zinc-600"><span x-text="current.type === 'quotation' ? 'Net total incl. GST' : 'Price told'"></span></span>
                                <span class="text-2xl font-bold tabular-nums tracking-[-0.01em]" x-text="current.value"></span>
                            </div>
                        </div>
                    </section>

                    {{-- Message card --}}
                    <section class="rounded-xl border border-zinc-200 bg-white p-5">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="flex items-center gap-2 text-[15px] font-semibold"><x-quotations.icon name="chat" class="size-4" />WhatsApp template: <span x-text="current.template_label"></span></h3>
                            @can('settings.manage')<a href="{{ route('settings.edit') }}#followups" class="text-xs font-semibold underline underline-offset-2">Edit template</a>@endcan
                        </div>
                        <textarea x-model="msg" rows="4" class="block w-full resize-y rounded-lg border border-green-200 bg-green-50/40 px-3 py-2.5 text-[13px] leading-5 text-zinc-800 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink"></textarea>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <span class="text-xs text-zinc-600"><span x-show="msg !== current.message">Edited for this customer only. </span>Opens WhatsApp Web with this text.</span>
                            <a :href="waHref()" target="_blank" rel="noopener" @click="current.contacted = true"
                               class="inline-flex h-10 items-center gap-2 rounded-lg bg-green-700 px-4 text-sm font-semibold text-white hover:bg-green-800">Send this message via WhatsApp <kbd class="rounded border border-white/40 px-1 text-[11px]">W</kbd></a>
                        </div>
                    </section>

                    {{-- Log result --}}
                    <section class="rounded-xl border border-zinc-200 bg-white p-5" x-ref="logPanel">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-[17px] font-semibold">Log interaction result</h3>
                            <span class="text-xs text-zinc-600">Shortcut: press <kbd class="{{ $kbd }}">1</kbd>–<kbd class="{{ $kbd }}">7</kbd>, then <kbd class="{{ $kbd }}">Enter</kbd></span>
                        </div>
                        <p x-show="current.contacted" class="mb-3 text-xs font-medium text-zinc-600">Contacted. Now log what they said.</p>
                        @include('followups._log-controls')
                    </section>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== Log result bottom sheet (phone) ===== --}}
    <div x-show="sheet" x-cloak class="fixed inset-0 z-50 lg:hidden">
        <div class="absolute inset-0 bg-[rgba(17,17,17,.4)]" @click="sheet = false"></div>
        <div class="absolute inset-x-0 bottom-0 max-h-[92dvh] overflow-y-auto rounded-t-2xl bg-white px-4 pt-2 shadow-[0_-8px_24px_rgba(0,0,0,.08)]"
             x-show="sheet" x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             style="padding-bottom: max(1.5rem, env(safe-area-inset-bottom))">
            <div class="mx-auto mb-3 h-1 w-9 rounded-full bg-zinc-300"></div>
            <template x-if="current">
                <div>
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-[22px] font-bold leading-7">Log result</h2>
                            <p class="truncate text-sm font-medium text-zinc-600"><span x-text="current.name"></span> · <span x-text="current.asked"></span></p>
                        </div>
                        <button type="button" @click="sheet = false" class="grid size-11 place-items-center rounded-lg" aria-label="Close"><x-quotations.icon name="x" class="size-6" /></button>
                    </div>
                    @include('followups._log-controls')
                </div>
            </template>
        </div>
    </div>

    {{-- ===== Undo toast ===== --}}
    <div x-show="toast" x-cloak x-transition.opacity class="fixed inset-x-4 bottom-24 z-50 mx-auto flex max-w-md items-center justify-between gap-4 rounded-lg bg-ink px-4 py-3 text-sm font-medium text-white shadow-lg lg:bottom-6" role="status">
        <span x-text="toast?.message"></span>
        <button type="button" x-show="toast?.undo_url" @click="undo()" class="shrink-0 font-semibold text-brand-400">Undo</button>
    </div>
</div>

@include('followups._queue-js')
</x-layouts.app>
