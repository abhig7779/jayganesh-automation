@php use App\Services\Offers\SegmentBuilder; @endphp
<x-layouts.app title="Offers">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold">Offers</h1>
            <p class="text-sm text-zinc-600">Send an offer on WhatsApp to the right customers. Free methods first; opted-out customers are always left out.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('audiences.export') }}" class="btn-ghost">Meta ads audience</a>
            <a href="{{ route('offers.create') }}" class="btn-accent"><x-icon name="plus" /> New offer</a>
        </div>
    </x-slot:header>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="card min-w-0 overflow-hidden p-0 sm:p-0">
            @if ($offers->isEmpty())
                <div class="flex flex-col items-start gap-3 p-6">
                    <x-icon name="chat" class="size-8 text-zinc-500" />
                    <p class="font-medium text-zinc-700">No offers yet. Make the first one: pick customers, write the message, export a free broadcast list.</p>
                    <a href="{{ route('offers.create') }}" class="btn-ghost"><x-icon name="plus" /> New offer</a>
                </div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="bg-zinc-100 text-xs font-semibold uppercase tracking-wider text-zinc-600">
                        <tr>
                            <th class="px-5 py-3">Offer</th>
                            <th class="px-3 py-3">How</th>
                            <th class="px-3 py-3 text-right">Customers</th>
                            <th class="px-3 py-3 text-right">Sent</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Made</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($offers as $o)
                            <tr class="hover:bg-zinc-50">
                                <td class="px-5 py-3"><a href="{{ route('offers.show', $o) }}" class="font-semibold text-ink hover:underline">{{ $o->title }}</a></td>
                                <td class="px-3 py-3 text-zinc-700">{{ $o->send_method ? $o->methodLabel() : '—' }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ number_format($o->recipient_count) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ $o->send_method === 'broadcast' ? '—' : number_format($o->sent_count) }}</td>
                                <td class="px-3 py-3"><span class="badge {{ $o->statusClasses() }}">{{ $o->statusLabel() }}</span></td>
                                <td class="whitespace-nowrap px-5 py-3 text-right text-zinc-600">{{ $o->created_at->timezone('Asia/Kolkata')->format('d M Y') }}<div class="text-xs">{{ $o->creator?->name }}</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                @if ($offers->hasPages()) <div class="border-t border-zinc-100 px-5 py-3">{{ $offers->links() }}</div> @endif
            @endif
        </div>

        <aside class="space-y-3">
            <h2 class="px-1 text-xs font-semibold uppercase tracking-wider text-zinc-600">Saved customer lists</h2>
            @forelse ($saved as $s)
                <div class="card">
                    <div class="flex items-start justify-between gap-2">
                        <div class="font-semibold">{{ $s->name }}</div>
                        <form method="POST" action="{{ route('segments.destroy', $s) }}" data-confirm="Remove the saved list “{{ $s->name }}”? Offers already sent keep their own list.">
                            @csrf @method('DELETE')
                            <button class="text-xs font-medium text-zinc-600 hover:text-red-600" aria-label="Remove {{ $s->name }}">Remove</button>
                        </form>
                    </div>
                    <p class="mt-1 text-sm text-zinc-700">{{ implode(' · ', $s->summary) }}</p>
                    <p class="mt-1 text-sm font-medium tabular-nums text-zinc-600">{{ SegmentBuilder::countLine($s->count) }}</p>
                    <a href="{{ route('offers.create', ['segment' => $s->id]) }}" class="mt-3 inline-block text-sm font-semibold text-ink underline decoration-brand-400 decoration-2 underline-offset-4">Send an offer to this list</a>
                </div>
            @empty
                <div class="card text-sm text-zinc-700">No saved lists. While making an offer, use “Save these filters for next time”.</div>
            @endforelse
        </aside>
    </div>
</x-layouts.app>
