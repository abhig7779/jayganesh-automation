{{-- WhatsApp status tick (DESIGN §2.2): sending | sent | delivered | read | failed. --}}
@switch($state)
    @case('sending') <span class="inline-flex items-center gap-1 text-xs font-semibold text-faint"><x-icon name="clock" class="size-3.5" /> Sending</span> @break
    @case('sent') <span class="inline-flex items-center gap-1 text-xs font-semibold text-muted"><x-icon name="check" class="size-3.5" stroke="2" /> Sent</span> @break
    @case('delivered') <span class="inline-flex items-center gap-1 text-xs font-semibold text-ink"><x-icon name="check" class="size-3.5" stroke="2" /><x-icon name="check" class="-ml-2.5 size-3.5" stroke="2" /> Delivered</span> @break
    @case('read') <span class="inline-flex items-center gap-1 text-xs font-semibold text-ok"><x-icon name="check" class="size-3.5" stroke="2.4" /><x-icon name="check" class="-ml-2.5 size-3.5" stroke="2.4" /> Read</span> @break
    @case('failed') <span class="inline-flex items-center gap-1 text-xs font-semibold text-bad"><x-icon name="alert" class="size-3.5" /> Failed · Resend</span> @break
    @default <span class="text-xs text-faint">—</span>
@endswitch
