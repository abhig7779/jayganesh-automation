<x-layouts.app title="My jobs">
    {{-- Technician home (UX-FLOWS §2, STITCH X2). Phone first, 48 px targets, no money anywhere. --}}
    <div data-jobcards="mine" class="mx-auto max-w-xl">
        <div class="mb-4">
            <h1 class="text-[34px] font-bold leading-10 tracking-tight">My jobs</h1>
            <p class="text-[15px] font-medium text-zinc-600">
                {{ preg_replace('/\s*\(.*?\)/', '', $user->name) }} · {{ $cards->count() }} {{ \Illuminate\Support\Str::plural('car', $cards->count()) }}
            </p>
        </div>

        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-[15px] font-medium text-red-700">{{ $errors->first() }}</div>
        @endif

        @forelse ($cards as $card)
            @php
                $hero = $card->photos->firstWhere('stage', 'before') ?? $card->photos->first();
                $done = $card->items->where('done', true)->count();
                $hasBefore = $card->photos->where('stage', 'before')->isNotEmpty();
                $late = $card->isLate();
            @endphp
            <article data-job id="job-{{ $card->id }}" class="mb-4 overflow-hidden rounded-xl border border-zinc-200 bg-white">
                <a href="{{ route('jobcards.show', $card) }}" @class(['relative block bg-zinc-100', 'aspect-[16/9]' => $hero, 'h-20' => ! $hero])>
                    @if ($hero)
                        <img src="{{ $hero->url() }}" alt="Photo of {{ $card->carLabel() }}" class="h-full w-full object-cover" loading="lazy">
                        @if ($card->photos->count() > 1)
                            <span class="absolute bottom-2 right-2 inline-flex items-center gap-1 rounded-md bg-ink/80 px-2 py-1 text-xs font-semibold text-white">
                                <x-jobcards.icon name="photo" class="size-4" /> {{ $card->photos->count() }}
                            </span>
                        @endif
                    @else
                        <span class="flex h-full items-center justify-center gap-2 text-zinc-500">
                            <x-jobcards.icon name="camera" class="size-6" />
                            <span class="text-sm font-medium">No photo yet</span>
                        </span>
                    @endif
                </a>

                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-[22px] font-bold leading-7 tabular-nums">{{ $card->plate() ?? 'No plate' }}</h2>
                            <p class="text-[15px] font-medium text-zinc-600">{{ $card->carLabel(true) }}@if ($card->car?->colour) · {{ $card->car->colour }}@endif</p>
                            @if ($card->promiseText())
                                <p @class(['mt-0.5 flex items-center gap-1 text-[15px]', 'font-semibold text-red-600' => $late, 'font-medium text-zinc-600' => ! $late])>
                                    <x-jobcards.icon name="clock" class="size-4" />{{ $card->promiseText() }}
                                </p>
                            @endif
                        </div>
                        <x-jobcards.status :status="$card->status" size="md" class="mt-1 shrink-0" />
                    </div>

                    @if ($card->notes)
                        <p class="mt-3 rounded-lg bg-zinc-100 px-3 py-2 text-sm text-zinc-700">{{ $card->notes }}</p>
                    @endif

                    <div class="mt-3 flex items-center justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Work</h3>
                        <span class="text-xs font-semibold tabular-nums text-zinc-600"><span data-progress>{{ $done }}/{{ $card->items->count() }}</span> done</span>
                    </div>
                    <div class="-mx-2 mt-1 divide-y divide-zinc-100">
                        @foreach ($card->items as $item)
                            @include('jobcards._tick', ['card' => $card, 'item' => $item, 'canTick' => in_array($card->status, ['waiting', 'working', 'qc'], true)])
                        @endforeach
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <form method="POST" action="{{ route('jobcards.photos.store', $card) }}" enctype="multipart/form-data" data-photo-upload class="contents">
                            @csrf
                            <input type="hidden" name="stage" value="{{ $card->status === 'waiting' || ! $hasBefore ? 'before' : 'after' }}">
                            <label class="flex h-14 cursor-pointer items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white text-[17px] font-semibold text-ink active:bg-zinc-100">
                                <x-jobcards.icon name="camera" class="size-6" />
                                <span>Add photo</span>
                                <input type="file" name="photos[]" accept="image/*" capture="environment" multiple class="sr-only">
                            </label>
                            <button data-upload-submit class="col-span-2 h-12 rounded-lg border border-zinc-300 text-sm font-semibold">Upload photos</button>
                            <p data-upload-status hidden class="col-span-2 order-last text-sm font-medium text-zinc-700" role="status"></p>
                        </form>

                        @if ($card->status === 'waiting')
                            <form method="POST" action="{{ route('jobcards.status', $card) }}">
                                @csrf <input type="hidden" name="status" value="working">
                                <button class="flex h-14 w-full items-center justify-center gap-2 rounded-lg bg-[#FFD51C] text-[17px] font-semibold text-ink active:bg-[#F2C800]">
                                    <x-jobcards.icon name="play" class="size-5" /> Start
                                </button>
                            </form>
                        @elseif ($card->status === 'working')
                            <form method="POST" action="{{ route('jobcards.status', $card) }}">
                                @csrf <input type="hidden" name="status" value="qc">
                                <button class="flex h-14 w-full items-center justify-center gap-2 rounded-lg bg-[#FFD51C] text-[17px] font-semibold text-ink active:bg-[#F2C800]">
                                    <x-jobcards.icon name="check" class="size-5" /> Ready for QC
                                </button>
                            </form>
                        @else
                            <div class="flex h-14 items-center justify-center rounded-lg bg-zinc-100 px-2 text-center text-sm font-semibold text-zinc-600">
                                {{ $card->status === 'qc' ? 'Waiting for QC check' : 'Ready for pickup' }}
                            </div>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-zinc-500">
                        {{ $hasBefore ? 'Photos now go in as “after” photos.' : 'Take “before” photos of the car first.' }}
                    </p>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-white px-6 py-10 text-center">
                <x-jobcards.icon name="wrench" class="mx-auto size-8 text-zinc-500" />
                <p class="mt-3 text-[15px] font-medium text-zinc-700">No cars assigned to you right now.</p>
                <p class="mt-1 text-sm text-zinc-500">The counter assigns cars from the job board. Pull down to refresh.</p>
                <a href="{{ route('jobcards.mine') }}" class="mt-4 inline-flex h-12 items-center justify-center rounded-lg border border-zinc-300 px-5 font-semibold">Refresh</a>
            </div>
        @endforelse
    </div>
</x-layouts.app>
