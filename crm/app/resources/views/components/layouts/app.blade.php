@props(['title' => null, 'header' => null])
@php
    $user = auth()->user();
    $nav = collect(require resource_path('views/partials/nav.php'))
        ->filter(fn ($i) => $i['can'] === null || $user->can($i['can']))
        ->values();
    $isActive = fn ($i) => $i['route'] && request()->routeIs(\Illuminate\Support\Str::before($i['route'], '.').'*');
@endphp
<x-layouts.base :title="$title ?? null">
<div x-data="{ more: false }" class="min-h-dvh lg:flex">

    {{-- Sidebar (tablet landscape / PC) --}}
    <aside class="hidden w-64 shrink-0 flex-col bg-ink px-3 py-5 lg:flex lg:sticky lg:top-0 lg:h-dvh">
        <a href="{{ route('dashboard') }}" class="mb-6 block px-3">
            <img src="{{ asset('img/logo.png') }}" alt="Jay Ganesh Car Accessories" class="h-14 w-auto">
            <span class="mt-1 block text-xs font-semibold tracking-widest text-brand-400">CRM</span>
        </a>
        <nav class="flex-1 space-y-1 overflow-y-auto">
            @foreach ($nav as $item)
                @if (!empty($item['soon']))
                    <span class="nav-link cursor-not-allowed opacity-45" title="Coming soon">
                        <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                        <span class="ml-auto text-[10px] uppercase tracking-wide">Soon</span>
                    </span>
                @else
                    <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => $isActive($item)])
                       @if (!empty($item['shortcut'])) data-shortcut="{{ $item['shortcut'] }}" @endif>
                        <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="mt-4 border-t border-white/10 px-3 pt-4 text-sm text-zinc-300">
            <div class="font-semibold text-white">{{ $user->name }}</div>
            <div class="text-xs">{{ $user->roleLabel() }} · {{ $user->branch?->name }}</div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button class="flex items-center gap-2 text-zinc-300 hover:text-white"><x-icon name="logout" /> Sign out</button>
            </form>
        </div>
    </aside>

    {{-- Top bar (phone / tablet portrait) --}}
    <header class="sticky top-0 z-30 flex items-center justify-between bg-ink px-4 py-2 lg:hidden" style="padding-top: max(0.5rem, env(safe-area-inset-top))">
        <a href="{{ route('dashboard') }}"><img src="{{ asset('img/logo.png') }}" alt="Jay Ganesh" class="h-9 w-auto"></a>
        <span class="text-sm font-medium text-zinc-300">{{ $user->name }}</span>
    </header>

    <main class="flex-1 px-4 pb-28 pt-5 sm:px-6 lg:px-10 lg:pb-10 lg:pt-8">
        <div class="mx-auto max-w-6xl">
            @if (session('status'))
                <div role="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
            @endif
            @isset($header)
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">{{ $header }}</div>
            @endisset
            {{ $slot }}
        </div>
    </main>

    {{-- Bottom tab bar (phone) --}}
    @php $tabs = $nav->where('mobile', true)->take(4); @endphp
    <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-zinc-200 bg-white lg:hidden" style="padding-bottom: env(safe-area-inset-bottom)">
        @foreach ($tabs as $item)
            @if (!empty($item['soon']))
                <span class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] text-zinc-300"><x-icon :name="$item['icon']" class="size-6" />{{ $item['label'] }}</span>
            @else
                <a href="{{ route($item['route']) }}" @class(['flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium', 'text-ink' => $isActive($item), 'text-zinc-500' => !$isActive($item)])>
                    <x-icon :name="$item['icon']" class="size-6" />{{ $item['label'] }}
                </a>
            @endif
        @endforeach
        <button type="button" @click="more = true" class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-zinc-500">
            <x-icon name="menu" class="size-6" />More
        </button>
    </nav>

    {{-- "More" sheet (phone) --}}
    <div x-cloak x-show="more" class="fixed inset-0 z-40 lg:hidden" @keydown.escape.window="more = false">
        <div class="absolute inset-0 bg-black/50" @click="more = false"></div>
        <div class="absolute inset-x-0 bottom-0 rounded-t-3xl bg-ink p-4 pb-8" x-transition>
            <div class="mb-3 flex items-center justify-between px-2">
                <span class="text-sm font-semibold text-white">{{ $user->roleLabel() }}</span>
                <button type="button" @click="more = false" class="p-2 text-zinc-300" aria-label="Close"><x-icon name="x" /></button>
            </div>
            <div class="grid grid-cols-2 gap-1">
                @foreach ($nav as $item)
                    @if (!empty($item['soon']))
                        <span class="nav-link opacity-45"><x-icon :name="$item['icon']" /> {{ $item['label'] }}</span>
                    @else
                        <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => $isActive($item)])><x-icon :name="$item['icon']" /> {{ $item['label'] }}</a>
                    @endif
                @endforeach
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-4 px-2">
                @csrf
                <button class="btn-ghost w-full"><x-icon name="logout" /> Sign out</button>
            </form>
        </div>
    </div>
</div>
</x-layouts.base>
