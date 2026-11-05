{{--
    <x-ui.status-badge status="paid" />   or   <x-ui.status-badge tone="warn">Low stock</x-ui.status-badge>
    Round badge: tint + text + border (DESIGN §5 Status badge). Known statuses map to their tone and
    label (paid, due, part_paid, draft, cancelled, failed, sent, viewed, won, lost, expired, open,
    overdue, waiting, working, qc, ready, delivered, archived, soon, active, off). Anything else
    shows as written, in muted. Tones: ok | warn | bad | ink | muted.
--}}
@props(['status' => null, 'tone' => null])
@php
    $map = [
        'paid' => ['ok', 'Paid'], 'due' => ['bad', 'Due'], 'part_paid' => ['warn', 'Part paid'],
        'draft' => ['warn', 'Draft'], 'cancelled' => ['bad', 'Cancelled'], 'failed' => ['bad', 'Failed'],
        'sent' => ['ink', 'Sent'], 'viewed' => ['ink', 'Viewed'], 'won' => ['ok', 'Won'], 'lost' => ['muted', 'Lost'],
        'expired' => ['muted', 'Expired'], 'open' => ['ink', 'Open'], 'overdue' => ['bad', 'Overdue'],
        'waiting' => ['muted', 'Waiting'], 'working' => ['warn', 'Working'], 'qc' => ['ink', 'QC'],
        'ready' => ['ok', 'Ready'], 'delivered' => ['muted', 'Delivered'], 'archived' => ['muted', 'Archived'],
        'soon' => ['muted', 'Soon'], 'active' => ['ok', 'Active'], 'off' => ['bad', 'Off'],
    ];
    [$mappedTone, $label] = $map[$status] ?? ['muted', $status ? ucfirst(str_replace('_', ' ', (string) $status)) : ''];
    $tone = $tone ?? $mappedTone;
@endphp
<span {{ $attributes->merge(['class' => 'badge badge-'.$tone]) }}>{{ $slot->isEmpty() ? $label : $slot }}</span>
