@php
    use App\Models\OfferRecipient;
    use App\Services\Offers\OfferMessage;
    use App\Services\Offers\SegmentBuilder;
    use App\Support\Money;
    use App\Support\Phone;
    $parts = max(1, (int) ceil($offer->recipient_count / $listLimit));
    $broadcastText = OfferMessage::generic($offer->message);
    $tiles = [
        ['Customers', $offer->recipient_count, 'text-ink'],
        ['Sent', $byStatus['sent'] ?? 0, 'text-green-700'],
        ['Not sent yet', ($byStatus['pending'] ?? 0) + ($byStatus['queued'] ?? 0), 'text-amber-700'],
        ['Skipped', $byStatus['skipped'] ?? 0, 'text-zinc-700'],
        ['Failed', $byStatus['failed'] ?? 0, 'text-red-600'],
    ];
    if ($offer->send_method === 'broadcast') {
        $tiles = [['Customers', $offer->recipient_count, 'text-ink'], ['In broadcast list', $byStatus['exported'] ?? 0, 'text-green-700'], ['Opted out, left out', $offer->opted_out_excluded, 'text-zinc-700']];
    }
@endphp
<x-layouts.app :title="$offer->title">
    <x-slot:header>
        <div class="min-w-0">
            <a href="{{ route('offers.index') }}" class="text-sm font-medium text-zinc-600 hover:text-ink">← Offers</a>
            <h1 class="mt-1 flex flex-wrap items-center gap-3 text-2xl font-bold">{{ $offer->title }} <span class="badge {{ $offer->statusClasses() }}">{{ $offer->statusLabel() }}</span></h1>
            <p class="text-sm text-zinc-600">{{ $offer->methodLabel() }} · made by {{ $offer->creator?->name ?? '—' }} on {{ $offer->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</p>
        </div>
        <a href="{{ route('offers.create', ['copy' => $offer->id]) }}" class="btn-ghost">Copy into a new offer</a>
    </x-slot:header>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="min-w-0 space-y-5">

            @if ($offer->send_method)
                <div class="grid grid-cols-2 gap-3 {{ count($tiles) === 3 ? 'md:grid-cols-3' : 'md:grid-cols-5' }}">
                    @foreach ($tiles as [$label, $value, $colour])
                        <div class="card">
                            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-600">{{ $label }}</div>
                            <div class="mt-1 text-2xl font-bold tabular-nums {{ $colour }}">{{ number_format($value) }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- What to do next, per method --}}
            @if (! $offer->send_method)
                <form method="POST" action="{{ route('offers.start', $offer) }}" class="card space-y-4" x-data="{ method: '{{ old('method', 'broadcast') }}' }">
                    @csrf
                    <div>
                        <h2 class="text-lg font-semibold">How to send</h2>
                        <p class="text-sm text-zinc-600">This draft goes to <strong class="text-ink">{{ SegmentBuilder::countLine($liveCount) }}</strong> right now. The list is fixed when you start.</p>
                    </div>
                    @include('offers.partials.methods', ['count' => $liveCount['eligible']])
                    <div class="flex justify-end">
                        <button class="btn-accent px-6">Start</button>
                    </div>
                </form>
            @elseif ($offer->send_method === 'broadcast')
                <div class="card space-y-4">
                    <h2 class="text-lg font-semibold">Send it from the WhatsApp Business app</h2>
                    <ol class="list-decimal space-y-2 pl-5 text-sm text-zinc-800">
                        <li><strong>Download the list</strong> below. It opens in Excel and imports into Google Contacts on the shop phone (contacts.google.com → Import).</li>
                        <li>In WhatsApp Business: <strong>Chats → ⋮ → New broadcast</strong>, tick these contacts (up to {{ $listLimit }} per list).</li>
                        <li><strong>Copy the message</strong> and paste it into the broadcast. Only customers who saved the shop number get broadcasts, so ask them to save it.</li>
                    </ol>
                    <div class="flex flex-wrap gap-2">
                        @for ($p = 1; $p <= $parts; $p++)
                            <a href="{{ route('offers.broadcast', [$offer, 'part' => $p]) }}" @class(['btn-accent' => $p === 1, 'btn-ghost' => $p > 1])>
                                Download list{{ $parts > 1 ? " part $p of $parts" : '' }} (CSV)
                            </a>
                        @endfor
                    </div>
                    @if ($offer->exported_at)
                        <p class="text-sm text-zinc-600">First downloaded {{ $offer->exported_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}.</p>
                    @endif
                </div>
            @elseif ($offer->send_method === 'assisted')
                @php $left = $byStatus['pending'] ?? 0; $doneN = ($byStatus['sent'] ?? 0) + ($byStatus['skipped'] ?? 0); @endphp
                <div class="card flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold">{{ $left ? "$left customers left to send" : 'Everyone is done' }}</h2>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200"><div class="h-full bg-ink" style="width: {{ $offer->recipient_count ? round($doneN / $offer->recipient_count * 100) : 0 }}%"></div></div>
                        <p class="mt-1 text-sm tabular-nums text-zinc-600">{{ $doneN }} of {{ $offer->recipient_count }} done</p>
                    </div>
                    <a href="{{ route('offers.assist', $offer) }}" class="{{ $left ? 'btn-accent' : 'btn-ghost' }} px-6">{{ $left ? ($doneN ? 'Carry on sending' : 'Start sending') : 'Open the sender' }}</a>
                </div>
            @elseif ($offer->send_method === 'automatic')
                <div class="card grid gap-3 text-sm md:grid-cols-3">
                    <div><div class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Template</div><div class="mt-1 font-mono">{{ $offer->template_name }}</div></div>
                    <div><div class="text-xs font-semibold uppercase tracking-wider text-zinc-600">When</div><div class="mt-1">{{ $offer->scheduled_at ? $offer->scheduled_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') : 'Sent straight away' }}</div></div>
                    <div><div class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Estimated cost</div><div class="mt-1 tabular-nums">{{ Money::format((int) $offer->estimated_cost_paise, true) }}</div></div>
                </div>
            @endif

            {{-- Recipients --}}
            @if ($offer->send_method)
                <div class="card overflow-hidden p-0 sm:p-0">
                    <div class="border-b border-zinc-100 px-5 py-3 text-sm font-semibold">Who gets it</div>
                    <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead class="bg-zinc-100 text-xs font-semibold uppercase tracking-wider text-zinc-600">
                            <tr><th class="px-5 py-2">Customer</th><th class="px-3 py-2">Mobile</th><th class="px-3 py-2">Car</th><th class="px-5 py-2">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($recipients as $r)
                                <tr>
                                    <td class="px-5 py-2 font-medium">{{ $r->name }}</td>
                                    <td class="px-3 py-2 tabular-nums text-zinc-700">{{ Phone::pretty($r->mobile) }}</td>
                                    <td class="px-3 py-2 text-zinc-700">{{ $r->customer?->cars->first()?->model ?? '—' }}</td>
                                    <td class="px-5 py-2">
                                        <span @class([
                                            'badge border',
                                            'border-green-200 bg-green-50 text-green-700' => in_array($r->status, ['sent', 'exported']),
                                            'border-amber-200 bg-amber-50 text-amber-700' => in_array($r->status, ['pending', 'queued']),
                                            'border-red-200 bg-red-50 text-red-700' => $r->status === 'failed',
                                            'border-zinc-300 bg-white text-zinc-700' => $r->status === 'skipped',
                                        ]) @if ($r->error) title="{{ $r->error }}" @endif>{{ OfferRecipient::STATUS_LABELS[$r->status] ?? $r->status }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                    @if ($recipients->hasPages()) <div class="border-t border-zinc-100 px-5 py-3">{{ $recipients->links() }}</div> @endif
                </div>
            @endif
        </div>

        <aside class="space-y-5">
            <div class="card" x-data="{ copied: false }">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Message</h2>
                    <button type="button" class="btn-ghost min-h-9 px-3 text-xs"
                            @click="navigator.clipboard.writeText($refs.copyText.innerText).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                        <span x-show="!copied">Copy message</span><span x-show="copied" x-cloak>Copied</span>
                    </button>
                </div>
                @if ($offer->send_method === 'broadcast')
                    <div x-ref="copyText" class="mt-3 whitespace-pre-line break-words rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-[15px] leading-snug">{{ $broadcastText }}</div>
                    <p class="mt-2 text-xs text-zinc-600">A broadcast sends the same text to everyone, so {name} becomes “there” and {car} becomes “car”.</p>
                @else
                    <div x-ref="copyText" class="mt-3 whitespace-pre-line break-words rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-[15px] leading-snug">{{ $sampleText }}</div>
                    <p class="mt-2 text-xs text-zinc-600">As {{ $sampleCustomer?->name ?? 'a sample customer' }} sees it. Each customer gets their own name and car.</p>
                @endif
            </div>
            <div class="card">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Audience</h2>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($summary as $chip)
                        <span class="badge border border-zinc-300 bg-white text-zinc-800">{{ $chip }}</span>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-zinc-700">{{ number_format($offer->opted_out_excluded) }} opted-out customers were left out.</p>
                @if ($offer->segment) <p class="mt-1 text-xs text-zinc-600">From the saved list “{{ $offer->segment->name }}”.</p> @endif
            </div>
        </aside>
    </div>
</x-layouts.app>
