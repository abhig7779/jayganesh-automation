{{-- Customers list: PC table with filters; phone list rows with a filter sheet. Live search via htmx. --}}
@php
    use App\Http\Controllers\Customers\CustomerController as CC;
    use App\Models\Customer;
    $canManage = auth()->user()->can('create', Customer::class);
    $without = fn (string ...$keys) => route('customers.index', array_filter(\Illuminate\Support\Arr::except(request()->query(), [...$keys, 'page'])));
    $chips = array_filter([
        $filters['source'] ? ['Source: '.Customer::SOURCE_LABELS[$filters['source']], $without('source')] : null,
        $filters['visit'] ? ['Last visit: '.CC::VISIT_FILTERS[$filters['visit']], $without('visit')] : null,
        $filters['added'] === 'today' ? ['Added today', $without('added')] : null,
        $filters['archived'] ? ['Archived', $without('archived')] : null,
    ]);
@endphp
<x-layouts.app title="Customers">
    <x-ui.page-header title="Customers" :subtitle="number_format($total).' on file'">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('customers.create') }}" class="btn-primary hidden lg:inline-flex" data-fkey="F7"><x-icon name="user-plus" class="size-5" /> Add customer <span class="kbd border-white/20 bg-transparent text-white/70">F7</span></a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" action="{{ route('customers.index') }}" id="customer-filters"
          hx-get="{{ route('customers.index') }}" hx-trigger="input delay:250ms from:#customer-q, change from:select"
          hx-select="#customer-results" hx-target="#customer-results" hx-swap="outerHTML" hx-push-url="true"
          class="sticky top-[calc(env(safe-area-inset-top)+57px)] z-20 -mx-4 mb-3 bg-canvas px-4 py-2 lg:static lg:mx-0 lg:mb-4 lg:p-0">
        <div class="flex flex-wrap items-center gap-2 lg:gap-3">
            <x-ui.search-input id="customer-q" name="q" :value="$filters['q']" placeholder="Name, mobile or car number" class="min-w-0 flex-1 lg:max-w-md" />
            <div class="hidden items-center gap-2 lg:flex">
                <select name="source" class="input w-auto pr-8" aria-label="Source">
                    <option value="">All sources</option>
                    @foreach (Customer::SOURCE_LABELS as $v => $l) <option value="{{ $v }}" @selected($filters['source'] === $v)>{{ $l }}</option> @endforeach
                </select>
                <select name="visit" class="input w-auto pr-8" aria-label="Last visit">
                    @foreach (CC::VISIT_FILTERS as $v => $l) <option value="{{ $v }}" @selected($filters['visit'] === (string) $v)>{{ $v === '' ? 'Last visit: any' : $l }}</option> @endforeach
                </select>
                <select name="sort" class="input w-auto pr-8" aria-label="Sort">
                    @foreach (CC::SORTS as $v => $l) <option value="{{ $v }}" @selected($filters['sort'] === $v)>{{ $l }}</option> @endforeach
                </select>
                @if ($filters['added']) <input type="hidden" name="added" value="{{ $filters['added'] }}"> @endif
                @if ($filters['archived']) <input type="hidden" name="archived" value="1"> @endif
            </div>
            <button type="button" x-data @click="$dispatch('sheet', 'customer-filters')" class="btn-ghost relative w-12 px-0 lg:hidden" aria-label="Filters">
                <x-icon name="filter" class="size-5" />
                @if (count($filters['active'])) <span class="absolute -top-1 -right-1 inline-flex size-5 items-center justify-center rounded-full bg-ink text-[11px] font-bold text-white">{{ count($filters['active']) }}</span> @endif
            </button>
            <noscript><button class="btn-ghost">Search</button></noscript>
        </div>
    </form>

    <div id="customer-results">
        @if ($chips)
            <div class="mb-3 flex flex-wrap items-center gap-2">
                @foreach ($chips as [$text, $url])
                    <a href="{{ $url }}" class="inline-flex min-h-9 items-center gap-1.5 rounded-full border border-line-strong bg-surface pr-2 pl-3 text-[13px] font-semibold hover:bg-sunken lg:min-h-8">
                        {{ $text }} <x-icon name="x" class="size-4 text-muted" /><span class="sr-only">Remove filter</span>
                    </a>
                @endforeach
                <a href="{{ route('customers.index', array_filter(['q' => $filters['q']])) }}" class="text-[13px] font-semibold text-muted hover:text-ink">Clear all</a>
            </div>
        @endif

        @if ($customers->isEmpty())
            <div class="card">
                @if ($filters['q'] !== '' || $filters['active'])
                    <x-ui.empty-state icon="search" text="No customer matches “{{ $filters['q'] ?: 'these filters' }}”. Check the number, or add them as new.">
                        @if ($canManage)
                            @php $digits = preg_replace('/\D+/', '', $filters['q']); @endphp
                            <a href="{{ route('customers.create', strlen($digits) >= 10 ? ['mobile' => $digits] : []) }}" class="btn-ghost"><x-icon name="user-plus" class="size-5" /> Add customer</a>
                        @endif
                    </x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="users" text="No customers yet. Add the first one, or they’ll be added as bills are made.">
                        @if ($canManage) <a href="{{ route('customers.create') }}" class="btn-ghost"><x-icon name="user-plus" class="size-5" /> Add customer</a> @endif
                    </x-ui.empty-state>
                @endif
            </div>
        @else
            {{-- PC table --}}
            <div class="hidden overflow-hidden rounded-xl border border-line bg-surface lg:block">
                <table class="table-pc">
                    <thead>
                        <tr><th>Customer</th><th>Mobile</th><th>Car</th><th>Source</th><th>Last visit</th><th class="text-right">Total spent</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $c)
                            <tr data-href="{{ route('customers.show', array_merge(['customer' => $c], request()->only('q', 'source', 'visit', 'sort'))) }}">
                                <td>
                                    <a href="{{ route('customers.show', array_merge(['customer' => $c], request()->only('q', 'source', 'visit', 'sort'))) }}" class="flex items-center gap-3 py-1.5 focus:outline-none">
                                        <x-ui.avatar-initials :name="$c->name" size="sm" />
                                        <span class="min-w-0">
                                            <span class="block truncate font-semibold text-ink">{{ $c->name }}</span>
                                            @if ($c->area) <span class="block truncate text-xs text-muted">{{ $c->area }}</span> @endif
                                        </span>
                                    </a>
                                </td>
                                <td class="tnum whitespace-nowrap">{{ \App\Support\Phone::pretty($c->mobile) }}</td>
                                <td class="max-w-56 truncate">{{ CC::carLine($c) ?? '—' }}</td>
                                <td><span class="text-muted">{{ Customer::SOURCE_LABELS[$c->source] ?? $c->source }}</span></td>
                                <td class="whitespace-nowrap">
                                    @if ($c->last_visit_at)
                                        <span title="{{ $c->last_visit_at->timezone('Asia/Kolkata')->format('j M Y') }}">{{ $c->last_visit_at->diffForHumans(['parts' => 1]) }}</span>
                                    @else <span class="text-faint">No visit yet</span> @endif
                                </td>
                                <td class="text-right"><x-ui.money :paise="$c->total_spent_paise" size="sm" class="text-ink" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Phone rows --}}
            <div class="-mx-4 divide-y divide-line border-y border-line bg-surface lg:hidden">
                @foreach ($customers as $c)
                    <x-ui.list-row :href="route('customers.show', $c)" :avatar="$c->name" :title="$c->name"
                                   :subtitle="CC::carLine($c) ?? \App\Support\Phone::pretty($c->mobile)">
                        <x-slot:trailing>
                            <x-ui.money :paise="$c->total_spent_paise" size="sm" class="block text-ink" />
                            <span class="mt-0.5 block text-xs font-medium text-muted">{{ $c->last_visit_at ? $c->last_visit_at->diffForHumans(['parts' => 1, 'short' => true]) : 'New' }}</span>
                        </x-slot:trailing>
                    </x-ui.list-row>
                @endforeach
            </div>

            @if ($customers->hasPages())
                <nav class="mt-4 flex items-center justify-between gap-3" aria-label="Pages">
                    <span class="text-[13px] font-medium text-muted">{{ $customers->firstItem() }}–{{ $customers->lastItem() }} of {{ number_format($customers->total()) }}</span>
                    <div class="flex gap-2">
                        @if ($customers->onFirstPage()) <span class="btn-ghost pointer-events-none opacity-45">Previous</span>
                        @else <a href="{{ $customers->previousPageUrl() }}" class="btn-ghost" rel="prev">Previous</a> @endif
                        @if ($customers->hasMorePages()) <a href="{{ $customers->nextPageUrl() }}" class="btn-ghost" rel="next">Next</a>
                        @else <span class="btn-ghost pointer-events-none opacity-45">Next</span> @endif
                    </div>
                </nav>
            @endif
        @endif
    </div>

    {{-- Phone: add button floating above the tab bar --}}
    @if ($canManage)
        <a href="{{ route('customers.create') }}" class="fixed right-4 bottom-[calc(env(safe-area-inset-bottom)+84px)] z-20 inline-flex h-12 items-center gap-2 rounded-lg bg-ink px-4 text-[15px] font-semibold text-white shadow-raised lg:hidden">
            <x-icon name="user-plus" class="size-5" /> Add customer
        </a>
    @endif

    {{-- Phone filter sheet --}}
    <x-ui.bottom-sheet name="customer-filters" title="Filter customers" :form="['action' => route('customers.index'), 'method' => 'GET']">
        <input type="hidden" name="q" value="{{ $filters['q'] }}">
        <div class="space-y-5">
            <div>
                <div class="label">Source</div>
                <x-ui.segmented name="source" :options="['' => 'Any'] + Customer::SOURCE_LABELS" :value="$filters['source']" wrap class="w-full" />
            </div>
            <div>
                <div class="label">Last visit</div>
                <x-ui.segmented name="visit" :options="CC::VISIT_FILTERS" :value="$filters['visit']" wrap class="w-full" />
            </div>
            <div>
                <div class="label">Sort by</div>
                <x-ui.segmented name="sort" :options="CC::SORTS" :value="$filters['sort']" wrap class="w-full" />
            </div>
            @can('customers.manage')
                <label class="flex min-h-11 items-center gap-3 text-[15px] font-medium">
                    <input type="checkbox" name="archived" value="1" class="size-5 accent-ink" @checked($filters['archived'])> Show archived customers only
                </label>
            @endcan
        </div>
        <x-slot:footer>
            <div class="flex gap-3">
                <a href="{{ route('customers.index') }}" class="btn-ghost flex-1">Reset</a>
                <button class="btn-primary flex-[2]">Show customers</button>
            </div>
        </x-slot:footer>
    </x-ui.bottom-sheet>
</x-layouts.app>
