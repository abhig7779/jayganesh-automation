@props(['status' => null, 'send' => true])
{{-- WhatsApp tick (DESIGN.md §2.2). Until the WhatsApp module records a status, shows a neutral placeholder. --}}
@php
    $states = [
        'sending' => ['Sending…', 'text-zinc-500', 'clock'],
        'queued' => ['Sending…', 'text-zinc-500', 'clock'],
        'sent' => ['Sent ✓', 'text-zinc-600', null],
        'delivered' => ['Delivered ✓✓', 'text-ink', null],
        'read' => ['Read ✓✓', 'text-green-700', null],
        'failed' => ['Not sent', 'text-red-600', 'alert'],
    ];
@endphp
@if (! $send)
    <span {{ $attributes->class('text-[11px] font-semibold text-zinc-500') }}>No WhatsApp</span>
@elseif ($status && isset($states[$status]))
    <span {{ $attributes->class(['inline-flex items-center gap-1 text-[11px] font-semibold', $states[$status][1]]) }}>
        @if ($states[$status][2])<x-bills.icon :name="$states[$status][2]" class="size-3.5" />@endif{{ $states[$status][0] }}
    </span>
@else
    <span {{ $attributes->class('inline-flex items-center gap-1 text-[11px] font-semibold text-zinc-500') }} title="Shows sent / delivered / read once WhatsApp sending is switched on"><x-bills.icon name="chat" class="size-3.5" />WhatsApp</span>
@endif
