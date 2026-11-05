{{-- Right column panels shared by the counter and owner Home: workshop, draft bills, low stock. --}}
@php
    $jobBoard = \Illuminate\Support\Facades\Route::has('jobcards.index') ? route('jobcards.index') : null;
    $stockIndex = \Illuminate\Support\Facades\Route::has('products.index') ? route('products.index') : null;
@endphp

@if ($user->can('jobcards.view'))
    @php $workshop = $workshop ?? null; @endphp
    <x-ui.panel title="Cars in workshop">
        <x-slot:aside>
            @if ($workshop === null) <x-ui.status-badge status="soon" />
            @elseif ($jobBoard) <a href="{{ $jobBoard }}" class="text-[13px] font-semibold hover:underline">Bay view</a> @endif
        </x-slot:aside>
        @if ($workshop === null)
            @include('home.partials.soon', ['icon' => 'car', 'text' => 'Switches on with Job cards: cars waiting, being worked on, in QC and ready for pickup.'])
        @else
            <div class="grid grid-cols-4 gap-2">
                @foreach ($workshop as $key => $w)
                    <a href="{{ $jobBoard ?? '#' }}" class="rounded-lg border border-line px-2 py-2.5 text-center hover:bg-hover">
                        <div class="tnum text-xl leading-6 font-bold {{ $key === 'ready' && $w['count'] ? 'text-ok' : '' }}">{{ $w['count'] }}</div>
                        <div class="mt-0.5 text-xs font-medium text-muted">{{ $w['label'] }}</div>
                    </a>
                @endforeach
            </div>
        @endif
    </x-ui.panel>
@endif

@if ($user->can('invoices.create'))
    <x-ui.panel title="Draft bills" :subtitle="$drafts === null ? null : 'Saved automatically'">
        <x-slot:aside>@if ($drafts === null) <x-ui.status-badge status="soon" /> @endif</x-slot:aside>
        @if ($drafts === null)
            @include('home.partials.soon', ['icon' => 'receipt', 'text' => 'Half-made bills will wait here so nobody has to start again.'])
        @elseif (empty($drafts))
            <p class="text-[13px] font-medium text-muted">No drafts waiting.</p>
        @else
            <ul class="-mx-4 divide-y divide-line border-t border-line lg:-mx-5">
                @foreach ($drafts as $d)
                    <li class="flex items-center gap-3 px-4 py-2.5 lg:px-5">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold">{{ $d['name'] }}</div>
                            <div class="truncate text-xs font-medium text-muted">{{ $d['detail'] }}</div>
                        </div>
                        @if ($d['url']) <a href="{{ $d['url'] }}" class="btn-ghost min-h-9 px-3 text-[13px] lg:min-h-8">Resume</a> @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.panel>
@endif

@if ($user->can('inventory.view'))
    <x-ui.panel title="Low stock alerts">
        <x-slot:aside>@if ($stockIndex && $lowStock) <a href="{{ $stockIndex }}" class="text-[13px] font-semibold hover:underline">Stock</a> @endif</x-slot:aside>
        @if (empty($lowStock))
            <div class="flex items-center gap-2 text-[13px] font-medium text-muted">
                <x-icon name="check" class="size-4 text-ok" stroke="2" /> Nothing is running low.
            </div>
        @else
            <ul class="-mx-4 divide-y divide-line border-t border-line lg:-mx-5">
                @foreach ($lowStock as $p)
                    <li class="flex items-center gap-3 px-4 py-2.5 lg:px-5">
                        <div class="min-w-0 flex-1">
                            @if ($p['url'])
                                <a href="{{ $p['url'] }}" class="block truncate text-sm font-semibold hover:underline">{{ $p['name'] }}</a>
                            @else
                                <div class="truncate text-sm font-semibold">{{ $p['name'] }}</div>
                            @endif
                            <div class="truncate text-xs font-medium text-muted">{{ $p['detail'] }}</div>
                        </div>
                        <x-ui.status-badge :tone="$p['left'] <= 0 ? 'bad' : 'warn'">{{ $p['left'] <= 0 ? 'Out of stock' : $p['left'].' left' }}</x-ui.status-badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.panel>
@endif
