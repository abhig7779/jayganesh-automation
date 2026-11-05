<x-layouts.app title="Connect WhatsApp">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold">Connect WhatsApp</h1>
            <p class="text-sm text-zinc-500">Use the shop's WhatsApp number in the CRM and keep using it in the WhatsApp Business app on the phone.</p>
        </div>
    </x-slot:header>

    @if (session('status'))
        <div role="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ session('error') }}</div>
    @endif

    {{-- 1. The number --}}
    <section class="card mb-4" aria-labelledby="wa-number">
        <h2 id="wa-number" class="text-base font-semibold">Shop number</h2>

        @if ($connection)
            <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between gap-3 border-b border-zinc-100 py-1"><dt class="text-zinc-500">Number</dt><dd class="font-medium">{{ $connection->display_phone ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3 border-b border-zinc-100 py-1"><dt class="text-zinc-500">Name on WhatsApp</dt><dd>{{ $connection->verified_name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3 border-b border-zinc-100 py-1">
                    <dt class="text-zinc-500">Also on the phone app</dt>
                    <dd>
                        @if ($connection->isCoexistence())
                            <span class="badge bg-emerald-50 text-emerald-700">Yes (coexistence)</span>
                        @else
                            <span class="badge bg-zinc-100 text-zinc-600">No, CRM only</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3 border-b border-zinc-100 py-1"><dt class="text-zinc-500">Connected</dt><dd>{{ $connection->created_at?->timezone('Asia/Kolkata')->format('d M Y, g:i A') }}</dd></div>
                @if ($connection->isCoexistence())
                    <div class="flex justify-between gap-3 border-b border-zinc-100 py-1">
                        <dt class="text-zinc-500">Contacts &amp; chat history</dt>
                        <dd>
                            @if ($connection->contacts_sync_request_id && $connection->history_sync_request_id)
                                <span class="badge bg-emerald-50 text-emerald-700">Sync started</span>
                            @else
                                <span class="badge bg-amber-50 text-amber-800">Not started</span>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>
            @if ($connection->last_error)
                <p class="error mt-3">{{ $connection->last_error }}</p>
            @endif
            @if ($connection->isCoexistence() && ! ($connection->contacts_sync_request_id && $connection->history_sync_request_id))
                <form method="POST" action="{{ route('whatsapp.connect.sync') }}" class="mt-3">
                    @csrf
                    <button class="btn-ghost">Start the contacts &amp; history sync</button>
                    <p class="mt-1 text-xs text-zinc-500">Only works within 24 hours of connecting.</p>
                </form>
            @endif
        @elseif ($envConfigured)
            <p class="mt-2 text-sm text-zinc-600">The number is set in the server settings (.env). Nothing to connect here.</p>
        @else
            <p class="mt-2 text-sm text-zinc-600">Not connected yet. Before you start:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-zinc-600">
                <li>Update WhatsApp Business on the shop phone (version 2.24.17 or newer) and keep the phone nearby.</li>
                <li>Log in to Facebook as the owner of the business portfolio.</li>
                <li>In the popup, choose <strong>"Connect your existing WhatsApp Business app"</strong>, enter the shop number, and scan the code with the phone when asked.</li>
                <li>Keep the WhatsApp Business app open until the sync finishes. Calls stay on the phone.</li>
            </ul>
        @endif

        @unless ($envConfigured)
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" id="wa-connect" class="btn-primary min-h-12" disabled>{{ $connection ? 'Connect again' : 'Connect WhatsApp' }}</button>
                <p id="wa-connect-status" class="text-sm text-zinc-600" role="status" aria-live="polite">Loading Facebook…</p>
            </div>
        @endunless
    </section>

    {{-- 2. AI replies --}}
    <section class="card mb-4" aria-labelledby="wa-ai">
        <h2 id="wa-ai" class="text-base font-semibold">AI replies</h2>
        <p class="mt-1 text-sm text-zinc-600">The AI answers new customer messages in their language, asks for the car and what they need, and hands the chat to you for prices and bookings. It never quotes a price or offer that isn't in your ad. When you reply from the phone, it stays quiet in that chat for 12 hours.</p>

        @unless ($aiReady)
            <p class="error mt-2">No AI key is set on the server yet, so AI replies cannot run.</p>
        @endunless

        <form method="POST" action="{{ route('whatsapp.ai') }}" class="mt-3 grid gap-2 sm:max-w-md">
            @csrf
            @foreach (['ads' => 'Only chats from my Click-to-WhatsApp ads', 'all' => 'All new customer chats', 'off' => 'Off'] as $value => $label)
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-zinc-200 px-3">
                    <input type="radio" name="mode" value="{{ $value }}" @checked($aiMode === $value)>
                    <span class="text-sm">{{ $label }}</span>
                </label>
            @endforeach
            <button class="btn-primary min-h-12">Save</button>
        </form>
    </section>

    @unless ($envConfigured)
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            (function () {
                const button = document.getElementById('wa-connect');
                const status = document.getElementById('wa-connect-status');
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content
                    || document.querySelector('input[name="_token"]')?.value;
                let signup = null; // data from the WA_EMBEDDED_SIGNUP message
                let code = null;   // from FB.login, valid about 30 seconds

                const post = (url, body) => fetch(url, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                    body: JSON.stringify(body),
                    credentials: 'same-origin',
                });

                const finish = async () => {
                    if (!signup || !code) return;
                    status.textContent = 'Connecting… keep this page open.';
                    try {
                        const res = await post(@json(route('whatsapp.connect.store')), {code, event: signup});
                        const json = await res.json().catch(() => ({}));
                        if (res.ok && json.ok) {
                            status.textContent = 'Connected ' + (json.number || '') + '. Reloading…';
                            setTimeout(() => location.reload(), 1200);
                        } else {
                            status.textContent = json.message || 'Could not connect. Please try again.';
                            button.disabled = false;
                        }
                    } catch (e) {
                        status.textContent = 'No internet connection. Please try again.';
                        button.disabled = false;
                    }
                    code = null;
                };

                // Session logging: Meta asks partners to record every Embedded Signup event.
                window.addEventListener('message', (event) => {
                    if (!event.origin.endsWith('facebook.com')) return;
                    let data;
                    try { data = JSON.parse(event.data); } catch (e) { return; }
                    if (data.type !== 'WA_EMBEDDED_SIGNUP') return;
                    post(@json(route('whatsapp.connect.event')), {type: data.type, event: data.event, data: data.data || {}}).catch(() => {});
                    if (String(data.event || '').startsWith('FINISH')) {
                        signup = Object.assign({event: data.event}, data.data || {});
                        finish();
                    } else if (data.event === 'CANCEL') {
                        status.textContent = 'Stopped at step "' + ((data.data || {}).current_step || '?') + '". You can start again.';
                        button.disabled = false;
                    } else if (data.event === 'ERROR') {
                        status.textContent = 'Meta error: ' + ((data.data || {}).error_message || 'unknown') + '.';
                        button.disabled = false;
                    }
                });

                window.fbAsyncInit = function () {
                    FB.init({appId: @json($appId), autoLogAppEvents: true, xfbml: false, version: @json($graphVersion)});
                    button.disabled = false;
                    status.textContent = '';
                };

                button.addEventListener('click', () => {
                    button.disabled = true;
                    status.textContent = 'Finish the steps in the Facebook window…';
                    FB.login((response) => {
                        if (response.authResponse && response.authResponse.code) {
                            code = response.authResponse.code;
                            finish();
                        } else {
                            status.textContent = 'The Facebook window was closed before the end.';
                            button.disabled = false;
                        }
                    }, {
                        config_id: @json($configId),
                        response_type: 'code',
                        override_default_response_type: true,
                        extras: {setup: {}, featureType: 'whatsapp_business_app_onboarding', sessionInfoVersion: '3'},
                    });
                });

                const sdk = document.createElement('script');
                sdk.src = 'https://connect.facebook.net/en_US/sdk.js';
                sdk.async = true; sdk.defer = true; sdk.crossOrigin = 'anonymous';
                sdk.onerror = () => { status.textContent = 'Facebook could not load (check the internet or the page security settings).'; };
                document.head.appendChild(sdk);
            })();
        </script>
    @endunless
</x-layouts.app>
