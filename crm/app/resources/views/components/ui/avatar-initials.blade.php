{{--
    <x-ui.avatar-initials name="Rahul Pawar" size="md" />
    A square of initials (DESIGN §5 list row). Sizes: sm 32, md 40, lg 56.
    tone: light (default, `sunken` + ink-2), dark (ink + white, the top-bar account button),
    sidebar (translucent white on the ink sidebar). Brackets and symbols in names are ignored.
--}}
@props(['name' => '', 'size' => 'md', 'tone' => 'light'])
@php
    $clean = trim(preg_replace('/\([^)]*\)|[^\p{L}\s]/u', ' ', (string) $name));
    $words = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
    $initials = mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1).(count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
    $sizes = ['sm' => 'size-8 text-[11px] rounded-md', 'md' => 'size-10 text-[13px] rounded-lg', 'lg' => 'size-14 text-lg rounded-xl'];
    $tones = ['light' => 'bg-sunken text-ink-2', 'dark' => 'bg-ink text-white', 'sidebar' => 'bg-white/10 text-white'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center justify-center font-bold '.($sizes[$size] ?? $sizes['md']).' '.($tones[$tone] ?? $tones['light'])]) }} aria-hidden="true">{{ $initials }}</span>
