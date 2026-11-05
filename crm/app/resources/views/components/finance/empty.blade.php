@props(['icon' => 'box', 'title', 'href' => null, 'action' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-3 px-4 py-10 text-center']) }}>
    <x-finance.icon :name="$icon" class="size-8 text-zinc-500" />
    <p class="max-w-md text-[15px] font-medium text-zinc-700">{{ $title }}</p>
    @if (trim($slot))
        <div class="max-w-md text-sm text-zinc-600">{{ $slot }}</div>
    @endif
    @if ($href && $action)
        <a href="{{ $href }}" class="btn-ghost">{{ $action }}</a>
    @endif
</div>
