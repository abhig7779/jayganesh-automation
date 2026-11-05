@php use App\Services\Offers\SegmentBuilder; @endphp
<x-layouts.app title="Meta ads audience">
    <x-slot:header>
        <div>
            <a href="{{ route('offers.index') }}" class="text-sm font-medium text-zinc-600 hover:text-ink">← Offers</a>
            <h1 class="mt-1 text-2xl font-bold">Meta ads audience</h1>
            <p class="text-sm text-zinc-600">Download customers as a list for Facebook and Instagram ads (ad account act_25886513611038791). Opted-out customers are left out. Every download is logged.</p>
        </div>
    </x-slot:header>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <form method="POST" action="{{ route('audiences.download') }}" class="card min-w-0 space-y-5" x-data="{ seg: '{{ old('segment_id', $choices->first()['id']) }}' }">
            @csrf
            <div>
                <h2 class="text-base font-semibold">1. Which customers</h2>
                <div class="mt-2 grid gap-2 md:grid-cols-2">
                    @foreach ($choices as $c)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-300 bg-white p-3 has-[:checked]:border-ink has-[:checked]:ring-1 has-[:checked]:ring-ink">
                            <input type="radio" name="segment_id" value="{{ $c['id'] }}" x-model="seg" class="mt-1 size-4 accent-ink" @checked($loop->first)>
                            <span>
                                <span class="block font-semibold">{{ $c['name'] }}</span>
                                <span class="block text-sm tabular-nums text-zinc-600">{{ SegmentBuilder::countLine($c['count']) }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-sm text-zinc-600">Need a different group? <a href="{{ route('offers.create') }}" class="font-semibold text-ink underline decoration-brand-400 decoration-2 underline-offset-4">Build and save a list</a> from New offer, then come back.</p>
            </div>

            <div>
                <h2 class="text-base font-semibold">2. Extra columns <span class="font-normal text-zinc-600">(more columns = better match rate)</span></h2>
                <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" checked disabled class="size-4 accent-ink"> phone (always)</label>
                    @foreach (['fn' => 'First name (fn)', 'ln' => 'Last name (ln)', 'ct' => 'City: Pune (ct)', 'st' => 'State: Maharashtra (st)', 'country' => 'Country: in'] as $key => $label)
                        <label class="flex items-center gap-2"><input type="checkbox" name="fields[]" value="{{ $key }}" checked class="size-4 accent-ink"> {{ $label }}</label>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 class="text-base font-semibold">3. Download</h2>
                <div class="mt-2 grid gap-3 md:grid-cols-2">
                    <div class="rounded-lg border border-zinc-300 p-4">
                        <div class="font-semibold">Hashed (SHA-256)</div>
                        <p class="mt-1 text-sm text-zinc-700">Numbers and names are scrambled before they leave the shop PC. Meta matches them the same way. Safer if the file is emailed or left in Downloads.</p>
                        <button name="format" value="sha256" class="btn-accent mt-3">Download hashed CSV</button>
                    </div>
                    <div class="rounded-lg border border-zinc-300 p-4">
                        <div class="font-semibold">Plain</div>
                        <p class="mt-1 text-sm text-zinc-700">Readable numbers like 919823107676. Meta hashes them during upload. Delete the file after uploading.</p>
                        <button name="format" value="plain" class="btn-ghost mt-3">Download plain CSV</button>
                    </div>
                </div>
            </div>
        </form>

        <aside class="space-y-5">
            <div class="card text-sm">
                <h2 class="text-base font-semibold">How to upload</h2>
                <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-zinc-800">
                    <li>Open <strong>Ads Manager → Audiences</strong>.</li>
                    <li><strong>Create audience → Custom audience → Customer list</strong>.</li>
                    <li>Upload this file. Meta reads the column names (phone, fn, ln, ct, st, country) by itself.</li>
                    <li>Name it with the date, e.g. “JG customers Nov 2026”.</li>
                </ol>
                <h3 class="mt-4 font-semibold">Then make a lookalike</h3>
                <p class="mt-1 text-zinc-700">Create audience → <strong>Lookalike</strong> → source: this list → location <strong>India</strong>, size <strong>1%</strong>. In the ad set, narrow the location to <strong>Pune</strong> (about 15–20 km around Loni Kalbhor) to bring in new walk-ins who look like your buyers.</p>
                <p class="mt-3 text-xs text-zinc-600">Lists under about 100 matched people are too small for Meta to use. Exclude this list from “new customer” ads so you don't pay to reach people who already come.</p>
            </div>

            <div class="card p-0 sm:p-0">
                <h2 class="border-b border-zinc-100 px-5 py-3 text-xs font-semibold uppercase tracking-wider text-zinc-600">Download log</h2>
                @if ($exports->isEmpty())
                    <p class="px-5 py-4 text-sm text-zinc-600">No downloads yet.</p>
                @else
                    <ul class="divide-y divide-zinc-100 text-sm">
                        @foreach ($exports as $x)
                            <li class="px-5 py-2.5">
                                <div class="flex justify-between gap-2"><span class="font-medium">{{ $x->segment_name }}</span><span class="tabular-nums text-zinc-700">{{ number_format($x->row_count) }} rows</span></div>
                                <div class="text-xs text-zinc-600">{{ $x->user?->name ?? 'Removed user' }} · {{ $x->format === 'sha256' ? 'hashed' : 'plain' }} · {{ $x->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>
    </div>
</x-layouts.app>
