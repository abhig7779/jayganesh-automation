<x-layouts.app title="WhatsApp log">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold">WhatsApp log</h1>
            <p class="text-sm text-zinc-500">Every bill, receipt and message sent on WhatsApp, with its ticks. Failed ones can be sent again.</p>
        </div>
    </x-slot:header>

    @if (session('error'))
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    @if ($setup)
        @include('whatsapp.messages.setup', ['setup' => $setup])
    @endif

    <form method="GET" action="{{ route('whatsapp.messages.index') }}" class="card mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2">
            <label class="label" for="q">Mobile</label>
            <input class="input" id="q" name="q" inputmode="numeric" value="{{ $filters['q'] ?? '' }}" placeholder="Last digits, e.g. 07676">
        </div>
        <div>
            <label class="label" for="status">Status</label>
            <select class="input" id="status" name="status">
                <option value="">All</option>
                @foreach (\App\Models\MessageLog::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="template">Template</label>
            <select class="input" id="template" name="template">
                <option value="">All</option>
                @foreach ($templates as $t)
                    <option value="{{ $t }}" @selected(($filters['template'] ?? '') === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label" for="from">From</label>
            <input class="input" id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div>
            <label class="label" for="to">To</label>
            <input class="input" id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
        </div>
        <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-6">
            <button class="btn-primary">Show</button>
            @if (array_filter($filters))
                <a href="{{ route('whatsapp.messages.index') }}" class="btn-ghost">Clear filters</a>
            @endif
        </div>
    </form>

    <div class="card overflow-hidden p-0 sm:p-0">
        @if ($messages->isEmpty())
            <div class="px-5 py-12 text-center">
                <p class="font-semibold">No WhatsApp messages {{ array_filter($filters) ? 'match these filters' : 'yet' }}.</p>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ array_filter($filters) ? 'Try clearing the filters.' : 'E-bills appear here as soon as the first bill is saved.' }}
                </p>
            </div>
        @else
            <table class="w-full text-left text-sm">
                <thead class="hidden bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 sm:table-header-group">
                    <tr><th class="px-5 py-3">When</th><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Message</th><th class="px-5 py-3">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($messages as $m)
                        <tr class="block px-4 py-3 sm:table-row sm:p-0">
                            <td class="block text-xs text-zinc-500 sm:table-cell sm:px-5 sm:py-3 sm:text-sm">
                                {{ $m->created_at->timezone('Asia/Kolkata')->format('d M, h:i A') }}
                                <div class="text-xs text-zinc-400">{{ $m->direction === 'in' ? 'Received' : ($m->isManual() ? 'Manual' : 'Cloud API') }}</div>
                            </td>
                            <td class="block font-semibold sm:table-cell sm:px-5 sm:py-3">
                                {{ $m->customer?->name ?? ($m->bodyValue('profile_name') ?: 'Unknown') }}
                                <div class="text-xs font-normal tabular-nums text-zinc-500">{{ \App\Support\Phone::pretty($m->to) }}</div>
                            </td>
                            <td class="block py-1 sm:table-cell sm:max-w-md sm:px-5 sm:py-3">
                                @if ($m->template)
                                    <span class="badge bg-zinc-100 text-zinc-700">{{ $m->template }}</span>
                                    <span @class(['badge', 'bg-amber-50 text-amber-700' => $m->category === 'marketing', 'bg-zinc-50 text-zinc-500' => $m->category !== 'marketing'])>{{ ucfirst($m->category) }}</span>
                                @endif
                                @if ($m->document_name)
                                    <span class="text-xs text-zinc-500">{{ $m->document_name }}</span>
                                @endif
                                <p class="mt-1 line-clamp-2 text-zinc-600">{{ $m->previewText() }}</p>
                            </td>
                            <td class="block sm:table-cell sm:px-5 sm:py-3">
                                @if ($m->direction === 'in')
                                    @if ($m->bodyValue('keyword') === 'stop')
                                        <span class="badge bg-red-50 text-red-700">Opted out (STOP)</span>
                                    @elseif ($m->bodyValue('keyword') === 'start')
                                        <span class="badge bg-emerald-50 text-emerald-700">Opted back in (START)</span>
                                    @else
                                        <span class="text-xs font-semibold text-zinc-500">Received</span>
                                    @endif
                                @else
                                    <x-whatsapp.status :log="$m" />
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-4">{{ $messages->links() }}</div>
</x-layouts.app>
