{{--
    WhatsApp status for one message, to drop next to a bill or quotation:
        <x-whatsapp.status :log="$invoice->latestWhatsApp" />
    ✓ sent (grey), ✓✓ delivered (ink), ✓✓ read (WhatsApp blue, the only blue in the app),
    ⚠ failed with the plain-English reason + Resend / Send manually.
    Manual mode: a "Send on WhatsApp" button that opens WhatsApp with the message typed in.
--}}
@props(['log' => null, 'compact' => false])
@php
    // Same rule as the whatsapp.resend / whatsapp.manual routes (owner and admin have invoices.create).
    $canAct = (bool) auth()->user()?->can('invoices.create');
@endphp
@if (! $log)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs font-semibold text-zinc-400']) }}>Not sent on WhatsApp</span>
@else
<span {{ $attributes->merge(['class' => 'inline-flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-semibold']) }} data-whatsapp-status="{{ $log->status }}">
    @switch($log->status)
        @case('sent')
            <span class="text-zinc-500" title="Sent {{ $log->sent_at?->timezone('Asia/Kolkata')->format('d M, h:i A') }}"><span aria-hidden="true">✓</span> Sent</span>
            @break
        @case('delivered')
            <span class="text-ink" title="Delivered {{ $log->delivered_at?->timezone('Asia/Kolkata')->format('d M, h:i A') }}"><span aria-hidden="true">✓✓</span> Delivered</span>
            @break
        @case('read')
            <span class="text-ink" title="Read {{ $log->read_at?->timezone('Asia/Kolkata')->format('d M, h:i A') }}"><span aria-hidden="true" style="color:#34B7F1">✓✓</span> Read</span>
            @break
        @case('failed')
            <span class="text-red-600"><span aria-hidden="true">⚠</span> Not sent</span>
            @unless ($compact)
                <span class="basis-full font-normal text-red-600">{{ $log->reason() }}</span>
            @endunless
            @if ($canAct)
                <form method="POST" action="{{ route('whatsapp.resend', $log) }}" class="inline">
                    @csrf
                    <button class="font-semibold text-ink underline decoration-red-300 decoration-2 underline-offset-4">Resend</button>
                </form>
                <a href="{{ app(\App\Services\WhatsApp\WhatsApp::class)->manualLink($log->to, $log->previewText()) }}" target="_blank" rel="noopener"
                   class="font-semibold text-ink underline decoration-zinc-300 decoration-2 underline-offset-4">Send manually</a>
            @endif
            @break
        @case('manual_opened')
            <span class="text-zinc-500"><span aria-hidden="true">✓</span> Opened in WhatsApp</span>
            @break
        @default
            @if ($log->isManual())
                @if ($canAct)
                    <form method="POST" action="{{ route('whatsapp.manual', $log) }}" target="_blank" class="inline">
                        @csrf
                        <button class="btn-ghost min-h-9 px-3 text-xs"><x-icon name="chat" class="size-4" /> Send on WhatsApp</button>
                    </form>
                @else
                    <span class="text-zinc-500">Ready to send</span>
                @endif
            @else
                <span class="text-zinc-400"><span aria-hidden="true">◷</span> Sending…</span>
                @if ($log->error_code && ! $compact)
                    <span class="basis-full font-normal text-amber-700">{{ $log->reason() }}</span>
                @endif
            @endif
    @endswitch
</span>
@endif
