@php /** @var \App\Models\JobCardItem $item */ @endphp
@if ($canTick)
    <form method="POST" action="{{ route('jobcards.items.toggle', [$card, $item]) }}" data-tick data-done="{{ $item->done ? '1' : '0' }}"
          class="group" aria-pressed="{{ $item->done ? 'true' : 'false' }}">
        @csrf
        <input type="hidden" name="done" value="{{ $item->done ? '0' : '1' }}">
        <button data-box class="flex min-h-12 w-full items-center gap-3 rounded-lg px-2 text-left hover:bg-zinc-50 focus-visible:outline-2 focus-visible:outline-ink">
            <span class="grid size-7 shrink-0 place-items-center rounded-md border-2 border-zinc-400 text-white group-data-[done=1]:border-ink group-data-[done=1]:bg-ink">
                <x-jobcards.icon name="check" class="size-5 opacity-0 group-data-[done=1]:opacity-100" />
            </span>
            <span class="flex-1 text-base font-medium text-ink group-data-[done=1]:text-zinc-500 group-data-[done=1]:line-through">
                {{ $item->description }}@if ($item->qty > 1) <span class="text-zinc-500">× {{ $item->qty }}</span>@endif
            </span>
        </button>
    </form>
@else
    <div class="flex min-h-12 items-center gap-3 px-2" data-done="{{ $item->done ? '1' : '0' }}">
        <span @class(['grid size-7 shrink-0 place-items-center rounded-md border-2', 'border-ink bg-ink text-white' => $item->done, 'border-zinc-300' => ! $item->done])>
            @if ($item->done)<x-jobcards.icon name="check" class="size-5" />@endif
        </span>
        <span @class(['flex-1 text-base font-medium', 'text-zinc-500 line-through' => $item->done])>{{ $item->description }}@if ($item->qty > 1) <span class="text-zinc-500">× {{ $item->qty }}</span>@endif</span>
    </div>
@endif
