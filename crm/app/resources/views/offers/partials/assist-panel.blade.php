@php $pct = $total ? round($done / $total * 100) : 0; @endphp
<div class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_340px]" x-data="{ opened: false }">
    <div class="min-w-0 space-y-5">
        <div class="card">
            <div class="flex items-baseline justify-between gap-2">
                <div class="text-sm font-semibold">Progress</div>
                <div class="text-sm tabular-nums text-zinc-700" data-progress>{{ $done }} of {{ $total }} done</div>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $done }}">
                <div class="h-full bg-ink" style="width: {{ $pct }}%"></div>
            </div>
        </div>

        @if ($current)
            <div class="card space-y-4" data-current="{{ $current->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Next customer · {{ $done + 1 }} of {{ $total }}</div>
                        <div class="mt-1 text-2xl font-bold">{{ $current->name }}</div>
                        <div class="text-sm text-zinc-700">
                            <span class="tabular-nums">{{ $pretty($current->mobile) }}</span>
                            @if ($customer?->cars->first()) · {{ $customer->cars->first()->label() }} @endif
                            @if ($customer?->area) · {{ $customer->area }} @endif
                        </div>
                    </div>
                </div>
                <div class="whitespace-pre-line break-words rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-[15px] leading-snug">{{ $text }}</div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ $link }}" target="_blank" rel="noopener" class="btn-accent px-6" @click="opened = true">
                        <x-icon name="chat" /> 1. Open WhatsApp
                    </a>
                    <form hx-post="{{ route('offers.recipients.mark', [$offer, $current]) }}" hx-target="#assist-panel" method="POST" action="{{ route('offers.recipients.mark', [$offer, $current]) }}" class="contents">
                        @csrf
                        <button name="status" value="sent" class="btn-primary px-6" :class="opened ? '' : 'opacity-60'">2. Sent ✓</button>
                        <button name="status" value="skipped" class="btn-ghost">Skip</button>
                    </form>
                </div>
                <p class="text-xs text-zinc-600">Skip if the number isn't on WhatsApp or the customer shouldn't get this one.</p>
            </div>
        @else
            <div class="card space-y-3">
                <h2 class="text-lg font-semibold">All done</h2>
                <p class="text-sm text-zinc-700">Every customer on this offer is marked sent or skipped.</p>
                <a href="{{ route('offers.show', $offer) }}" class="btn-ghost">See the results</a>
            </div>
        @endif
    </div>

    <aside class="card">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Just done</h2>
        @if ($recent->isEmpty())
            <p class="mt-2 text-sm text-zinc-600">Nothing yet. The first customer is on the left.</p>
        @else
            <ul class="mt-2 divide-y divide-zinc-100">
                @foreach ($recent as $r)
                    <li class="flex items-center justify-between gap-2 py-2 text-sm">
                        <span class="min-w-0 truncate">{{ $r->name }}</span>
                        <span class="flex shrink-0 items-center gap-2">
                            <span @class(['badge border', 'border-green-200 bg-green-50 text-green-700' => $r->status === 'sent', 'border-zinc-300 bg-white text-zinc-700' => $r->status !== 'sent'])>{{ $r->status === 'sent' ? 'Sent' : 'Skipped' }}</span>
                            <form hx-post="{{ route('offers.recipients.mark', [$offer, $r]) }}" hx-target="#assist-panel" method="POST" action="{{ route('offers.recipients.mark', [$offer, $r]) }}">
                                @csrf
                                <button name="status" value="pending" class="text-xs font-medium text-zinc-600 underline hover:text-ink">Undo</button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </aside>
</div>
