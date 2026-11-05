{{--
    The app shell. API (stable, used by every module):
        <x-layouts.app title="Customers"> … </x-layouts.app>
        optional <x-slot:header> … </x-slot:header>   rendered above the page content
        optional :search="true"   phone only: a sticky global search under the header (PC always
                                  has it in the top bar)
        optional :wide="true"     drop the 1200 px content cap (POS, boards, two-pane screens)
    PC/tablet ≥1024 px: ink sidebar (240) + white top bar (search, New bill · F2, user menu).
    Phone: compact top row, page content, bottom tabs Home · Bills · (New bill) · Customers · More,
    and a "More" bottom sheet. Menu items come from partials/nav.php and switch on by route name.
--}}
@props(['title' => null, 'header' => null, 'search' => false, 'wide' => false])
@php
    $user = auth()->user();
    $nav = collect(require resource_path('views/partials/nav.php'))
        ->filter(fn ($i) => ($i['can'] === null || $user->can($i['can'])) && (! isset($i['when']) || ($i['when'])($user)))
        ->map(function ($i) use ($user) {
            $i['children'] = array_values(array_filter($i['children'] ?? [], fn ($c) => $user->can($c['can']) && (! isset($c['when']) || ($c['when'])($user))));

            return $i;
        })
        ->values();
    $isActive = function (array $i): bool {
        if (! $i['route'] || ! empty($i['soon'])) {
            return false;
        }
        if (! empty($i['active'])) {
            return request()->routeIs(...$i['active']);
        }
        if (str_ends_with($i['route'], '.create')) {
            return request()->routeIs($i['route']);
        }

        return request()->routeIs($i['route']) || request()->routeIs(\Illuminate\Support\Str::before($i['route'], '.').'.*');
    };

    // New bill: always the raised centre tab / the yellow top-bar button for roles that can bill.
    $canBill = $user->can('invoices.create');
    $billUrl = \Illuminate\Support\Facades\Route::has('bills.create') ? route('bills.create') : null;

    // Bottom tabs: the items flagged 'mobile' (except New bill), in the order the design asks for.
    $tabOrder = ['dashboard' => 1, 'jobcards.mine' => 1, 'bills.index' => 2, 'customers.index' => 3];
    $tabs = $nav->filter(fn ($i) => ! empty($i['mobile']) && $i['route'] !== 'bills.create')
        ->sortBy(fn ($i) => $tabOrder[$i['route']] ?? 9)->values();
    $leftTabs = $canBill ? $tabs->take(2) : $tabs;
    $rightTabs = $canBill ? $tabs->slice(2) : collect();
    $moreItems = $nav->reject(fn ($i) => $tabs->contains('route', $i['route']) || $i['route'] === 'bills.create');
    $tabCount = $tabs->count() + ($canBill ? 1 : 0) + 1;
    $first = \Illuminate\Support\Str::before($user->name, ' ');
    $followUpBadge = \App\Support\Dashboard\HomeData::followUpBadge($user);
