{{--
    <x-ui.empty-state icon="users" text="No customers yet. Add the first one.">
        <a class="btn-ghost" href="…">Add customer</a>
    </x-ui.empty-state>
    One outline icon, one line that says what to do, one optional button (DESIGN §5 Empty state).
    `compact` uses less vertical padding, for use inside cards.
--}}
@props(['icon' => 'grid', 'text', 'compact' => false])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-4 text-center '.($compact ? 'py-7' : 'py-14')]) }}>
    <x-icon :name="$icon" class="size-8 text-muted" />
    <p class="mt-3 max-w-sm text-[15px] leading-[22px] font-medium text-ink-2">{{ $text }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
