@php
    /** @var \App\Models\JobCard $card */
    $thumb = $card->photos->firstWhere('stage', 'before') ?? $card->photos->first();
    $done = $card->items->where('done', true)->count();
    $total = $card->items->count();
    $late = $card->isLate();
    $promise = $card->promiseText();
@endphp
<article class="group relative rounded-xl border border-zinc-200 bg-white p-3 transition hover:border-zinc-400 {{ $compact ? '' : 'cursor-grab active:cursor-grabbing' }}"
         data-card data-id="{{ $card->id }}" data-status="{{ $card->status }}">
    <a href="{{ route('jobcards.show', $card) }}" class="absolute inset-0 rounded-xl focus-visible:outline-2 focus-visible:outline-ink" aria-label="Open {{ $card->number }}"></a>

    @if ($compact)
        <div class="flex items-center justify-between gap-2">
            <div class="min-w-0">
                <div class="whitespace-nowrap text-sm font-bold tabular-nums">{{ $card->plate() ?? $card->number }}</div>
                <div class="truncate text-xs font-medium text-zinc-600">{{ $card->carLabel() }} · {{ $card->customer?->name }}</div>
            </div>
            <span class="shrink-0 self-start whitespace-nowrap text-xs tabular-nums text-zinc-500">{{ $card->delivered_at?->timezone('Asia/Kolkata')->format('g:i a') }}</span>
        </div>
    @else
        <div class="flex items-center gap-2.5">
            <div class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-zinc-100 text-zinc-400">
                @if ($thumb)
                    <img src="{{ $thumb->url(true) }}" alt="" class="h-full w-full object-cover" loading="lazy">
                @else
                    <x-jobcards.icon name="camera" class="size-5" />
                @endif
            </div>
            <div class="min-w-0">
                <div class="whitespace-nowrap text-[15px] font-bold leading-5 tabular-nums">{{ $card->plate() ?? 'No plate' }}</div>
                <div class="truncate text-[13px] font-medium text-zinc-600">{{ $card->carLabel() }} · {{ $card->customer?->name }}</div>
            </div>
        </div>

        <ul class="mt-2 space-y-0.5 text-[13px] leading-[18px] text-ink">
            @foreach ($card->items->take(2) as $item)
                <li @class(['truncate', 'text-zinc-500 line-through decoration-zinc-400' => $item->done])>{{ $item->description }}</li>
            @endforeach
            @if ($card->items->count() > 2)
                <li class="text-xs text-zinc-500">+{{ $card->items->count() - 2 }} more</li>
            @endif
        </ul>

        @if ($promise)
            <div @class(['mt-2 flex items-center gap-1 whitespace-nowrap text-xs tabular-nums', 'font-semibold text-red-600' => $late, 'font-medium text-zinc-600' => ! $late])>
                <x-jobcards.icon name="clock" class="size-3.5 shrink-0" />{{ $promise }}
            </div>
        @endif
        <div class="mt-2 flex items-center gap-2 border-t border-zinc-100 pt-2 text-xs font-medium">
            @if ($card->technician)
                <span class="inline-flex items-center gap-1.5 truncate text-zinc-700" title="{{ $card->technician->name }}">
                    <span class="grid size-6 shrink-0 place-items-center rounded-md bg-zinc-100 text-[10px] font-bold text-ink">{{ \App\Models\JobCard::initials($card->technician->name) }}</span>
                </span>
            @else
                <span class="whitespace-nowrap rounded-md border border-dashed border-zinc-300 px-1.5 py-0.5 text-[11px] font-semibold text-zinc-500">Unassigned</span>
            @endif
            <span class="ml-auto inline-flex items-center gap-2.5 tabular-nums text-zinc-600">
                @if ($total)<span title="Work done" class="inline-flex items-center gap-0.5"><x-jobcards.icon name="check" class="size-3.5" />{{ $done }}/{{ $total }}</span>@endif
                <span class="inline-flex items-center gap-0.5" title="Photos"><x-jobcards.icon name="photo" class="size-3.5" />{{ $card->photos->count() }}</span>
            </span>
        </div>

        @if ($card->status === 'ready')
            <form method="POST" action="{{ route('jobcards.notify-ready', $card) }}" target="_blank" class="relative z-10 mt-2.5">
                @csrf
                <button class="inline-flex h-8 w-full items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-zinc-300 bg-white text-xs font-semibold text-ink hover:bg-zinc-50">
                    <x-jobcards.icon name="chat" class="size-4 shrink-0" /> WhatsApp “ready”
                </button>
            </form>
        @endif
    @endif
</article>
