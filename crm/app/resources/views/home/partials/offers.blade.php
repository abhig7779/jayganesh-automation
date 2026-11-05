{{-- Latest offers and how far each has gone out (Dashboard\Offers::recent). Owner / Marketing. --}}
<x-ui.panel title="Offers" subtitle="Latest WhatsApp offers">
    <x-slot:aside>
        @if (\Illuminate\Support\Facades\Route::has('offers.index') && $offers)
            <a href="{{ route('offers.index') }}" class="text-[13px] font-semibold hover:underline">All offers</a>
        @endif
    </x-slot:aside>
    @if (empty($offers))
        <div class="flex flex-col items-start gap-3">
            <p class="text-[13px] font-medium text-muted">No offers sent yet. Pick customers and send one on WhatsApp.</p>
            @if (\Illuminate\Support\Facades\Route::has('offers.create'))
                <a href="{{ route('offers.create') }}" class="btn-ghost min-h-10 px-3 text-[13px] lg:min-h-8"><x-icon name="megaphone" class="size-4" /> New offer</a>
            @endif
        </div>
    @else
        <ul class="-mx-4 divide-y divide-line border-t border-line lg:-mx-5">
            @foreach ($offers as $o)
                @php $done = $o['sent'] + $o['skipped']; $tone = in_array($o['status'], ['sent', 'exported'], true) ? 'ok' : ($o['status'] === 'sending' ? 'warn' : 'muted'); @endphp
                <li>
                    <a href="{{ $o['url'] }}" class="flex items-center gap-3 px-4 py-2.5 hover:bg-hover lg:px-5">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold">{{ $o['title'] }}</div>
                            <div class="truncate text-xs font-medium text-muted">
                                {{ $o['created_at'] }} · {{ $o['method'] }} · {{ $o['sent'] }} of {{ $o['recipients'] }} sent @if ($o['failed']) · <span class="text-bad">{{ $o['failed'] }} failed</span> @endif
                            </div>
                            @if ($o['recipients'] > 0)
                                <div class="mt-1.5 h-1 rounded-full bg-sunken"><div class="h-1 rounded-full bg-ink" style="width: {{ min(100, (int) round($done * 100 / max(1, $o['recipients']))) }}%"></div></div>
                            @endif
                        </div>
                        <x-ui.status-badge :tone="$tone">{{ $o['status_label'] }}</x-ui.status-badge>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-ui.panel>
