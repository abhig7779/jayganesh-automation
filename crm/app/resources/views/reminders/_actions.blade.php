{{-- Row buttons: WhatsApp · Call · Mark done · Skip. $row from App\Support\Dashboard\Reminders::row(); $phone = bigger grid for phones. --}}
@php $phone = $phone ?? false; @endphp
@if ($row['open'] || $row['status'] === 'sent')
    <div @class(['grid grid-cols-2 gap-2' => $phone, 'flex flex-nowrap items-center justify-end gap-1.5' => ! $phone])>
        @if ($row['has_mobile'] && ! $row['opted_out'])
            @if ($row['auto'])
                <form method="POST" action="{{ $row['send_url'] }}" @class(['contents' => $phone])>
                    @csrf
                    <button class="btn-ghost {{ $phone ? 'w-full' : 'lg:min-h-9 lg:px-3' }}" title="Sends the approved WhatsApp template now">
                        <x-icon name="chat" class="size-4" /> {{ $row['status'] === 'sent' ? 'Send again' : 'WhatsApp' }}
                    </button>
                </form>
            @else
                <a href="{{ $row['wa_url'] }}" target="_blank" rel="noopener" class="btn-ghost {{ $phone ? 'w-full' : 'lg:min-h-9 lg:px-3' }}" title="Opens WhatsApp with the message typed in">
                    <x-icon name="chat" class="size-4" /> {{ $row['status'] === 'sent' ? 'Open again' : 'WhatsApp' }}
                </a>
            @endif
        @endif
        @if ($row['tel'])
            <a href="{{ $row['tel'] }}" class="btn-ghost {{ $phone ? 'w-full' : 'lg:min-h-9 lg:px-3' }}"><x-icon name="phone" class="size-4" /> Call</a>
        @endif
        <form method="POST" action="{{ $row['done_url'] }}" @class(['contents' => $phone])>
            @csrf
            <button class="btn-primary {{ $phone ? 'w-full' : 'lg:min-h-9 lg:px-3' }}"><x-icon name="check" class="size-4" /> Mark done</button>
        </form>
        <form method="POST" action="{{ $row['skip_url'] }}" @class(['contents' => $phone])>
            @csrf
            <button class="btn-quiet {{ $phone ? 'w-full border border-line' : 'lg:min-h-9' }} text-muted">Skip</button>
        </form>
    </div>
@else
    <div class="text-right text-[13px] text-muted">
        <x-ui.status-badge :tone="$row['status'] === 'done' ? 'ok' : 'muted'">{{ $row['status_label'] }}</x-ui.status-badge>
        @if ($row['note'])<div class="mt-1">{{ $row['note'] }}</div>@endif
    </div>
@endif
