{{--
    <x-ui.action-tile :href="$url" icon="user-plus" label="Add customer" hint="Mobile number first" shortcut="F7" />
    <x-ui.action-tile primary …/>   phone: the one big yellow, full-width tile (New bill)
    <x-ui.action-tile ink …/>       PC row: the same action as a small ink-filled card (the top bar
                                    already has the yellow New bill, and a screen gets two yellows max)
    A Home action. `href` null means the module isn't switched on yet: the tile stays calm with a
    "Soon" tag and isn't clickable. `hint` is one short line (`hintTone="bad"` for a due amount).
    `shortcut` (e.g. F7) shows a keycap on PC and is wired: pressing the key opens the tile.
--}}
@props(['href' => null, 'icon', 'label', 'hint' => null, 'hintTone' => null, 'primary' => false, 'ink' => false, 'shortcut' => null])
@php
    $tag = $href ? 'a' : 'div';
    $look = match (true) {
        $primary && (bool) $href => 'border-brand bg-brand text-on-brand hover:border-brand-press hover:bg-brand-press',
        $ink && (bool) $href => 'border-ink bg-ink text-white hover:bg-zinc-800',
        (bool) $href => 'border-line bg-surface text-ink hover:border-line-strong hover:bg-hover',
        default => 'border-line bg-surface text-ink cursor-default',
    };
    $shape = $primary ? 'min-h-[72px] items-center gap-4 px-4 py-3' : 'min-h-[100px] flex-col justify-between gap-2 p-3.5 lg:min-h-[92px]';
    $dim = ! $href;
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @if ($shortcut) data-fkey="{{ $shortcut }}" aria-keyshortcuts="{{ $shortcut }}" @endif @else aria-disabled="true" @endif
    {{ $attributes->merge(['class' => "group relative flex min-w-0 rounded-xl border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2 $shape $look"]) }}>
    @if ($primary)
        <span @class(['inline-flex size-11 shrink-0 items-center justify-center rounded-lg', 'bg-ink/10' => ! $dim, 'bg-sunken text-faint' => $dim])><x-icon :name="$icon" class="size-6" stroke="1.8" /></span>
        <span class="min-w-0 flex-1">
            <span @class(['block text-[19px] leading-6 font-bold', 'text-muted' => $dim])>{{ $label }}</span>
            @if ($hint) <span @class(['block truncate text-[13px] font-medium', 'text-ink/75' => ! $dim, 'text-muted' => $dim])>{{ $hint }}</span> @endif
        </span>
        @if ($dim)
            <x-ui.status-badge status="soon" />
        @else
            <x-icon name="arrow-right" class="size-6 shrink-0" stroke="1.8" />
        @endif
    @else
        <span class="flex items-start justify-between gap-2">
            <x-icon :name="$icon" @class(['size-[22px]', 'text-faint' => $dim]) />
            @if ($dim)
                <x-ui.status-badge status="soon" />
            @elseif ($shortcut)
                <span @class(['kbd hidden lg:inline-flex', 'border-white/25 bg-transparent text-white/80' => $ink])>{{ $shortcut }}</span>
            @endif
        </span>
        <span class="block min-w-0">
            <span @class(['line-clamp-2 block text-[15px] leading-5 font-semibold lg:text-sm lg:leading-[18px]', 'text-muted' => $dim])>{{ $label }}</span>
            @if ($hint)
                <span @class(['mt-0.5 block truncate text-xs font-medium', 'text-bad' => $hintTone === 'bad', 'text-white/70' => $ink && ! $dim && $hintTone !== 'bad', 'text-muted' => (! $ink || $dim) && $hintTone !== 'bad'])>{{ $hint }}</span>
            @endif
        </span>
    @endif
</{{ $tag }}>
