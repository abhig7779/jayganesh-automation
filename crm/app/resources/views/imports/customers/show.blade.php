@php $problems = collect($import->problems ?? []); @endphp
<x-layouts.app title="Import customers">
    <x-slot:header>
        <div>
            <a href="{{ route('customers.import') }}" class="text-sm font-semibold text-zinc-700 hover:text-ink">← Import customers</a>
            <h1 class="mt-1 text-[22px] font-bold leading-7">{{ $import->isDone() ? 'Import finished' : 'Importing customers…' }}</h1>
            <p class="text-sm text-zinc-600">{{ $import->file_name }} · <span class="tabular-nums">{{ number_format($import->total_rows) }}</span> rows</p>
        </div>
    </x-slot:header>

    @unless ($import->isDone())
        {{-- Each slice of 250 rows is its own request, so a 5,000-row file never hits the server's time limit. --}}
        <form method="POST" action="{{ route('customers.import.continue', $import) }}"
              x-data x-init="$nextTick(() => $el.requestSubmit())"
              class="rounded-xl border border-zinc-200 bg-white p-5" data-import-progress>
            @csrf
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Step 3 of 3</p>
            <h2 class="mt-1 text-[17px] font-semibold">Saving <span class="tabular-nums">{{ number_format($import->processed) }}</span> of <span class="tabular-nums">{{ number_format($import->total_rows) }}</span></h2>
            <div class="mt-3 h-3 overflow-hidden rounded-full bg-zinc-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $import->percent() }}">
                <div class="h-full bg-ink" style="width: {{ $import->percent() }}%"></div>
            </div>
            <p class="mt-3 text-sm text-zinc-600">Keep this page open. It carries on by itself; if it stops, press the button.</p>
            <button class="btn-ghost mt-3">Carry on importing</button>
        </form>
    @else
        <section class="rounded-xl border border-zinc-200 bg-white p-5" data-import-summary>
            <h2 class="text-[17px] font-semibold">Done: {{ $import->file_name }}</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2"><div class="text-2xl font-bold tabular-nums text-green-700">{{ number_format($import->created) }}</div><div class="text-[13px] text-green-800">new customers</div></div>
                <div class="rounded-lg border border-zinc-200 px-3 py-2"><div class="text-2xl font-bold tabular-nums">{{ number_format($import->updated) }}</div><div class="text-[13px] text-zinc-600">got new details</div></div>
                <div class="rounded-lg border border-zinc-200 px-3 py-2"><div class="text-2xl font-bold tabular-nums">{{ number_format($import->cars_added) }}</div><div class="text-[13px] text-zinc-600">cars added</div></div>
                <div class="rounded-lg border border-zinc-200 px-3 py-2"><div class="text-2xl font-bold tabular-nums">{{ number_format($import->unchanged) }}</div><div class="text-[13px] text-zinc-600">already the same</div></div>
                <div @class(['rounded-lg border px-3 py-2', 'border-red-200 bg-red-50' => $import->skipped > 0, 'border-zinc-200' => $import->skipped === 0])><div @class(['text-2xl font-bold tabular-nums', 'text-red-600' => $import->skipped > 0])>{{ number_format($import->skipped) }}</div><div class="text-[13px] text-zinc-600">rows skipped</div></div>
            </div>
            @if ($problems->isNotEmpty())
                <details class="mt-4" @if ($problems->count() <= 10) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold">Rows to look at ({{ number_format($problems->count()) }})</summary>
                    <ul class="mt-2 max-h-72 divide-y divide-zinc-200 overflow-y-auto rounded-lg border border-zinc-200 text-[13px]">
                        @foreach ($problems as $p)
                            <li class="flex gap-3 px-3 py-1.5"><span class="w-16 shrink-0 tabular-nums text-zinc-600">Row {{ $p['line'] }}</span><span class="text-red-700">{{ $p['reason'] }}</span></li>
                        @endforeach
                    </ul>
                </details>
            @endif
            <p class="mt-4 text-[13px] text-zinc-600">New customers are tagged <strong>imported</strong>. WhatsApp: bills and service messages on; offers off until they agree.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('customers.index') }}" class="btn-primary">See customers</a>
                <a href="{{ route('customers.import') }}" class="btn-ghost">Import another file</a>
            </div>
        </section>
    @endunless
</x-layouts.app>
