{{--
    <x-ui.list-row :href="route('customers.show', $c)" :title="$c->name" subtitle="Creta · MH 12 KT 4521" avatar="Rahul Pawar">
        <x-slot:trailing><x-ui.money :paise="…" /></x-slot:trailing>
    </x-ui.list-row>
    Phone list row, 64–72 px (DESIGN §5 List row). Leading is an initials square (`avatar` = a name)
    or an icon (`icon`, with optional `tone` ok|warn|bad). Put rows in a `divide-y divide-line`
    container. Without a `trailing` slot, a linked row shows a chevron. `active` marks the row
    that is open in a two-pane layout.
--}}
@props(['href' => null, 'title', 'subtitle' => null, 'avatar' => null, 'icon' => null, 'trailing' => null, 'tone' => null, 'active' => false])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif @if ($active) aria-current="page" @endif
    {{ $attributes->merge(['class' => 'relative flex min-h-16 items-center gap-3 px-4 py-3 '.($href ? 'transition-colors hover:bg-hover active:bg-sunken focus:outline-none focus-visible:bg-sunken ' : '').($active ? 'bg-sunken before:absolute before:inset-y-0 before:left-0 before:w-[3px] before:bg-brand' : '')]) }}>
    @if ($avatar)
        <x-ui.avatar-initials :name="$avatar" @class(['bg-surface' => $active]) />
    @elseif ($icon)
        <span @class(['inline-flex size-10 shrink-0 items-center justify-center rounded-lg', 'bg-sunken text-ink-2' => ! $tone, 'bg-bad-tint text-bad' => $tone === 'bad', 'bg-warn-tint text-warn' => $tone === 'warn', 'bg-ok-tint text-ok' => $tone === 'ok'])>
            <x-icon :name="$icon" class="size-5" />
        </span>
    @endif
    <div class="min-w-0 flex-1">
        <div @class(['truncate text-[15px] leading-5 font-semibold', 'text-bad' => $tone === 'bad', 'text-ink' => $tone !== 'bad'])>{{ $title }}</div>
        @if ($subtitle)
            <div class="mt-0.5 truncate text-[13px] leading-[18px] font-medium text-muted">{{ $subtitle }}</div>
        @endif
        {{ $slot }}
    </div>
    @if ($trailing)
        <div class="shrink-0 text-right">{{ $trailing }}</div>
    @elseif ($href)
        <x-icon name="chevron-right" class="size-4 shrink-0 text-faint" />
    @endif
</{{ $tag }}>
