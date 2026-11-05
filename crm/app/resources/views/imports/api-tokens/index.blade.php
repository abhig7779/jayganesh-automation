<x-layouts.app title="API keys">
    <x-slot:header>
        <div>
            <h1 class="text-[22px] font-bold leading-7">API keys</h1>
            <p class="text-sm text-zinc-600">Keys let the website enquiry form send leads straight into the CRM.</p>
        </div>
    </x-slot:header>

    @if ($plain)
        <section class="mb-6 rounded-xl border border-green-200 bg-green-50 p-5" data-new-token x-data="{ copied: false }">
            <h2 class="text-[17px] font-semibold text-green-800">Key “{{ $plainName }}” is ready</h2>
            <p class="mt-1 text-sm text-green-900">Copy it now and give it to the website developer. <strong>It is shown only this once.</strong> If it's lost, switch it off and make a new one.</p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <input x-ref="key" type="text" readonly value="{{ $plain }}" class="input min-h-11 flex-1 font-mono text-[13px]" @focus="$el.select()" aria-label="New API key">
                <button type="button" class="btn-primary min-h-11" @click="navigator.clipboard.writeText($refs.key.value).then(() => copied = true)">
                    <span x-show="!copied">Copy key</span><span x-show="copied" x-cloak>Copied</span>
                </button>
            </div>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <section>
            <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-zinc-600">Keys in use</h2>
            @if ($tokens->isEmpty())
                <p class="rounded-xl border border-zinc-200 bg-white px-4 py-8 text-center text-sm text-zinc-600">No keys yet. Make one for the website form on the right.</p>
            @else
                <ul class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white" data-token-list>
                    @foreach ($tokens as $t)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-[15px] font-semibold text-ink">{{ $t->name }}</div>
                                <div class="text-[13px] text-zinc-600">
                                    Can: create leads ·
                                    Made {{ $t->created_at->timezone('Asia/Kolkata')->format('j M Y') }} ·
                                    {{ $t->last_used_at ? 'Last used '.$t->last_used_at->timezone('Asia/Kolkata')->format('j M, g:i a') : 'Not used yet' }}
                                </div>
                            </div>
                            <form method="POST" action="{{ route('api-tokens.destroy', $t->id) }}" data-name="{{ $t->name }}" x-data @submit="if (! confirm('Switch off “' + $el.dataset.name + '”? Anything using it stops working at once.')) $event.preventDefault()">
                                @csrf
                                @method('DELETE')
                                <button class="inline-flex min-h-10 items-center rounded-lg border border-red-300 bg-white px-3 text-sm font-semibold text-red-600 hover:bg-red-50">Switch off</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <aside class="space-y-4">
            <form method="POST" action="{{ route('api-tokens.store') }}" class="rounded-xl border border-zinc-200 bg-white p-5">
                @csrf
                <h2 class="text-[15px] font-semibold">Make a new key</h2>
                <label for="token-name" class="label mt-3">Name</label>
                <input id="token-name" name="name" class="input" placeholder="Website form" value="{{ old('name', 'Website form') }}" maxlength="60" required>
                @error('name') <p class="error">{{ $message }}</p> @enderror
                <button class="btn-accent mt-4 min-h-11 w-full">Make key</button>
            </form>
            <section class="rounded-xl border border-zinc-200 bg-white p-5 text-sm text-zinc-700">
                <h2 class="text-[15px] font-semibold text-ink">What a key can do</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li>Only add enquiries to <strong>Leads</strong>. It can't see customers, bills or prices.</li>
                    <li>Each new lead gets a call-today follow-up.</li>
                    <li>The same number again within 7 days is added to the same lead.</li>
                </ul>
                <p class="mt-2 text-zinc-600">Setup for the website developer: <code class="text-[12px]">crm/LEADS-API.md</code>.</p>
            </section>
        </aside>
    </div>
</x-layouts.app>
