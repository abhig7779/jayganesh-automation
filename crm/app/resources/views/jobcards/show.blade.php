<x-layouts.app :title="$card->number">
    @php
        /** @var \App\Models\JobCard $card */
        $moveLabels = [
            'working' => $card->status === 'waiting' ? 'Start work' : 'Send back to work',
            'waiting' => $card->status === 'cancelled' ? 'Reopen job' : 'Pause (back to waiting)',
            'qc' => 'Ready for QC',
            'ready' => 'Passed QC · Ready for pickup',
            'delivered' => 'Hand over · Delivered',
        ];
        $primaryMove = $moves->first(fn ($m) => in_array($m, ['working', 'qc', 'ready', 'delivered'], true) && ! ($m === 'working' && $card->status !== 'waiting'));
        $before = $card->photos->where('stage', 'before');
        $after = $card->photos->where('stage', 'after');
        $other = $card->photos->where('stage', 'other');
        $done = $card->items->where('done', true)->count();
        $section = 'text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600';
        $panel = 'rounded-xl border border-zinc-200 bg-white';
        $btn2 = 'inline-flex h-12 lg:h-10 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold text-ink hover:bg-zinc-50';
        $btn1 = 'inline-flex h-12 lg:h-10 items-center justify-center gap-2 rounded-lg bg-[#FFD51C] px-4 text-sm font-semibold text-ink hover:bg-[#F2C800]';
    @endphp

    <x-slot:header>
        <div class="min-w-0">
            <a href="{{ $isTechnician ? route('jobcards.mine') : route('jobcards.index') }}" class="mb-1 inline-flex items-center gap-1 text-sm font-medium text-zinc-600 hover:text-ink">
                <x-jobcards.icon name="back" class="size-4" /> {{ $isTechnician ? 'My jobs' : 'Job board' }}
            </a>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-[28px] font-bold leading-tight tracking-tight tabular-nums">{{ $card->plate() ?? 'No plate' }}</h1>
                <x-jobcards.status :status="$card->status" size="md" />
                @if ($card->isLate())
                    <span class="inline-flex items-center gap-1 text-sm font-semibold text-red-600"><x-jobcards.icon name="clock" class="size-4" />{{ $card->promiseText() }}</span>
                @endif
            </div>
            <p class="text-[15px] font-medium text-zinc-600">
                {{ $card->carLabel(true) }}@if ($card->car?->colour) · {{ $card->car->colour }}@endif · {{ $card->customer?->name }}
                @unless ($isTechnician)
                    · <a href="tel:{{ $card->customer?->mobile }}" class="tabular-nums underline-offset-2 hover:underline">{{ \App\Support\Phone::pretty($card->customer?->mobile) }}</a>
                @endunless
                · <span class="tabular-nums">{{ $card->number }}</span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('jobcards.print', $card) }}" target="_blank" class="{{ $btn2 }}"><x-jobcards.icon name="printer" /> Print job sheet</a>
        </div>
    </x-slot:header>

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    {{-- Status actions --}}
    @if ($moves->isNotEmpty() || $whatsAppLink || in_array($card->status, ['ready', 'delivered'], true))
        <div class="{{ $panel }} mb-4 flex flex-wrap items-center gap-2 p-3">
            @foreach ($moves->reject(fn ($m) => $m === 'cancelled') as $to)
                <form method="POST" action="{{ route('jobcards.status', $card) }}">
                    @csrf <input type="hidden" name="status" value="{{ $to }}">
                    <button class="{{ $to === $primaryMove ? $btn1 : $btn2 }}">{{ $moveLabels[$to] ?? ucfirst($to) }}</button>
                </form>
            @endforeach

            @if ($whatsAppLink)
                <form method="POST" action="{{ $whatsAppLink }}" target="_blank">
                    @csrf
                    <button class="{{ $btn2 }}"><x-jobcards.icon name="chat" /> Send ready on WhatsApp</button>
                </form>
            @endif

            @if (! $isTechnician && in_array($card->status, ['ready', 'delivered'], true))
                @if ($card->invoice_id)
                    <span class="inline-flex h-10 items-center gap-2 rounded-lg bg-green-50 px-3 text-sm font-semibold text-green-700"><x-jobcards.icon name="receipt" /> Bill made</span>
                @elseif ($canBill && $billingReady)
                    <form method="POST" action="{{ route('jobcards.bill', $card) }}">
                        @csrf
                        <button class="{{ $card->status === 'delivered' ? $btn1 : $btn2 }}"><x-jobcards.icon name="receipt" /> Make bill</button>
                    </form>
                @elseif ($canBill)
                    <span class="inline-flex h-10 items-center gap-2 rounded-lg bg-zinc-100 px-3 text-sm font-medium text-zinc-600"><x-jobcards.icon name="receipt" /> Billing coming soon</span>
                @endif
            @endif

            @if ($moves->contains('cancelled'))
                <details class="ml-auto">
                    <summary class="inline-flex h-10 cursor-pointer list-none items-center rounded-lg px-3 text-sm font-semibold text-red-600 hover:bg-red-50">Cancel job…</summary>
                    <form method="POST" action="{{ route('jobcards.status', $card) }}" class="mt-2 flex gap-2">
                        @csrf <input type="hidden" name="status" value="cancelled">
                        <input name="note" required maxlength="200" placeholder="Why? e.g. customer changed mind" class="h-10 w-64 rounded-lg border border-zinc-300 px-3 text-sm">
                        <button class="h-10 rounded-lg border border-red-300 bg-white px-3 text-sm font-semibold text-red-600">Cancel job</button>
                    </form>
                </details>
            @endif
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]" data-jobcards="detail">
        <div class="space-y-4">
            {{-- Work items --}}
            <section class="{{ $panel }} p-4" data-job>
                <div class="mb-1 flex items-center justify-between">
                    <h2 class="{{ $section }}">Work</h2>
                    <span class="text-xs font-semibold tabular-nums text-zinc-600"><span data-progress>{{ $done }}/{{ $card->items->count() }}</span> done</span>
                </div>
                <div class="-mx-2 divide-y divide-zinc-100">
                    @forelse ($card->items as $item)
                        <div class="flex items-center gap-2">
                            <div class="flex-1">@include('jobcards._tick', ['card' => $card, 'item' => $item, 'canTick' => $canWork])</div>
                            @if ($seePrices)
                                <div class="w-28 pr-2 text-right text-sm font-semibold tabular-nums">{{ \App\Support\Money::format($item->qty * $item->price_paise) }}</div>
                            @endif
                        </div>
                        @if ($item->done && $item->doneBy)
                            <p class="-mt-2 pb-2 pl-12 text-xs text-zinc-500">Done by {{ $item->doneBy->name }} · {{ $item->done_at?->timezone('Asia/Kolkata')->format('j M, g:i a') }}</p>
                        @endif
                    @empty
                        <p class="px-2 py-6 text-center text-sm text-zinc-500">No work items yet.@if ($canUpdate) Add the first one below.@endif</p>
                    @endforelse
                </div>
                @if ($seePrices && $card->items->isNotEmpty())
                    <div class="mt-2 flex items-center justify-between border-t border-zinc-200 pt-3">
                        <span class="text-sm text-zinc-600">Estimate (incl. GST)</span>
                        <span class="text-lg font-bold tabular-nums">{{ \App\Support\Money::format($card->totalPaise()) }}</span>
                    </div>
                @endif

                @if ($canUpdate)
                    <form method="POST" action="{{ route('jobcards.items.store', $card) }}" class="mt-3 grid grid-cols-[minmax(0,1fr)_72px_112px_auto] gap-2 border-t border-zinc-200 pt-3"
                          x-data="{ q: '', results: [], open: false, productId: '', price: '',
                                    async search() { if (this.q.length < 2) { this.results = []; return; }
                                        const r = await fetch('{{ route('jobcards.products') }}?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
                                        this.results = r.ok ? (await r.json()).data : []; this.open = true; },
                                    pick(p) { this.q = p.name; this.productId = p.id; this.price = p.price; this.open = false; } }">
                        @csrf
                        <div class="relative">
                            <label for="add-desc" class="sr-only">Work or product</label>
                            <input id="add-desc" name="description" x-model="q" @input.debounce.250ms="productId = ''; search()" @keydown.escape="open = false"
                                   autocomplete="off" placeholder="Add work: product or free text" class="h-10 w-full rounded-lg border border-zinc-300 px-3 text-sm">
                            <input type="hidden" name="product_id" :value="productId">
                            <ul x-cloak x-show="open && results.length" @click.outside="open = false" class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                                <template x-for="p in results" :key="p.id">
                                    <li><button type="button" @click="pick(p)" class="flex w-full justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50"><span x-text="p.name"></span><span class="tabular-nums text-zinc-600" x-text="p.price_label"></span></button></li>
                                </template>
                            </ul>
                        </div>
                        <input name="qty" type="number" min="1" value="1" inputmode="numeric" aria-label="Qty" class="h-10 rounded-lg border border-zinc-300 px-2 text-sm tabular-nums">
                        <input name="price" x-model="price" inputmode="decimal" placeholder="₹ price" aria-label="Price in rupees" class="h-10 rounded-lg border border-zinc-300 px-2 text-right text-sm tabular-nums">
                        <button class="{{ $btn2 }}"><x-jobcards.icon name="plus" class="size-4" /> Add</button>
                    </form>
                @endif
            </section>

            {{-- Photos --}}
            <section class="{{ $panel }} p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="{{ $section }}">Photos</h2>
                    @if ($canWork)
                        <form method="POST" action="{{ route('jobcards.photos.store', $card) }}" enctype="multipart/form-data" data-photo-upload class="flex flex-wrap items-center gap-2">
                            @csrf
                            <div class="inline-flex rounded-lg bg-zinc-100 p-1 text-sm" role="radiogroup" aria-label="Photo type">
                                @foreach (['before' => 'Before', 'after' => 'After', 'other' => 'Other'] as $v => $l)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="stage" value="{{ $v }}" class="peer sr-only" @checked($v === ($before->isEmpty() || $card->status === 'waiting' ? 'before' : 'after'))>
                                        <span class="inline-flex h-11 items-center rounded-md px-4 font-medium text-zinc-600 lg:h-8 lg:px-3 peer-checked:bg-white peer-checked:text-ink peer-checked:shadow-[0_0_0_1px_#E4E4E7] peer-focus-visible:outline-2">{{ $l }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <label class="{{ $btn2 }} cursor-pointer">
                                <x-jobcards.icon name="camera" /> Add photo
                                <input type="file" name="photos[]" accept="image/*" capture="environment" multiple class="sr-only">
                            </label>
                            <button data-upload-submit class="{{ $btn2 }}">Upload</button>
                            <p data-upload-status hidden role="status" class="w-full text-sm font-medium text-zinc-700"></p>
                        </form>
                    @endif
                </div>
                @foreach (['Before' => $before, 'After' => $after, 'Other' => $other] as $label => $set)
                    @if ($set->isNotEmpty() || $label !== 'Other')
                        <h3 class="mb-2 mt-3 text-sm font-semibold text-ink first:mt-0">{{ $label }} <span class="font-medium text-zinc-500">· {{ $set->count() }}</span></h3>
                        @if ($set->isEmpty())
                            <p class="rounded-lg border border-dashed border-zinc-300 px-3 py-4 text-center text-sm text-zinc-500">
                                {{ $label === 'Before' ? 'No before photos. Take a few of every side before work starts.' : 'No after photos yet. Add them when the work is done.' }}
                            </p>
                        @else
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-5">
                                @foreach ($set as $photo)
                                    <a href="{{ $photo->url() }}" target="_blank" class="block aspect-[4/3] overflow-hidden rounded-lg bg-zinc-100">
                                        <img src="{{ $photo->url(true) }}" alt="{{ $label }} photo {{ $loop->iteration }}" loading="lazy" class="h-full w-full object-cover">
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @endif
                @endforeach
            </section>
        </div>

        <aside class="space-y-4">
            {{-- Details + assignment --}}
            <section class="{{ $panel }} p-4">
                <h2 class="{{ $section }} mb-3">Details</h2>
                @if ($canUpdate)
                    <form method="POST" action="{{ route('jobcards.assign', $card) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="technician_id" class="mb-1 block text-sm font-medium text-zinc-700">Technician</label>
                            <select id="technician_id" name="technician_id" class="h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm">
                                <option value="">Not assigned</option>
                                @foreach ($technicians as $t)
                                    <option value="{{ $t->id }}" @selected($card->technician_id === $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-[minmax(0,1fr)_80px] gap-2">
                            <div>
                                <label for="promised_at" class="mb-1 block text-sm font-medium text-zinc-700">Promised</label>
                                <input id="promised_at" type="datetime-local" name="promised_at" value="{{ $card->promised_at?->timezone('Asia/Kolkata')->format('Y-m-d\TH:i') }}" class="h-10 w-full min-w-0 rounded-lg border border-zinc-300 px-2 text-sm">
                            </div>
                            <div>
                                <label for="bay" class="mb-1 block text-sm font-medium text-zinc-700">Bay</label>
                                <input id="bay" name="bay" value="{{ $card->bay }}" maxlength="20" class="h-10 w-full min-w-0 rounded-lg border border-zinc-300 px-2 text-sm">
                            </div>
                        </div>
                        <button class="{{ $btn2 }} w-full">Save details</button>
                    </form>
                    <hr class="my-4 border-zinc-200">
                @endif
                <dl class="grid grid-cols-[110px_1fr] gap-y-2 text-sm">
                    @unless ($canUpdate)
                        <dt class="text-zinc-600">Technician</dt><dd class="font-medium">{{ $card->technician?->name ?? 'Not assigned' }}</dd>
                        <dt class="text-zinc-600">Promised</dt><dd class="font-medium">{{ $card->promiseText() ?? '—' }}</dd>
                    @endunless
                    <dt class="text-zinc-600">Odometer</dt><dd class="font-medium tabular-nums">{{ $card->odometer ? number_format($card->odometer).' km' : '—' }}</dd>
                    <dt class="text-zinc-600">Fuel</dt><dd class="font-medium">{{ $card->fuel_level ?? '—' }}</dd>
                    <dt class="text-zinc-600">Car in</dt><dd class="font-medium">{{ $card->created_at->timezone('Asia/Kolkata')->format('j M, g:i a') }} · {{ $card->creator?->name }}</dd>
                    @if ($card->notes)
                        <dt class="text-zinc-600">Notes</dt><dd class="whitespace-pre-line">{{ $card->notes }}</dd>
                    @endif
                </dl>
            </section>

            {{-- Timeline --}}
            <section class="{{ $panel }} p-4">
                <h2 class="{{ $section }} mb-3">Timeline</h2>
                <ol class="relative space-y-3 border-l border-zinc-200 pl-4">
                    @foreach ($card->events->reverse() as $e)
                        <li class="relative">
                            <span @class(['absolute -left-[21px] top-1.5 size-2.5 rounded-full ring-2 ring-white', 'bg-ink' => $e->type === 'status' || $e->type === 'created', 'bg-zinc-300' => ! in_array($e->type, ['status', 'created'], true)])></span>
                            <p class="text-sm font-medium text-ink">{{ $e->summary() }}</p>
                            <p class="text-xs text-zinc-500">{{ $e->created_at->timezone('Asia/Kolkata')->format('j M, g:i a') }}@if ($e->user) · {{ $e->user->name }}@endif</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
</x-layouts.app>
