<x-layouts.app title="Import price list">
    <x-slot:header>
        <div>
            <a href="{{ route('products.index') }}" class="text-sm font-semibold text-zinc-700 hover:text-ink">← Stock &amp; prices</a>
            <h1 class="mt-1 text-[22px] font-bold leading-7">Import price list</h1>
        </div>
    </x-slot:header>

    @if ($summary)
        @php $skipped = collect($summary['skipped']); @endphp
        <section class="mb-6 rounded-xl border border-zinc-200 bg-white p-5" data-import-summary>
            <h2 class="text-[17px] font-semibold">Done: {{ $summary['file'] }}</h2>
            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2"><div class="text-2xl font-bold tabular-nums text-green-700">{{ $summary['created'] }}</div><div class="text-[13px] text-green-800">new items added</div></div>
                <div class="rounded-lg border border-zinc-200 px-3 py-2"><div class="text-2xl font-bold tabular-nums">{{ $summary['updated'] }}</div><div class="text-[13px] text-zinc-600">items updated</div></div>
                <div class="rounded-lg border border-zinc-200 px-3 py-2"><div class="text-2xl font-bold tabular-nums">{{ $summary['same'] }}</div><div class="text-[13px] text-zinc-600">already the same</div></div>
                <div @class(['rounded-lg border px-3 py-2', 'border-red-200 bg-red-50' => $skipped->isNotEmpty(), 'border-zinc-200' => $skipped->isEmpty()])><div @class(['text-2xl font-bold tabular-nums', 'text-red-600' => $skipped->isNotEmpty()])>{{ $skipped->count() }}</div><div class="text-[13px] text-zinc-600">rows skipped</div></div>
            </div>
            @if ($skipped->isNotEmpty())
                <details class="mt-4" @if ($skipped->count() <= 10) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold">Why rows were skipped</summary>
                    <ul class="mt-2 max-h-64 divide-y divide-zinc-200 overflow-y-auto rounded-lg border border-zinc-200 text-[13px]">
                        @foreach ($skipped as $s)
                            <li class="flex gap-3 px-3 py-1.5"><span class="w-16 shrink-0 tabular-nums text-zinc-600">Row {{ $s['line'] }}</span><span class="text-red-700">{{ $s['reason'] }}</span></li>
                        @endforeach
                    </ul>
                </details>
            @endif
            <div class="mt-4 flex gap-2">
                <a href="{{ route('products.index') }}" class="btn-primary">See stock &amp; prices</a>
            </div>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
        <form method="POST" action="{{ route('products.import.upload') }}" enctype="multipart/form-data"
              x-data="{ name: '', busy: false }" @submit="busy = true"
              class="rounded-xl border border-zinc-200 bg-white p-5">
            @csrf
            <p class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Step 1 of 3</p>
            <h2 class="mt-1 text-[17px] font-semibold">Choose the file</h2>
            <p class="mt-1 text-sm text-zinc-600">Excel (.xlsx) or CSV, up to 5,000 rows. The first sheet is read. Nothing is saved until you check the rows and confirm.</p>

            <label class="mt-4 flex min-h-36 cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 bg-zinc-50 px-4 text-center hover:border-ink"
                   @dragover.prevent @drop.prevent="$refs.file.files = $event.dataTransfer.files; name = $event.dataTransfer.files[0]?.name || ''">
                <svg class="size-8 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                <span class="text-[15px] font-semibold" x-text="name || 'Click to choose, or drop the file here'">Click to choose, or drop the file here</span>
                <span class="text-xs text-zinc-600">.xlsx · .csv · .ods</span>
                <input x-ref="file" type="file" name="file" accept=".xlsx,.csv,.txt,.ods,.xls" class="sr-only" required @change="name = $event.target.files[0]?.name || ''">
            </label>
            @error('file') <p class="error">{{ $message }}</p> @enderror

            <button class="btn-accent mt-4 min-h-12 w-full sm:w-auto" :disabled="busy">
                <span x-show="!busy">Next: match columns</span>
                <span x-show="busy" x-cloak>Reading the file…</span>
            </button>
        </form>

        <aside class="rounded-xl border border-zinc-200 bg-white p-5 text-sm">
            <h2 class="text-[15px] font-semibold">Any column names work</h2>
            <p class="mt-1 text-zinc-600">We guess columns like <em>Item</em>, <em>Brand</em>, <em>Rate</em>, <em>MRP</em>, <em>GST</em>, <em>HSN</em>, <em>Qty</em>, <em>Code</em> and <em>Also called</em>. You can change any guess on the next step.</p>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-zinc-700">
                <li>Items are matched by <strong>Code / SKU</strong>, or by name + brand when there is no code. Importing the same file again changes nothing.</li>
                <li>Stock for new items is saved as opening stock.</li>
                <li>Rows with problems are shown in red and skipped.</li>
            </ul>
            <a href="{{ route('products.import.template') }}" class="btn-ghost mt-4 w-full">Download a blank template (.csv)</a>
        </aside>
    </div>
</x-layouts.app>
