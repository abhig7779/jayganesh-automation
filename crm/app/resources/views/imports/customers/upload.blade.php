<x-layouts.app title="Import customers">
    <x-slot:header>
        <div>
            <a href="{{ route('customers.index') }}" class="text-sm font-semibold text-zinc-700 hover:text-ink">← Customers</a>
            <h1 class="mt-1 text-[22px] font-bold leading-7">Import customers</h1>
        </div>
    </x-slot:header>

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <form method="POST" action="{{ route('customers.import.upload') }}" enctype="multipart/form-data"
              x-data="{ name: '', busy: false }" @submit="busy = true"
              class="self-start rounded-xl border border-zinc-200 bg-white p-5" data-import-upload>
            @csrf
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Step 1 of 3</p>
            <h2 class="mt-1 text-[17px] font-semibold">Choose the file</h2>
            <p class="mt-1 text-sm text-zinc-600">An Excel sheet or CSV of old customers, or the contacts exported from a phone or Google Contacts. Up to 5,000 people per file. Nothing is saved until you check the rows and confirm.</p>

            <label class="mt-4 flex min-h-36 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 bg-zinc-50 px-4 text-center hover:border-ink"
                   @dragover.prevent @drop.prevent="$refs.file.files = $event.dataTransfer.files; name = $event.dataTransfer.files[0]?.name || ''">
                <svg class="size-8 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                <span class="text-[15px] font-semibold" x-text="name || 'Click to choose, or drop the file here'">Click to choose, or drop the file here</span>
                <span class="text-xs text-zinc-600">.xlsx · .csv · .vcf (phone contacts)</span>
                <input x-ref="file" type="file" name="file" accept=".xlsx,.csv,.txt,.ods,.xls,.vcf" class="sr-only" required @change="name = $event.target.files[0]?.name || ''">
            </label>
            @error('file') <p class="error">{{ $message }}</p> @enderror

            <button class="btn-accent mt-4 min-h-12 w-full sm:w-auto" :disabled="busy">
                <span x-show="!busy">Next: match columns</span>
                <span x-show="busy" x-cloak>Reading the file…</span>
            </button>
        </form>

        <aside class="space-y-4 text-sm">
            <section class="rounded-xl border border-zinc-200 bg-white p-5">
                <h2 class="text-[15px] font-semibold">Any column names work</h2>
                <p class="mt-1 text-zinc-600">We guess <em>Name</em>, <em>Mobile</em>, <em>Car</em>, <em>Number plate</em>, <em>Area</em> and <em>Notes</em>. You can change any guess on the next step.</p>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-zinc-700">
                    <li>People are matched by <strong>mobile number</strong>, typed any way. Someone already saved only gets the details that are empty now; nothing is overwritten.</li>
                    <li>A car or number plate column adds the car to the customer.</li>
                    <li>Rows with a wrong number, or a number that's already higher up in the file, are shown in red and skipped.</li>
                </ul>
                <a href="{{ route('customers.import.template') }}" class="btn-ghost mt-4 w-full">Download a blank template (.csv)</a>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5" data-optin-note>
                <h2 class="text-[15px] font-semibold">WhatsApp: service yes, offers no</h2>
                <p class="mt-1 text-zinc-700">Imported customers get bills, receipts and "car ready" messages on WhatsApp. They are <strong>not</strong> added to offers and marketing, because they never agreed to them. They join offers only after they say yes.</p>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-5">
                <h2 class="text-[15px] font-semibold">Getting contacts off a phone</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-zinc-700">
                    <li><strong>Android:</strong> Contacts → Fix &amp; manage → Export to file (.vcf).</li>
                    <li><strong>Google Contacts:</strong> contacts.google.com → Export → Google CSV.</li>
                    <li><strong>iPhone:</strong> icloud.com/contacts → select all → Export vCard.</li>
                </ul>
            </section>
        </aside>
    </div>

    <section class="mt-6">
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-600">Recent imports</h2>
        @if ($recent->isEmpty())
            <p class="rounded-xl border border-zinc-200 bg-white px-4 py-6 text-center text-sm text-zinc-600">No imports yet. Your first file will show here.</p>
        @else
            <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white text-sm">
                @foreach ($recent as $r)
                    <li>
                        <a href="{{ route('customers.import.show', $r) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-zinc-50">
                            <span class="font-medium text-ink">{{ $r->file_name }}</span>
                            <span class="text-zinc-600">{{ $r->created_at->timezone('Asia/Kolkata')->format('j M, g:i a') }} · {{ $r->user?->name }}</span>
                            @if ($r->isDone())
                                <span class="ml-auto tabular-nums text-zinc-700">{{ number_format($r->created) }} new · {{ number_format($r->updated) }} updated · {{ number_format($r->skipped) }} skipped</span>
                            @else
                                <span class="ml-auto inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 text-[11px] font-semibold leading-5 text-amber-700">Not finished: {{ $r->percent() }}%</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
