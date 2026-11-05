<x-layouts.app title="Job board">
    @php
        $inShop = $summary['in_shop'];
        $chip = 'inline-flex h-9 items-center gap-2 rounded-full border px-3 text-sm font-medium';
        $chipOff = 'border-zinc-300 bg-white text-ink hover:border-zinc-400';
        $chipOn = 'border-ink bg-ink text-white';
        $seg = 'inline-flex h-8 items-center rounded-md px-3 font-medium';
        $filterUrl = fn (array $q) => route('jobcards.index', array_filter(array_merge(['technician' => $technicianId, 'scope' => $scope === 'all' ? null : $scope], $q)));
    @endphp
    <x-slot:header>
        <div>
            <h1 class="text-[28px] font-bold leading-tight tracking-tight">Job board</h1>
            <p class="text-sm font-medium text-zinc-600">
                {{ now('Asia/Kolkata')->format('l, j M') }} · {{ $inShop }} {{ \Illuminate\Support\Str::plural('car', $inShop) }} in
                @if ($summary['late']->count())
                    · <span class="text-red-600">{{ $summary['late']->count() }} late</span>
                @endif
            </p>
        </div>
        <a href="{{ route('jobcards.create') }}" class="btn-accent h-10 min-h-10 rounded-lg" data-shortcut="car-in">
            <x-jobcards.icon name="plus" /> Car in
        </a>
    </x-slot:header>

    <div class="mb-4 flex flex-wrap items-center gap-2" aria-label="Filters">
        <a href="{{ $filterUrl(['technician' => null]) }}" @class([$chip, $chipOn => ! $technicianId, $chipOff => (bool) $technicianId])>All technicians</a>
        @foreach ($technicians as $t)
            <a href="{{ $filterUrl(['technician' => $t->id]) }}" @class([$chip, $chipOn => $technicianId === $t->id, $chipOff => $technicianId !== $t->id])>
                <span class="grid size-6 place-items-center rounded-md bg-zinc-100 text-[10px] font-bold text-ink" aria-hidden="true">{{ \App\Models\JobCard::initials($t->name) }}</span>
                {{ $t->name }}
            </a>
        @endforeach
        <div class="ml-auto inline-flex rounded-lg bg-zinc-100 p-1 text-sm" role="tablist">
            <a href="{{ $filterUrl(['scope' => 'today']) }}" @class([$seg, 'bg-white text-ink shadow-[0_0_0_1px_#E4E4E7]' => $scope === 'today', 'text-zinc-600' => $scope !== 'today']) role="tab" aria-selected="{{ $scope === 'today' ? 'true' : 'false' }}">Today</a>
            <a href="{{ $filterUrl(['scope' => null]) }}" @class([$seg, 'bg-white text-ink shadow-[0_0_0_1px_#E4E4E7]' => $scope === 'all', 'text-zinc-600' => $scope !== 'all']) role="tab" aria-selected="{{ $scope === 'all' ? 'true' : 'false' }}">All open</a>
        </div>
    </div>

    <div class="-mx-4 overflow-x-auto px-4 pb-2 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0">
        <div class="grid min-w-[1060px] grid-cols-5 gap-3" data-jobcards="board"
             data-transitions='@json($transitions)' data-can-move="{{ $canMove ? '1' : '0' }}"
             data-status-url="{{ url('jobcards') }}">
            @foreach ($columns as $status => $label)
                @php $list = $byStatus->get($status, collect()); @endphp
                <section class="flex min-h-[60vh] flex-col rounded-xl bg-zinc-100/70 p-2" aria-label="{{ $label }}">
                    <header class="flex items-center justify-between px-1.5 pb-2 pt-1">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">{{ $label }}</h2>
                        <span class="rounded-full bg-white px-2 text-xs font-semibold tabular-nums text-ink" data-count>{{ $list->count() }}</span>
                    </header>
                    <div class="flex flex-1 flex-col gap-2" data-column="{{ $status }}">
                        @foreach ($list as $card)
                            @include('jobcards._board-card', ['card' => $card, 'compact' => $status === 'delivered'])
                        @endforeach
                        <p data-empty class="hidden only:block rounded-lg border border-dashed border-zinc-300 px-3 py-6 text-center text-sm text-zinc-500">
                            @switch($status)
                                @case('waiting') No cars waiting. New cars appear here after “Car in”. @break
                                @case('working') Nobody is working on a car. @break
                                @case('qc') Nothing to check. @break
                                @case('ready') No cars ready for pickup. @break
                                @default No cars handed over today.
                            @endswitch
                        </p>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
    <p class="mt-3 text-xs text-zinc-500">Drag a card to another column to change its status. Each move is timed and logged.</p>

    <div class="pointer-events-none fixed inset-x-0 bottom-24 z-50 flex justify-center px-4 lg:bottom-6" aria-live="polite">
        <div data-toast hidden class="pointer-events-auto flex max-w-xl items-center gap-4 rounded-lg bg-ink px-4 py-3 text-sm font-medium text-white shadow-lg"></div>
    </div>
    <style>
        .jc-ghost { opacity: .4; border-style: dashed; border-color: #a1a1aa; }
        .sortable-fallback { box-shadow: 0 10px 28px rgba(0, 0, 0, .14); transform: rotate(1.5deg); }
    </style>
</x-layouts.app>
