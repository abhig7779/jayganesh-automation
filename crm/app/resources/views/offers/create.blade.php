@php
    use App\Models\Customer;
    use App\Services\Offers\SegmentBuilder;
    $f = fn (string $k, $default = []) => old("filters.$k", $filters[$k] ?? $default);
    $makesSel = (array) $f('makes');
    $modelsSel = (array) $f('models');
@endphp
<x-layouts.app title="New offer">
    <x-slot:header>
        <div>
            <a href="{{ route('offers.index') }}" class="text-sm font-medium text-zinc-600 hover:text-ink">← Offers</a>
            <h1 class="mt-1 text-2xl font-bold">New offer</h1>
            <p class="text-sm text-zinc-600">Pick who gets it, write the message, then choose how to send. Customers who opted out never get offers.</p>
        </div>
    </x-slot:header>

    <form method="POST" action="{{ route('offers.store') }}" id="offer-form"
          x-data="{ step: {{ $errors->hasAny(['title', 'message']) ? 2 : ($errors->hasAny(['method', 'template_name', 'confirm_cost']) ? 3 : max(1, min(3, request()->integer('step', 1)))) }}, method: '{{ old('method', 'broadcast') }}', message: @js(old('message', $defaultMessage)) }">
        @csrf
        @if ($segment) <input type="hidden" name="segment_id" value="{{ $segment->id }}"> @endif

        {{-- Step tabs --}}
        <ol class="mb-5 grid grid-cols-3 overflow-hidden rounded-xl border border-zinc-200 bg-white text-sm">
            @foreach ([1 => 'Who gets it', 2 => 'Message', 3 => 'How to send'] as $n => $label)
                <li>
                    <button type="button" @click="step = {{ $n }}"
                            class="flex w-full items-center gap-2 border-b-[3px] px-2 py-3 text-left text-xs font-semibold sm:gap-3 sm:px-4 sm:text-sm"
                            :class="step === {{ $n }} ? 'border-brand-400 text-ink' : 'border-transparent text-zinc-600 hover:text-ink'">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full border text-xs"
                              :class="step === {{ $n }} ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white'">{{ $n }}</span>
                        {{ $label }}
                    </button>
                </li>
            @endforeach
        </ol>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0 space-y-5">

                {{-- STEP 1: audience --}}
                <section x-show="step === 1" id="audience" class="space-y-4"
                         hx-get="{{ route('offers.count') }}" hx-trigger="change, input delay:400ms" hx-include="#audience" hx-target="#count-box" hx-sync="this:replace">

                    @if ($saved->isNotEmpty())
                        <div class="card flex flex-wrap items-center gap-2">
                            <span class="mr-1 text-sm font-semibold">Start from a saved list:</span>
                            @foreach ($saved as $s)
                                <a href="{{ route('offers.create', ['segment' => $s->id]) }}" @class(['badge border px-3 py-1 text-sm', 'border-ink bg-ink text-white' => $segment?->id === $s->id, 'border-zinc-300 bg-white text-ink hover:border-ink' => $segment?->id !== $s->id])>{{ $s->name }}</a>
                            @endforeach
                        </div>
                    @endif

                    <div class="card">
                        <h2 class="text-base font-semibold">Car</h2>
                        <p class="text-sm text-zinc-600">Tick a make for all its models, or open it and tick only some models.</p>
                        @if (empty($options['makes']))
                            <p class="mt-3 text-sm text-zinc-600">No cars saved on customers yet.</p>
                        @else
                            <div class="mt-3 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                @foreach ($options['makes'] as $make => $n)
                                    <details class="rounded-lg border border-zinc-200 bg-white" @if (in_array($make, $makesSel) || array_intersect(array_keys($options['models'][$make] ?? []), $modelsSel)) open @endif>
                                        <summary class="flex cursor-pointer list-none items-center gap-2 px-3 py-2">
                                            <input type="checkbox" name="filters[makes][]" value="{{ $make }}" @checked(in_array($make, $makesSel)) class="size-4 accent-ink" @click.stop>
                                            <span class="flex-1 text-sm font-semibold">{{ $make }}</span>
                                            <span class="text-xs tabular-nums text-zinc-600">{{ $n }}</span>
                                            <span class="text-xs text-zinc-500">▾</span>
                                        </summary>
                                        <div class="grid grid-cols-1 gap-1 border-t border-zinc-100 px-3 py-2">
                                            @foreach ($options['models'][$make] ?? [] as $model => $m)
                                                <label class="flex items-center gap-2 text-sm">
                                                    <input type="checkbox" name="filters[models][]" value="{{ $model }}" @checked(in_array($model, $modelsSel)) class="size-4 accent-ink">
                                                    <span class="flex-1">{{ $model }}</span><span class="text-xs tabular-nums text-zinc-600">{{ $m }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="card grid gap-5 md:grid-cols-2">
                        <div>
                            <h2 class="text-base font-semibold">Last visit</h2>
                            @php $visit = old('filters.visit', $filters['visit'] ?? 'any'); @endphp
                            <div class="mt-2 space-y-2" x-data="{ visit: '{{ $visit }}' }">
                                @foreach (SegmentBuilder::VISIT_MODES as $mode => $label)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="radio" name="filters[visit]" value="{{ $mode }}" x-model="visit" @checked($visit === $mode) class="size-4 accent-ink">
                                        {{ $label }}
                                    </label>
                                    @if ($mode === 'not_in_days')
                                        <div x-show="visit === 'not_in_days'" x-cloak class="ml-6 flex items-center gap-2">
                                            <input type="number" min="1" max="3650" name="filters[visit_days]" value="{{ old('filters.visit_days', $filters['visit_days'] ?? 90) }}" class="input w-28" aria-label="Days">
                                            <span class="text-sm text-zinc-600">days</span>
                                            @foreach ([90, 180, 365] as $d)
                                                <button type="button" class="rounded-md border border-zinc-300 px-2 py-1 text-xs font-semibold hover:border-ink"
                                                        @click="$el.parentElement.querySelector('input').value = {{ $d }}; $el.parentElement.querySelector('input').dispatchEvent(new Event('change', { bubbles: true }))">{{ $d === 365 ? '1 year' : ($d / 30).' months' }}</button>
                                            @endforeach
                                        </div>
                                    @endif
                                @endforeach
                                <div x-show="visit === 'before' || visit === 'after'" x-cloak class="ml-6">
                                    <input type="date" name="filters[visit_date]" value="{{ old('filters.visit_date', $filters['visit_date'] ?? '') }}" class="input w-48" aria-label="Date">
                                </div>
                            </div>
                        </div>
                        <div class="space-y-5">
                            <div>
                                <label class="text-base font-semibold" for="min_spent">Total spent at the shop</label>
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="text-sm text-zinc-700">at least</span>
                                    <div class="relative w-40">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-zinc-600">₹</span>
                                        <input id="min_spent" inputmode="numeric" name="filters[min_spent]" value="{{ old('filters.min_spent', $filters['min_spent'] ?? '') }}" class="input pl-7 text-right tabular-nums" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="text-base font-semibold">Customer added between</div>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <label class="text-xs font-medium text-zinc-600">From
                                        <input type="date" name="filters[added_from]" value="{{ old('filters.added_from', $filters['added_from'] ?? '') }}" class="input mt-1">
                                    </label>
                                    <label class="text-xs font-medium text-zinc-600">To
                                        <input type="date" name="filters[added_to]" value="{{ old('filters.added_to', $filters['added_to'] ?? '') }}" class="input mt-1">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card grid gap-5 md:grid-cols-2">
                        <div>
                            <h2 class="text-base font-semibold">Area</h2>
                            @if (empty($options['areas']))
                                <p class="mt-2 text-sm text-zinc-600">No areas saved on customers yet.</p>
                            @else
                                <div class="mt-2 max-h-56 space-y-1 overflow-y-auto pr-1">
                                    @foreach ($options['areas'] as $area => $n)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" name="filters[areas][]" value="{{ $area }}" @checked(in_array($area, (array) $f('areas'))) class="size-4 accent-ink">
                                            <span class="flex-1">{{ $area }}</span><span class="text-xs tabular-nums text-zinc-600">{{ $n }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div class="space-y-5">
                            <div>
                                <h2 class="text-base font-semibold">Came to us from</h2>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($options['sources'] as $key => $label)
                                        <label class="cursor-pointer">
                                            <input type="checkbox" name="filters[sources][]" value="{{ $key }}" @checked(in_array($key, (array) $f('sources'))) class="peer sr-only">
                                            <span class="inline-flex min-h-9 items-center rounded-full border border-zinc-300 bg-white px-3 text-sm font-medium peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-400">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <h2 class="text-base font-semibold">Tags</h2>
                                @if (empty($options['tags']))
                                    <p class="mt-2 text-sm text-zinc-600">No tags on customers yet.</p>
                                @else
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($options['tags'] as $tag => $n)
                                            <label class="cursor-pointer">
                                                <input type="checkbox" name="filters[tags][]" value="{{ $tag }}" @checked(in_array($tag, (array) $f('tags'))) class="peer sr-only">
                                                <span class="inline-flex min-h-9 items-center gap-1 rounded-full border border-zinc-300 bg-white px-3 text-sm font-medium peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-400">{{ $tag }} <span class="text-xs opacity-70">{{ $n }}</span></span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($purchases)
                        <div class="card grid gap-5 md:grid-cols-2" data-filter="bought">
                            <div>
                                <label class="text-base font-semibold" for="bought_products">Bought a product</label>
                                <p class="text-sm text-zinc-600">Hold Ctrl to pick more than one.</p>
                                <select id="bought_products" name="filters[bought_products][]" multiple size="6" class="input mt-2 py-2">
                                    @foreach ($options['products'] as $id => $name)
                                        <option value="{{ $id }}" @selected(in_array($id, (array) $f('bought_products')))>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-base font-semibold" for="bought_categories">…or anything from a category</label>
                                <p class="text-sm text-zinc-600">For example all PPF or all seat covers.</p>
                                <select id="bought_categories" name="filters[bought_categories][]" multiple size="6" class="input mt-2 py-2">
                                    @foreach ($options['categories'] as $id => $name)
                                        <option value="{{ $id }}" @selected(in_array($id, (array) $f('bought_categories')))>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <div class="card flex flex-wrap items-center gap-3" x-data>
                        <label for="segment_name" class="text-sm font-semibold">Save these filters for next time</label>
                        <input id="segment_name" name="segment_name" class="input w-64" placeholder="e.g. Creta owners, 6 months" value="{{ $segment?->name }}">
                        <button type="button" class="btn-ghost" hx-post="{{ route('segments.store') }}" hx-include="#audience" hx-target="#segment-saved" hx-trigger="click" @click.stop>Save list</button>
                        <span id="segment-saved"></span>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" class="btn-accent px-6" @click="step = 2; window.scrollTo({ top: 0 })">Next: Message →</button>
                    </div>
                </section>

                {{-- STEP 2: message --}}
                <section x-show="step === 2" x-cloak class="card space-y-4">
                    <div>
                        <label class="label" for="title">Offer name (only for you, customers don't see it)</label>
                        <input id="title" name="title" value="{{ old('title', $copy ? $copy->title.' (copy)' : '') }}" class="input max-w-lg" placeholder="e.g. Diwali LED headlight offer">
                        @error('title') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-5 xl:grid-cols-2">
                        <div>
                            <div class="flex items-end justify-between gap-2">
                                <label class="label" for="message">Message</label>
                                <div class="mb-1 flex gap-1">
                                    @foreach (['{name}' => 'Name', '{car}' => 'Car'] as $ph => $label)
                                        <button type="button" class="rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs font-semibold hover:border-ink"
                                                @click="const t = $refs.msg; const s = t.selectionStart ?? message.length; message = message.slice(0, s) + '{{ $ph }}' + message.slice(t.selectionEnd ?? s); $nextTick(() => { t.focus(); t.dispatchEvent(new Event('input', { bubbles: true })) })">+ {{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <textarea id="message" name="message" x-ref="msg" x-model="message" rows="11" maxlength="1000" class="input py-2 leading-relaxed"
                                      hx-post="{{ route('offers.preview') }}" hx-trigger="input delay:300ms, load" hx-include="#audience, #message" hx-target="#preview-box"></textarea>
                            <div class="mt-1 flex justify-between text-xs text-zinc-600">
                                <span>{name} becomes the first name, {car} the car model. Write in English.</span>
                                <span class="shrink-0 whitespace-nowrap tabular-nums" :class="message.length > 1000 ? 'font-semibold text-red-600' : ''"><span x-text="message.length"></span> characters</span>
                            </div>
                            @error('message') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div id="preview-box" class="min-w-0">
                            <div class="text-sm text-zinc-600">Loading preview…</div>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <button type="button" class="btn-ghost" @click="step = 1">← Who gets it</button>
                        <button type="button" class="btn-accent px-6" @click="step = 3; window.scrollTo({ top: 0 })">Next: How to send →</button>
                    </div>
                </section>

                {{-- STEP 3: send method --}}
                <section x-show="step === 3" x-cloak class="space-y-4">
                    @include('offers.partials.methods', ['count' => $count['eligible']])
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <button type="button" class="btn-ghost" @click="step = 2">← Message</button>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" name="save_draft" value="1" class="btn-ghost" formnovalidate>Save as draft</button>
                            <button type="submit" class="btn-accent px-6">
                                <span x-show="method === 'broadcast'">Make broadcast list</span>
                                <span x-show="method === 'assisted'" x-cloak>Start sending one by one</span>
                                <span x-show="method === 'automatic'" x-cloak>Send offer</span>
                            </button>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Live audience summary --}}
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <div class="card" id="count-box" aria-live="polite" hx-get="{{ route('offers.count') }}" hx-trigger="load" hx-include="#audience">
                    @include('offers.partials.count', ['count' => $count, 'summary' => app(SegmentBuilder::class)->describe($filters), 'sample' => null])
                </div>
                <p class="mt-3 px-1 text-xs text-zinc-600">The list is fixed when you press the last button. Customers who reply STOP are marked opted out on their profile and are left out of every offer.</p>
            </aside>
        </div>
    </form>
</x-layouts.app>
