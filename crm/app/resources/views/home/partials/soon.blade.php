{{-- A calm note for a Home section whose module isn't merged yet. @include('home.partials.soon', ['icon' => 'car', 'text' => '…']) --}}
<div class="flex items-start gap-3 rounded-lg border border-dashed border-line-strong px-3.5 py-3">
    <x-icon :name="$icon" class="mt-px size-[18px] shrink-0 text-faint" />
    <p class="text-[13px] leading-5 font-medium text-muted">{{ $text }}</p>
</div>
