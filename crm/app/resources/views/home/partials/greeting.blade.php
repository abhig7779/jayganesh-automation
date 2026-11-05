{{-- Page title. Phone: large "Today" + date. PC: "Good morning, Rohit" + date · shop. --}}
@php
    $hour = now(\App\Support\Dashboard\HomeData::TZ)->hour;
    $part = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $first = \Illuminate\Support\Str::before($user->name, ' ');
@endphp
<div class="mb-4 flex items-end justify-between gap-3 lg:mb-5">
    <div class="min-w-0">
        <h1 class="display-title lg:hidden">Today</h1>
        <h1 class="hidden text-2xl leading-8 font-bold tracking-[-0.01em] lg:block">{{ $part }}, {{ $first }}</h1>
        <p class="mt-0.5 hidden text-sm text-muted lg:block">{{ $today->format('l, j M') }} · {{ $user->branch?->name ?? 'Jay Ganesh Car Accessories' }}</p>
    </div>
    <p class="pb-1.5 text-[15px] font-medium text-muted lg:hidden">{{ $today->format('D, j M') }}</p>
    @isset($aside) <div class="hidden lg:block">{{ $aside }}</div> @endisset
</div>
@if ($user->can('customers.view'))
    {{-- Phone: sticky search right under the large title (PC has it in the top bar). --}}
    <div class="sticky top-[calc(env(safe-area-inset-top)+57px)] z-20 -mx-4 -mt-1 mb-4 bg-canvas px-4 py-2 lg:hidden">
        <x-ui.search-input global />
    </div>
@endif
