{{--
    Customer profile. Wide PC (≥1280 px): two panes, the customer list on the left and the profile
    on the right. Phone: a single scrolling profile with Call / WhatsApp up top and tabs.
--}}
@php
    use App\Http\Controllers\Customers\CustomerController as CC;
    use App\Support\Phone;
    use App\Support\RegNo;
    $user = auth()->user();
    $canEdit = $user->can('update', $customer);
    $canArchive = $user->can('archive', $customer);
    $digits = ltrim((string) $customer->mobile, '+');
    $listParams = array_filter(request()->only('q', 'source', 'visit', 'sort'));
    $tab = in_array(request('tab'), ['history', 'cars', 'notes'], true) ? request('tab') : 'history';
    $history = collect(array_merge($bills ?? [], $quotations ?? [], $jobCards ?? []))->sortByDesc(fn ($r) => $r['at'] ?? strtotime($r['date'] ?? ''))->values();
    $historyOn = $bills !== null || $quotations !== null || ($jobCards ?? null) !== null;
@endphp
<x-layouts.app :title="$customer->name" wide>
<div class="mx-auto max-w-[1200px] xl:grid xl:max-w-none xl:grid-cols-[340px_minmax(0,1fr)] xl:gap-6">

    {{-- ============ List pane (wide PC only) ============ --}}
    <aside class="hidden xl:block" aria-label="Customer list">
        <div class="sticky top-[88px] flex max-h-[calc(100dvh-112px)] flex-col overflow-hidden rounded-xl border border-line bg-surface">
            <form method="GET" action="{{ route('customers.show', $customer) }}" class="border-b border-line p-3">
                <x-ui.search-input name="q" :value="$filters['q']" placeholder="Find a customer" />
                @foreach (\Illuminate\Support\Arr::except($listParams, 'q') as $k => $v) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endforeach
            </form>
            <div class="flex items-center justify-between px-4 py-2 text-xs font-semibold text-muted">
                <a href="{{ route('customers.index', $listParams) }}" class="inline-flex items-center gap-1 hover:text-ink"><x-icon name="chevron-left" class="size-3.5" /> All customers</a>
                <span>{{ $list->count() }}{{ $list->count() >= 40 ? '+' : '' }}</span>
            </div>
            <div class="min-h-0 flex-1 divide-y divide-line overflow-y-auto border-t border-line" x-data x-init="$el.querySelector('[aria-current=page]')?.scrollIntoView({ block: 'center' })">
                @forelse ($list as $c)
                    <x-ui.list-row :href="route('customers.show', ['customer' => $c] + $listParams)" :avatar="$c->name" :title="$c->name"
                                   :subtitle="CC::carLine($c) ?? Phone::pretty($c->mobile)" :active="$c->is($customer)" class="min-h-14 py-2.5">
                        <x-slot:trailing><x-ui.money :paise="$c->total_spent_paise" size="sm" class="text-ink-2" /></x-slot:trailing>
                    </x-ui.list-row>
                @empty
                    <x-ui.empty-state icon="search" text="No match." compact />
                @endforelse
            </div>
        </div>
    </aside>

    {{-- ============ Profile ============ --}}
    <div class="min-w-0">
        <a href="{{ route('customers.index', $listParams) }}" class="-ml-2 mb-2 inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-[15px] font-medium text-ink-2 hover:text-ink lg:min-h-8 lg:text-sm xl:hidden">
            <x-icon name="chevron-left" class="size-5 lg:size-4" /> Customers
        </a>

        @if ($customer->isArchived())
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-line-strong bg-sunken px-4 py-3">
                <x-icon name="archive" class="size-5 text-muted" />
                <p class="flex-1 text-sm font-medium text-ink-2">Archived {{ $customer->archived_at->timezone('Asia/Kolkata')->format('j M Y') }}. Hidden from lists and search; history is kept.</p>
                @if ($canArchive)
                    <form method="POST" action="{{ route('customers.restore', $customer) }}">@csrf <button class="btn-ghost min-h-10 lg:min-h-8"><x-icon name="restore" class="size-4" /> Bring back</button></form>
                @endif
            </div>
        @endif

        {{-- Header --}}
        <section class="rounded-xl border border-line bg-surface p-4 lg:p-5">
            <div class="flex items-start gap-4">
                <x-ui.avatar-initials :name="$customer->name" size="lg" />
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-[26px] leading-8 font-bold tracking-[-0.01em] lg:text-2xl">{{ $customer->name }}</h1>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[15px] text-muted lg:text-sm">
                        <a href="tel:{{ $customer->mobile }}" class="tnum font-semibold text-ink hover:underline">{{ Phone::pretty($customer->mobile) }}</a>
                        @if ($customer->area) <span class="inline-flex items-center gap-1"><x-icon name="pin" class="size-4" /> {{ $customer->area }}</span> @endif
                        <x-ui.status-badge tone="muted">{{ \App\Models\Customer::SOURCE_LABELS[$customer->source] ?? $customer->source }}</x-ui.status-badge>
                        @unless ($customer->whatsapp_opt_in) <x-ui.status-badge tone="warn">No WhatsApp</x-ui.status-badge> @endunless
                    </div>
                </div>
                <div class="hidden shrink-0 items-center gap-2 lg:flex">
                    @if ($canEdit) <a href="{{ route('customers.edit', $customer) }}" class="btn-ghost"><x-icon name="pencil" class="size-4" /> Edit</a> @endif
                    @if ($canArchive && ! $customer->isArchived())
                        <button type="button" x-data @click="$dispatch('sheet', 'archive')" class="btn-icon border border-line-strong" aria-label="More actions" title="Archive"><x-icon name="dots" class="size-5" /></button>
                    @endif
                </div>
            </div>

            {{-- Main actions --}}
            <div class="mt-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                <a href="tel:{{ $customer->mobile }}" class="btn-ghost"><x-icon name="phone" class="size-5 lg:size-4" /> Call</a>
                <a href="https://wa.me/{{ $digits }}" target="_blank" rel="noopener" class="btn-ghost"><x-icon name="whatsapp" class="size-5 lg:size-4" /> WhatsApp</a>
                @if ($billUrl)
                    <a href="{{ $billUrl }}" class="btn-accent col-span-2 sm:ml-auto"><x-icon name="receipt" class="size-5 lg:size-4" /> New bill for {{ \Illuminate\Support\Str::before($customer->name, ' ') }}</a>
                @elseif ($user->can('invoices.create') && ! $customer->isArchived())
                    <span class="btn col-span-2 cursor-default border border-line bg-sunken text-muted sm:ml-auto" aria-disabled="true"><x-icon name="receipt" class="size-5 lg:size-4" /> New bill <x-ui.status-badge status="soon" /></span>
                @endif
                @if ($canEdit)
                    <a href="{{ route('customers.edit', $customer) }}" class="btn-ghost lg:hidden"><x-icon name="pencil" class="size-5" /> Edit</a>
                @endif
                @if ($canArchive && ! $customer->isArchived())
                    <button type="button" x-data @click="$dispatch('sheet', 'archive')" class="btn-ghost lg:hidden"><x-icon name="archive" class="size-5" /> Archive</button>
                @endif
            </div>

            {{-- Numbers --}}
            <dl class="mt-5 grid grid-cols-2 gap-x-4 gap-y-4 border-t border-line pt-4 sm:grid-cols-4">
                <div>
                    <dt class="text-[13px] font-medium text-muted">Total spent</dt>
                    <dd class="mt-1"><x-ui.money :paise="$customer->total_spent_paise" size="lg" /></dd>
                </div>
                <div>
                    <dt class="text-[13px] font-medium text-muted">Last visit</dt>
                    <dd class="mt-1 text-[17px] leading-7 font-bold">{{ $customer->last_visit_at ? $customer->last_visit_at->timezone('Asia/Kolkata')->format('j M Y') : 'Not yet' }}</dd>
                    @if ($customer->last_visit_at) <dd class="text-xs font-medium text-muted">{{ $customer->last_visit_at->diffForHumans() }}</dd> @endif
                </div>
                <div>
                    <dt class="text-[13px] font-medium text-muted">Due</dt>
                    <dd class="mt-1">
                        @if ($duePaise > 0) <x-ui.money :paise="$duePaise" size="lg" tone="bad" />
                        @else <span class="text-[17px] leading-7 font-bold text-ok">No due</span> @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[13px] font-medium text-muted">Customer since</dt>
                    <dd class="mt-1 text-[17px] leading-7 font-bold">{{ $customer->created_at?->timezone('Asia/Kolkata')->format('M Y') }}</dd>
                    @if ($customer->creator) <dd class="truncate text-xs font-medium text-muted">Added by {{ $customer->creator->name }}</dd> @endif
                </div>
            </dl>
        </section>

        {{-- Tabs --}}
        <div x-data="{ tab: @js($tab) }" class="mt-5">
            <div class="flex items-center justify-between gap-3">
                <div class="inline-flex gap-1 rounded-lg bg-sunken p-1" role="tablist">
                    @foreach (['history' => 'History', 'cars' => 'Cars ('.$customer->cars->count().')', 'notes' => 'Notes'] as $key => $label)
                        <button type="button" role="tab" @click="tab = '{{ $key }}'; history.replaceState(null, '', '?tab={{ $key }}')" :aria-selected="(tab === '{{ $key }}').toString()"
                                :class="tab === '{{ $key }}' ? 'border-line bg-surface text-ink' : 'border-transparent text-muted hover:text-ink'"
                                class="inline-flex min-h-10 items-center rounded-md border px-4 text-[13px] font-semibold lg:min-h-8">{{ $label }}</button>
                    @endforeach
                </div>
                @if ($canEdit)
                    <button type="button" x-data x-show="tab === 'cars'" @click="$dispatch('sheet', 'add-car')" class="btn-ghost min-h-10 lg:min-h-8"><x-icon name="plus" class="size-4" /> Add car</button>
                @endif
            </div>

            {{-- History --}}
            <div x-show="tab === 'history'" role="tabpanel" class="mt-3">
                @if (! $historyOn)
                    <div class="rounded-xl border border-line bg-surface p-4">
                        @include('home.partials.soon', ['icon' => 'history', 'text' => 'Bills and quotations for '.\Illuminate\Support\Str::before($customer->name, ' ').' will list here once Billing and Quotations are switched on, newest first.'])
                    </div>
                @elseif ($history->isEmpty())
                    <div class="card"><x-ui.empty-state icon="receipt" text="No bills, quotations or job cards yet." compact /></div>
                @else
                    <div class="overflow-hidden rounded-xl border border-line bg-surface">
                        <table class="table-pc">
                            <thead><tr><th>Date</th><th>Type</th><th>Number</th><th>Status</th><th class="text-right">Amount</th></tr></thead>
                            <tbody>
                                @foreach ($history as $h)
                                    <tr @if ($h['url']) data-href="{{ $h['url'] }}" @endif>
                                        <td class="tnum whitespace-nowrap">{{ $h['date'] }}</td>
                                        <td>{{ $h['type'] }}</td>
                                        <td class="tnum font-semibold text-ink">
                                            @if ($h['url']) <a href="{{ $h['url'] }}" class="hover:underline">{{ $h['number'] }}</a> @else {{ $h['number'] }} @endif
                                        </td>
                                        <td>
                                            @if (! empty($h['status_label'])) <x-ui.status-badge :status="$h['status']">{{ $h['status_label'] }}</x-ui.status-badge>
                                            @elseif ($h['status']) <x-ui.status-badge :status="$h['status']" /> @endif
                                        </td>
                                        <td class="text-right">@if ($h['paise'] !== null) <x-ui.money :paise="$h['paise']" size="sm" class="text-ink" /> @else <span class="text-faint">—</span> @endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                @if (($hiddenHistory ?? 0) > 0)
                    <p class="mt-2 text-xs font-medium text-muted">{{ $hiddenHistory }} more {{ $hiddenHistory === 1 ? 'bill or quote' : 'bills or quotes' }} by other staff not shown.</p>
                @endif
            </div>

            {{-- Cars --}}
            <div x-show="tab === 'cars'" x-cloak role="tabpanel" class="mt-3">
                @if ($customer->cars->isEmpty())
                    <div class="card">
                        <x-ui.empty-state icon="car" text="No car on file yet. Add one so bills and offers can match the model.">
                            @if ($canEdit) <button type="button" x-data @click="$dispatch('sheet', 'add-car')" class="btn-ghost"><x-icon name="plus" class="size-5" /> Add car</button> @endif
                        </x-ui.empty-state>
                    </div>
                @else
                    <div class="divide-y divide-line overflow-hidden rounded-xl border border-line bg-surface">
                        @foreach ($customer->cars as $car)
                            <div class="flex min-h-16 items-center gap-3 px-4 py-3">
                                <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-sunken text-ink-2"><x-icon name="car" class="size-5" /></span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[15px] font-semibold lg:text-sm">{{ $car->label() }}</div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[13px] font-medium text-muted">
                                        @if ($car->reg_no) <span class="tnum rounded border border-line-strong bg-surface px-1.5 py-px text-xs font-semibold tracking-wide text-ink">{{ RegNo::pretty($car->reg_no) }}</span> @else <span>No number yet</span> @endif
                                        @if ($car->year) <span>{{ $car->year }}</span> @endif
                                        @if ($car->colour) <span>{{ $car->colour }}</span> @endif
                                    </div>
                                </div>
                                @if ($canEdit)
                                    <button type="button" x-data @click="$dispatch('sheet', 'edit-car-{{ $car->id }}')" class="btn-icon" aria-label="Edit {{ $car->label() }}"><x-icon name="pencil" class="size-5" /></button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Notes --}}
            <div x-show="tab === 'notes'" x-cloak role="tabpanel" class="mt-3">
                @if ($canEdit)
                    <form method="POST" action="{{ route('customers.notes', $customer) }}" class="card space-y-3">
                        @csrf @method('PUT')
                        <label for="notes" class="label">Notes for the team</label>
                        <textarea id="notes" name="notes" rows="5" maxlength="2000" class="input min-h-32 py-2.5" placeholder="Prefers evening calls. Fleet owner, 4 cars.">{{ old('notes', $customer->notes) }}</textarea>
                        @error('notes') <p class="error">{{ $message }}</p> @enderror
                        <div class="flex justify-end"><button class="btn-primary">Save note</button></div>
                    </form>
                @elseif ($customer->notes)
                    <div class="card whitespace-pre-line text-[15px] lg:text-sm">{{ $customer->notes }}</div>
                @else
                    <div class="card"><x-ui.empty-state icon="note" text="No notes." compact /></div>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($canEdit)
    @include('customers.partials.car-sheet', ['car' => null])
    @foreach ($customer->cars as $car)
        @include('customers.partials.car-sheet', ['car' => $car])
    @endforeach
@endif

@if ($canArchive && ! $customer->isArchived())
    <x-ui.bottom-sheet name="archive" :title="'Archive '.$customer->name.'?'" :form="['action' => route('customers.archive', $customer), 'method' => 'POST']">
        <p class="text-[15px] leading-[22px] text-ink-2 lg:text-sm">They’ll be hidden from the customer list and search. Bills, cars and history stay. Typing their number on a new bill brings them back.</p>
        <x-slot:footer>
            <div class="flex gap-3">
                <button type="button" @click="$dispatch('sheet-close')" class="btn-ghost flex-1">Keep</button>
                <button class="btn-danger flex-1"><x-icon name="archive" class="size-5" /> Archive</button>
            </div>
        </x-slot:footer>
    </x-ui.bottom-sheet>
@endif
</x-layouts.app>
