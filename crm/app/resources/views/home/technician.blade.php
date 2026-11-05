{{-- Home: Technician. Only "My job cards". No money anywhere (UX-FLOWS §2 Technician). --}}
<x-layouts.app title="My jobs">
    <div class="mb-5">
        <h1 class="display-title lg:text-2xl lg:leading-8">My job cards</h1>
        <p class="mt-1 text-[15px] text-muted lg:text-sm">{{ $today->format('l, j M') }}</p>
    </div>

    @if ($jobs === null)
        <div class="card">
            <x-ui.empty-state icon="wrench" text="Your cars will show here once job cards are switched on: the car, plate, work list and promised time, with Start, Add photo and Ready for QC buttons." />
        </div>
    @elseif (empty($jobs))
        <div class="card">
            <x-ui.empty-state icon="check" text="No cars assigned to you right now. The counter will assign the next one." />
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($jobs as $j)
                <a href="{{ $j['url'] ?? '#' }}" class="card block hover:border-line-strong">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate text-[17px] font-semibold">{{ $j['car'] }}</div>
                            @if ($j['plate']) <div class="mt-1 inline-flex rounded border border-line-strong px-1.5 py-0.5 text-xs font-semibold tracking-wide">{{ $j['plate'] }}</div> @endif
                        </div>
                        <x-ui.status-badge :status="$j['status']" />
                    </div>
                    @if ($j['promised']) <div class="mt-3 flex items-center gap-1.5 text-[13px] font-medium text-muted"><x-icon name="clock" class="size-4" /> Promised by {{ $j['promised'] }}</div> @endif
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