@endphp
<x-layouts.base :title="$title ?? null">
<div class="min-h-dvh lg:flex">

    {{-- ============ Sidebar (PC / tablet landscape) ============ --}}
    <aside class="hidden w-60 shrink-0 flex-col bg-ink lg:sticky lg:top-0 lg:flex lg:h-dvh" aria-label="Main menu">
        <a href="{{ route('dashboard') }}" class="flex h-16 shrink-0 items-center gap-2 px-5" aria-label="Jay Ganesh CRM, home">
            <span class="text-[17px] font-bold tracking-[-0.01em] text-white">Jay Ganesh</span>
            <span class="rounded bg-brand px-1.5 py-px text-[10px] leading-4 font-bold tracking-wide text-on-brand">CRM</span>
        </a>
        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-3">
            @foreach ($nav as $item)
                @if ($item['soon'])
                    <span class="nav-link cursor-default text-zinc-500 hover:bg-transparent hover:text-zinc-500" aria-disabled="true">
                        <x-icon :name="$item['icon']" class="size-5" /> {{ $item['label'] }}
                        <span class="ml-auto rounded border border-white/10 px-1.5 py-px text-[10px] font-semibold tracking-wide text-zinc-500 uppercase">Soon</span>
                    </span>
                @else
                    <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => $isActive($item)])
                       @if ($isActive($item)) aria-current="page" @endif
                       @if (! empty($item['shortcut'])) data-shortcut="{{ $item['shortcut'] }}" @endif>
                        <x-icon :name="$item['icon']" class="size-5" /> {{ $item['label'] }}
                        @if (($item['badge'] ?? null) === 'followups' && $followUpBadge)
                            <span class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-bad px-1.5 text-[11px] leading-5 font-bold text-white" data-badge="followups" aria-label="{{ $followUpBadge }} due">{{ $followUpBadge }}</span>
                        @endif
                        @if (! empty($item['shortcut']))
                            <span class="ml-auto rounded border border-white/15 px-1.5 py-px text-[10px] font-semibold text-zinc-400">F2</span>
                        @endif
                    </a>
                    @foreach ($item['children'] as $child)
                        @php $childOn = $isActive($child); @endphp
                        <a href="{{ route($child['route']) }}" @class(['nav-link min-h-9 py-1.5 pl-11 text-[13px]', 'nav-link-active' => $childOn]) @if ($childOn) aria-current="page" @endif>{{ $child['label'] }}</a>
                    @endforeach
                @endif
            @endforeach
        </nav>
        <div class="flex shrink-0 items-center gap-3 border-t border-white/10 px-4 py-3">
            <x-ui.avatar-initials :name="$user->name" size="sm" tone="sidebar" />
            <div class="min-w-0 flex-1 leading-tight">
                <div class="truncate text-[13px] font-semibold text-white">{{ $user->name }}</div>
                <div class="truncate text-[11px] font-medium text-zinc-400">{{ $user->roleLabel() }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="inline-flex size-9 items-center justify-center rounded-md text-zinc-400 hover:bg-white/5 hover:text-white" aria-label="Sign out" title="Sign out"><x-icon name="logout" class="size-5" /></button>
            </form>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        {{-- ============ Top bar (PC) ============ --}}
        <header class="sticky top-0 z-30 hidden h-16 shrink-0 items-center gap-4 border-b border-line bg-surface px-8 lg:flex">
            @if (\Illuminate\Support\Facades\Route::has('search') && $user->can('customers.view'))
                <x-ui.search-input global class="w-full max-w-[560px]" placeholder="Search mobile, name, car number, bill or product" />
            @endif
            <div class="ml-auto flex items-center gap-3">
                @if ($canBill)
                    @if ($billUrl)
                        <a href="{{ $billUrl }}" class="btn-accent" data-shortcut="new-bill">
                            <x-icon name="plus" class="size-5" stroke="2" /> New bill <span class="kbd border-ink/20 bg-transparent text-ink/70">F2</span>
                        </a>
                    @else
                        <span class="btn cursor-default border border-line bg-sunken text-muted" aria-disabled="true" title="Billing switches on soon">
                            <x-icon name="plus" class="size-5" /> New bill <span class="kbd">F2</span>
                        </span>
                    @endif
                @endif
                <div class="relative" x-data="{ menu: false }" @click.outside="menu = false" @keydown.escape="menu = false">
                    <button type="button" @click="menu = !menu" class="flex size-11 items-center justify-center rounded-lg hover:bg-sunken" :aria-expanded="menu.toString()" aria-haspopup="menu">
                        <x-ui.avatar-initials :name="$user->name" size="sm" tone="dark" />
                        <span class="sr-only">{{ $user->name }}, account menu</span>
                    </button>
                    <div x-show="menu" x-cloak x-transition.opacity.duration.100ms role="menu"
                         class="absolute top-full right-0 z-40 mt-2 w-56 overflow-hidden rounded-xl border border-line bg-surface py-1 shadow-pop">
                        <div class="border-b border-line px-4 py-3">
                            <div class="text-[13px] font-semibold">{{ $user->name }}</div>
                            <div class="text-xs text-muted">{{ $user->roleLabel() }} · {{ \App\Support\Phone::pretty($user->phone) ?: $user->email }}</div>
                        </div>
                        <a href="{{ route('profile.edit') }}" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm font-medium text-ink hover:bg-sunken" role="menuitem"><x-icon name="id" class="size-4" /> My profile</a>{{-- profile module --}}
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm font-medium text-ink hover:bg-sunken" role="menuitem">
                                <x-icon name="logout" class="size-4" /> Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- ============ Top row (phone / tablet portrait) ============ --}}
        <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-line bg-surface px-4 lg:hidden" style="padding-top: env(safe-area-inset-top)">
            <a href="{{ route('dashboard') }}" class="flex min-h-14 min-w-0 flex-1 items-center gap-3">
                <span class="inline-flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-ink">
                    <img src="{{ asset('img/logo.png') }}" alt="" class="h-8 w-auto">
                </span>
                <span class="min-w-0 leading-tight">
                    <span class="block text-[15px] font-semibold text-ink">Jay Ganesh</span>
                    <span class="block truncate text-xs font-medium text-muted">{{ $user->roleLabel() }} · {{ $first }}</span>
                </span>
            </a>
            @if (\Illuminate\Support\Facades\Route::has('search') && $user->can('customers.view') && ! $search)
                <a href="{{ route('search') }}" class="btn-icon -mr-2" aria-label="Search"><x-icon name="search" class="size-6" /></a>
            @endif
        </header>

        <main class="flex-1 px-4 pt-4 pb-[calc(env(safe-area-inset-bottom)+104px)] lg:px-8 lg:pt-7 lg:pb-12">
            <div @class(['mx-auto', 'max-w-[1200px]' => ! $wide])>
                @if (session('status'))
                    <div role="status" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition.opacity
                         class="mb-4 flex items-center gap-3 rounded-lg border border-ok-line bg-ok-tint px-4 py-3 text-sm font-medium text-ok">
                        <x-icon name="check" class="size-5 shrink-0" stroke="2" />
                        <span class="flex-1">{{ session('status') }}</span>
                        <button type="button" @click="show = false" class="text-ok/70 hover:text-ok" aria-label="Dismiss"><x-icon name="x" class="size-4" /></button>
                    </div>
                @endif
                @isset($header)
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">{{ $header }}</div>
                @endisset
                @if ($search && \Illuminate\Support\Facades\Route::has('search') && $user->can('customers.view'))
                    <div class="sticky top-[calc(env(safe-area-inset-top)+57px)] z-20 -mx-4 mb-4 bg-canvas px-4 py-2 lg:hidden">
                        <x-ui.search-input global />
                    </div>
                @endif
                {{ $slot }}
            </div>
        </main>
    </div>

    {{-- ============ Bottom tab bar (phone) ============ --}}
    <nav x-data class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface lg:hidden" style="padding-bottom: env(safe-area-inset-bottom)" aria-label="Tabs">
        <div class="grid h-16" style="grid-template-columns: repeat({{ $tabCount }}, minmax(0, 1fr))">
            @foreach ([$leftTabs, 'centre', $rightTabs] as $group)
                @if ($group === 'centre')
                    @if ($canBill)
                        <div class="relative flex justify-center">
                            @if ($billUrl)
                                <a href="{{ $billUrl }}" data-shortcut="new-bill" class="absolute -top-4 flex flex-col items-center gap-1" aria-label="New bill">
                                    <span class="inline-flex size-14 items-center justify-center rounded-full bg-brand text-on-brand shadow-raised ring-4 ring-surface active:bg-brand-press"><x-icon name="plus" class="size-7" stroke="2" /></span>
                                    <span class="text-[11px] leading-none font-semibold text-ink">New bill</span>
                                </a>
                            @else
                                <span class="absolute -top-4 flex flex-col items-center gap-1" aria-label="New bill" aria-disabled="true" title="Billing switches on soon">
                                    <span class="inline-flex size-14 items-center justify-center rounded-full bg-sunken text-faint ring-4 ring-surface"><x-icon name="plus" class="size-7" stroke="2" /></span>
                                    <span class="text-[11px] leading-none font-semibold text-faint">New bill</span>
                                </span>
                            @endif
                        </div>
                    @endif
                @else
                    @foreach ($group as $item)
                        @if ($item['soon'])
                            <span class="flex flex-col items-center justify-center gap-1 text-faint" aria-disabled="true">
                                <x-icon :name="$item['icon']" class="size-6" />
                                <span class="text-[11px] leading-none font-semibold">{{ $item['label'] }} <span class="sr-only">(soon)</span></span>
                            </span>
                        @else
                            @php $on = $isActive($item); @endphp
                            <a href="{{ route($item['route']) }}" @class(['relative flex flex-col items-center justify-center gap-1', 'text-ink' => $on, 'text-muted' => ! $on]) @if ($on) aria-current="page" @endif>
                                @if ($on) <span class="absolute top-0 h-[3px] w-6 rounded-b bg-brand"></span> @endif
                                @if ($item['route'] === 'dashboard' && $followUpBadge)
                                    <span class="absolute top-1.5 left-1/2 ml-1.5 inline-flex min-w-[18px] items-center justify-center rounded-full bg-bad px-1 text-[10px] leading-[18px] font-bold text-white" aria-label="{{ $followUpBadge }} follow-ups due">{{ $followUpBadge }}</span>
                                @endif
                                <x-icon :name="$item['icon']" class="size-6" :stroke="$on ? '2.1' : '1.6'" />
                                <span class="text-[11px] leading-none font-semibold">{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            <button type="button" @click="$dispatch('sheet', 'more')" class="flex flex-col items-center justify-center gap-1 text-muted">
                <x-icon name="grid" class="size-6" />
                <span class="text-[11px] leading-none font-semibold">More</span>
            </button>
        </div>
    </nav>

    {{-- ============ "More" sheet (phone) ============ --}}
    <x-ui.bottom-sheet name="more" title="More" class="lg:hidden">
        <div class="-mx-4 divide-y divide-line border-y border-line">
            @forelse ($moreItems as $item)
                @if ($item['soon'])
                    <div class="flex min-h-14 items-center gap-3 px-4 text-muted" aria-disabled="true">
                        <x-icon :name="$item['icon']" class="size-6 text-faint" />
                        <span class="flex-1 text-[15px] font-medium">{{ $item['label'] }}</span>
                        <x-ui.status-badge status="soon" />
                    </div>
                @else
                    <a href="{{ route($item['route']) }}" class="flex min-h-14 items-center gap-3 px-4 hover:bg-hover active:bg-sunken">
                        <x-icon :name="$item['icon']" class="size-6" />
                        <span class="flex-1 text-[15px] font-semibold">{{ $item['label'] }}</span>
                        @if (($item['badge'] ?? null) === 'followups' && $followUpBadge)
                            <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-bad px-1.5 text-[11px] leading-5 font-bold text-white">{{ $followUpBadge }}</span>
                        @endif
                        <x-icon name="chevron-right" class="size-4 text-faint" />
                    </a>
                @endif
            @empty
                <p class="px-4 py-4 text-[15px] text-muted">Everything you use is in the tabs below.</p>
            @endforelse
        </div>
        <div class="mt-4 flex items-center gap-3">
            <x-ui.avatar-initials :name="$user->name" />
            <div class="min-w-0 flex-1 leading-tight">
                <div class="truncate text-[15px] font-semibold">{{ $user->name }}</div>
                <div class="truncate text-[13px] text-muted">{{ $user->roleLabel() }} · {{ \App\Support\Phone::pretty($user->phone) ?: $user->email }}</div>
                <a href="{{ route('profile.edit') }}" class="mt-1 inline-flex min-h-8 items-center text-[13px] font-semibold text-ink underline underline-offset-4">My profile</a>{{-- profile module --}}
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn-ghost"><x-icon name="logout" class="size-5" /> Sign out</button>
            </form>
        </div>
    </x-ui.bottom-sheet>
</div>
</x-layouts.base>
