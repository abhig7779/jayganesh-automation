@props(['dark' => false])
<kbd {{ $attributes->class([
    'inline-flex h-5 min-w-5 items-center justify-center rounded border px-1 font-mono text-[10px] font-semibold leading-none',
    'border-zinc-300 bg-white text-zinc-600' => ! $dark,
    'border-black/20 bg-ink text-white' => $dark,
]) }}>{{ $slot }}</kbd>
