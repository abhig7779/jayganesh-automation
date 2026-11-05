{{-- Owner only: is WhatsApp ready to send automatically? Shows yes/no, never the secret values. --}}
<section class="card mb-4" aria-labelledby="wa-setup">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="wa-setup" class="text-base font-semibold">WhatsApp setup</h2>
            <p class="text-sm text-zinc-500">
                @if ($setup['automatic'])
                    Mode: <strong class="text-ink">Automatic (Cloud API)</strong>. Bills go out by themselves.
                @else
                    Mode: <strong class="text-ink">Manual</strong>. Staff tap "Send on WhatsApp" and send from the phone.
                @endif
            </p>
        </div>
        <a href="{{ route('settings.edit') }}" class="btn-ghost">Change mode</a>
    </div>

    <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
        @foreach ($setup['env'] as $name => $isSet)
            <div class="flex items-center justify-between gap-3 border-b border-zinc-100 py-1">
                <dt class="font-mono text-xs text-zinc-600">{{ $name }}</dt>
                <dd>
                    @if ($isSet)
                        <span class="badge bg-emerald-50 text-emerald-700">Set</span>
                    @else
                        <span class="badge bg-zinc-100 text-zinc-600">Not set</span>
                    @endif
                </dd>
            </div>
        @endforeach
        <div class="flex items-center justify-between gap-3 border-b border-zinc-100 py-1">
            <dt class="text-zinc-600">Graph API version</dt>
            <dd class="font-mono text-xs">{{ $setup['graph_version'] }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3 border-b border-zinc-100 py-1">
            <dt class="text-zinc-600">Last webhook from Meta</dt>
            <dd class="text-xs">{{ $setup['last_webhook_at']?->timezone('Asia/Kolkata')->format('d M Y, h:i A') ?? 'Never' }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-zinc-600">Webhook URL for the Meta app</dt>
            <dd class="mt-1 break-all font-mono text-xs">{{ $setup['webhook_url'] }}</dd>
        </div>
    </dl>

    @if ($setup['automatic'] && ! $setup['env']['WHATSAPP_TOKEN'])
        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Mode is Automatic but the token is not set, so every message will fail. Fill the WHATSAPP_ values in .env or switch back to Manual.
        </p>
    @endif

    <details class="mt-4 border-t border-zinc-100 pt-3" @if ($errors->has('to') || old('template')) open @endif>
        <summary class="cursor-pointer text-sm font-semibold">Send a test template</summary>
        @if ($setup['automatic'] && $setup['env']['WHATSAPP_TOKEN'] && $setup['env']['WHATSAPP_PHONE_NUMBER_ID'])
            <form method="POST" action="{{ route('whatsapp.test') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                @csrf
                <div>
                    <label class="label" for="test_to">Mobile</label>
                    <input class="input" id="test_to" name="to" inputmode="tel" value="{{ old('to') }}" placeholder="98231 07676" required>
                    @error('to') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="test_template">Template name</label>
                    <input class="input" id="test_template" name="template" value="{{ old('template', 'hello_world') }}" required>
                    @error('template') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="test_language">Language code</label>
                    <input class="input" id="test_language" name="language" value="{{ old('language', 'en_US') }}" placeholder="en_US for hello_world, en for ours">
                </div>
                <div>
                    <label class="label" for="test_params">Values, separated by |</label>
                    <input class="input" id="test_params" name="params" value="{{ old('params') }}" placeholder="Rahul | JG-2526-0001 | ₹1,500 | Paid">
                </div>
                <div class="sm:col-span-2">
                    <button class="btn-primary">Send test now</button>
                    <p class="mt-1 text-xs text-zinc-500">Templates with a PDF header (invoice_ebill) can't be tested here; save a test bill instead.</p>
                </div>
            </form>
        @else
            <p class="mt-2 text-sm text-zinc-500">Available once the number is connected: set the WHATSAPP_ values in .env and switch the mode to Automatic.</p>
        @endif
    </details>
</section>
