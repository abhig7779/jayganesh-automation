<x-layouts.app title="Car in">
    @php
        $initial = [
            'mobile' => old('mobile', $customer ? substr((string) $customer->mobile, -10) : ''),
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'cars' => $customer->cars->map(fn ($c) => ['id' => $c->id, 'label' => $c->label(), 'model' => $c->model, 'reg_no_pretty' => \App\Support\RegNo::pretty($c->reg_no)])->values(),
            ] : null,
            'carId' => old('customer_car_id', $customer?->cars->count() === 1 ? $customer->cars->first()->id : ''),
            'name' => old('name', ''),
            'carModel' => old('car_model', ''),
            'carRegNo' => old('car_reg_no', ''),
            'items' => collect(old('items', []))->map(fn ($i) => [
                'product_id' => $i['product_id'] ?? '', 'description' => $i['description'] ?? '',
                'qty' => (int) ($i['qty'] ?? 1), 'price' => $i['price'] ?? '',
            ])->values(),
            'promised' => old('promised_at', ''),
            'lookupUrl' => route('api.v1.customers.lookup'),
            'productsUrl' => route('jobcards.products'),
        ];
        $section = 'text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600';
        $panel = 'rounded-xl border border-zinc-200 bg-white p-5';
        $input = 'h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-[15px] text-ink placeholder:text-zinc-500 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink/10';
        $lbl = 'mb-1 block text-sm font-medium text-zinc-700';
        $chip = 'inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-full border px-4 text-sm font-medium';
    @endphp

    <x-slot:header>
        <div>
            <a href="{{ route('jobcards.index') }}" class="mb-1 inline-flex items-center gap-1 text-sm font-medium text-zinc-600 hover:text-ink"><x-jobcards.icon name="back" class="size-4" /> Job board</a>
            <h1 class="text-[28px] font-bold leading-tight tracking-tight">Car in</h1>
            <p class="text-sm font-medium text-zinc-600">Customer and car, the work, when it’s promised, and who does it.</p>
        </div>
    </x-slot:header>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        document.addEventListener('alpine:init', () => {
            Alpine.data('carIn', (init) => ({
                ...init,
                looking: false,
                lookupError: '',
                notFound: false,
                q: '',
                results: [],
                open: false,
                newCar: false,
                init() {
                    if (!this.items.length) this.items = [];
                    if (this.mobileDigits().length === 10 && !this.customer) this.lookup();
                    this.newCar = !!this.carModel && !this.carId;
                },
                mobileDigits() { return (this.mobile || '').replace(/\D+/g, '').slice(-10); },
                async lookup() {
                    const digits = this.mobileDigits();
                    this.lookupError = '';
                    if (digits.length !== 10) { this.customer = null; this.notFound = false; return; }
                    this.looking = true;
                    try {
                        const r = await fetch(this.lookupUrl + '?mobile=' + digits, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        if (r.status === 404) { this.customer = null; this.notFound = true; this.carId = ''; this.newCar = true; }
                        else if (r.ok) {
                            const c = (await r.json()).data;
                            this.customer = { id: c.id, name: c.name, cars: c.cars };
                            this.notFound = false;
                            this.carId = c.cars.length === 1 ? c.cars[0].id : '';
                            this.newCar = c.cars.length === 0;
                        } else if (r.status === 422) { this.lookupError = 'Mobile number needs 10 digits.'; }
                        else { this.lookupError = 'Couldn’t look up this number. Type the name and car below.'; this.notFound = true; this.newCar = true; }
                    } catch (e) {
                        this.lookupError = 'No internet. Type the name and car below; it saves when you’re back online.'; this.notFound = true; this.newCar = true;
                    } finally { this.looking = false; }
                },
                async search() {
                    if (this.q.trim().length < 2) { this.results = []; this.open = false; return; }
                    try {
                        const r = await fetch(this.productsUrl + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } });
                        this.results = r.ok ? (await r.json()).data : [];
                    } catch (e) { this.results = []; }
                    this.open = true;
                },
                addProduct(p) {
                    const existing = this.items.find((i) => i.product_id && +i.product_id === +p.id);
                    if (existing) existing.qty++;
                    else this.items.push({ product_id: p.id, description: p.name, qty: 1, price: p.price });
                    this.q = ''; this.results = []; this.open = false;
                },
                addFree() {
                    const text = this.q.trim();
                    if (!text) return;
                    if (this.open && this.results.length === 1) return this.addProduct(this.results[0]);
                    this.items.push({ product_id: '', description: text, qty: 1, price: '' });
                    this.q = ''; this.results = []; this.open = false;
                },
                total() {
                    return this.items.reduce((s, i) => s + (parseFloat(String(i.price).replace(/[^\d.]/g, '')) || 0) * (i.qty || 1), 0);
                },
                rupees(n) { return '₹' + Math.round(n).toLocaleString('en-IN'); },
                pad(n) { return String(n).padStart(2, '0'); },
                local(d) { return `${d.getFullYear()}-${this.pad(d.getMonth() + 1)}-${this.pad(d.getDate())}T${this.pad(d.getHours())}:${this.pad(d.getMinutes())}`; },
                promise(kind) {
                    const d = new Date();
                    if (kind === '1h' || kind === '3h') {
                        d.setHours(d.getHours() + (kind === '1h' ? 1 : 3));
                        d.setMinutes(Math.ceil(d.getMinutes() / 15) * 15, 0, 0); // round up to the quarter hour
                    } else if (kind === 'today') { d.setHours(18, 0, 0, 0); }
                    else { d.setDate(d.getDate() + 1); d.setHours(18, 0, 0, 0); }
                    this.promised = this.local(d);
                    this.promiseKind = kind;
                },
                promiseKind: '',
            }));
        });
    </script>

    <form method="POST" action="{{ route('jobcards.store') }}" x-data="carIn(@js($initial))" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]" @keydown.ctrl.enter="$el.requestSubmit()">
        @csrf
        <input type="hidden" name="customer_id" :value="customer ? customer.id : ''">

        <div class="space-y-4">
            @if ($errors->any())
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                    Fix {{ $errors->count() === 1 ? 'this' : 'these' }} to open the job card:
                    <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- 1. Customer + car --}}
            <section class="{{ $panel }}">
                <h2 class="{{ $section }} mb-3">1 · Customer and car</h2>
                <div class="grid gap-4 sm:grid-cols-[260px_minmax(0,1fr)]">
                    <div>
                        <label for="mobile" class="{{ $lbl }}">Mobile number</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-[15px] text-zinc-500">+91</span>
                            <input id="mobile" name="mobile" x-model="mobile" @input.debounce.300ms="lookup()" inputmode="numeric" autocomplete="off" autofocus
                                   placeholder="98231 07676" class="{{ $input }} pl-12 tabular-nums" aria-describedby="mobile-help">
                            <span x-show="looking" x-cloak class="absolute inset-y-0 right-3 flex items-center text-xs text-zinc-500">Looking…</span>
                        </div>
                        <p id="mobile-help" class="mt-1 text-[13px]" :class="lookupError ? 'text-red-600' : 'text-zinc-500'"
                           x-text="lookupError || (customer ? 'Found: ' + customer.name : (notFound ? 'New customer. Add the name below.' : 'Type 10 digits to find the customer.'))"></p>
                        @error('mobile')<p class="error">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <template x-if="customer">
                            <div>
                                <p class="{{ $lbl }}">Car</p>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="c in customer.cars" :key="c.id">
                                        <button type="button" @click="carId = c.id; newCar = false"
                                                class="{{ $chip }}" :class="+carId === +c.id && !newCar ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white text-ink hover:border-zinc-400'">
                                            <span x-text="c.model || c.label"></span><span class="tabular-nums opacity-80" x-show="c.reg_no_pretty" x-text="'· ' + c.reg_no_pretty"></span>
                                        </button>
                                    </template>
                                    <button type="button" @click="newCar = true; carId = ''" class="{{ $chip }}"
                                            :class="newCar ? 'border-ink bg-ink text-white' : 'border-dashed border-zinc-400 bg-white text-ink'">
                                        <x-jobcards.icon name="plus" class="size-4" /> Another car
                                    </button>
                                </div>
                            </div>
                        </template>
                        <template x-if="!customer && notFound">
                            <div>
                                <label for="name" class="{{ $lbl }}">Customer name</label>
                                <input id="name" name="name" x-model="name" class="{{ $input }}" placeholder="e.g. Rahul Pawar" autocomplete="off">
                            </div>
                        </template>
                        <p x-show="!customer && !notFound" class="pt-7 text-sm text-zinc-500">The customer’s cars show here.</p>
                        @error('name')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <input type="hidden" name="customer_car_id" :value="newCar ? '' : carId">
                <div x-show="newCar" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="car_model" class="{{ $lbl }}">Car model</label>
                        <input id="car_model" name="car_model" x-model="carModel" :disabled="!newCar" class="{{ $input }}" placeholder="e.g. Hyundai Creta" autocomplete="off">
                    </div>
                    <div>
                        <label for="car_reg_no" class="{{ $lbl }}">Number plate</label>
                        <input id="car_reg_no" name="car_reg_no" x-model="carRegNo" :disabled="!newCar" class="{{ $input }} uppercase tabular-nums" placeholder="MH12 KT 4521" autocomplete="off">
                    </div>
                </div>
                @error('customer_car_id')<p class="error">{{ $message }}</p>@enderror
            </section>

            {{-- 2. Work items --}}
            <section class="{{ $panel }}">
                <h2 class="{{ $section }} mb-3">2 · Work</h2>
                <div class="relative">
                    <label for="work-search" class="sr-only">Search products or type the work</label>
                    <x-jobcards.icon name="search" class="pointer-events-none absolute left-3 top-2.5 size-5 text-zinc-500" />
                    <input id="work-search" x-model="q" @input.debounce.250ms="search()" @keydown.enter.prevent="addFree()" @keydown.escape="open = false"
                           autocomplete="off" placeholder="Search a product or service, or type the work and press Enter" class="{{ $input }} pl-10">
                    <ul x-cloak x-show="open" @click.outside="open = false" class="absolute z-20 mt-1 max-h-72 w-full overflow-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg">
                        <template x-for="p in results" :key="p.id">
                            <li><button type="button" @click="addProduct(p)" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50">
                                <span x-text="p.name"></span><span class="tabular-nums text-zinc-600" x-text="p.price_label"></span></button></li>
                        </template>
                        <li x-show="q.trim()"><button type="button" @click="addFree()" class="w-full px-3 py-2 text-left text-sm font-medium hover:bg-zinc-50">
                            Add “<span x-text="q.trim()"></span>” as free text</button></li>
                    </ul>
                </div>

                @if ($popular->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span class="self-center text-xs font-semibold uppercase tracking-[0.06em] text-zinc-500">Popular</span>
                        @foreach ($popular as $p)
                            <button type="button" @click="addProduct(@js(['id' => $p->id, 'name' => $p->name, 'price' => number_format($p->price_paise / 100, $p->price_paise % 100 ? 2 : 0, '.', '')]))"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-full border border-zinc-300 bg-white px-3 text-sm hover:border-zinc-400">
                                {{ $p->name }} <span class="text-zinc-500 tabular-nums">· {{ \App\Support\Money::format($p->price_paise) }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="mt-4 overflow-hidden rounded-lg border border-zinc-200">
                    <div class="grid grid-cols-[minmax(0,1fr)_96px_120px_40px] gap-2 bg-zinc-100 px-3 py-2 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">
                        <span>Work</span><span class="text-center">Qty</span><span class="text-right">Price ₹</span><span></span>
                    </div>
                    <template x-for="(item, i) in items" :key="i">
                        <div class="grid grid-cols-[minmax(0,1fr)_96px_120px_40px] items-center gap-2 border-t border-zinc-200 px-3 py-2">
                            <div class="min-w-0">
                                <input type="hidden" :name="`items[${i}][product_id]`" :value="item.product_id">
                                <input :name="`items[${i}][description]`" x-model="item.description" class="h-9 w-full rounded-md border border-transparent px-2 text-sm hover:border-zinc-300 focus:border-ink focus:outline-none" aria-label="Work">
                            </div>
                            <div class="flex h-9 items-center rounded-md border border-zinc-300">
                                <button type="button" class="h-full w-8 text-lg leading-none" @click="item.qty > 1 ? item.qty-- : items.splice(i, 1)" aria-label="Less">−</button>
                                <input :name="`items[${i}][qty]`" x-model.number="item.qty" inputmode="numeric" class="h-full w-full min-w-0 border-x border-zinc-300 text-center text-sm tabular-nums focus:outline-none" aria-label="Qty">
                                <button type="button" class="h-full w-8 text-lg leading-none" @click="item.qty++" aria-label="More">+</button>
                            </div>
                            <input :name="`items[${i}][price]`" x-model="item.price" inputmode="decimal" placeholder="0" class="h-9 rounded-md border border-zinc-300 px-2 text-right text-sm tabular-nums focus:border-ink focus:outline-none" aria-label="Price in rupees">
                            <button type="button" @click="items.splice(i, 1)" class="grid size-9 place-items-center rounded-md text-zinc-500 hover:bg-zinc-100 hover:text-ink" aria-label="Remove"><x-jobcards.icon name="x" class="size-4" /></button>
                        </div>
                    </template>
                    <p x-show="!items.length" class="border-t border-zinc-200 px-3 py-6 text-center text-sm text-zinc-500">No work added yet. Search above or tap a Popular item.</p>
                </div>
                @error('items')<p class="error">{{ $message }}</p>@enderror
            </section>
        </div>

        {{-- 3. Promise + technician + save --}}
        <aside class="space-y-4 xl:sticky xl:top-6 xl:self-start">
            <section class="{{ $panel }}">
                <h2 class="{{ $section }} mb-3">3 · Promised time</h2>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['1h' => '+1 h', '3h' => '+3 h', 'today' => 'Today 6 pm', 'tomorrow' => 'Tomorrow 6 pm'] as $k => $l)
                        <button type="button" @click="promise('{{ $k }}')" class="h-10 rounded-lg border text-sm font-medium disabled:cursor-not-allowed disabled:opacity-40"
                                @if ($k === 'today') :disabled="new Date().getHours() >= 18" x-bind:title="new Date().getHours() >= 18 ? 'It’s past 6 pm' : ''" @endif
                                :class="promiseKind === '{{ $k }}' ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white text-ink hover:border-zinc-400'">{{ $l }}</button>
                    @endforeach
                </div>
                <label for="promised_at" class="{{ $lbl }} mt-3">Or pick a time</label>
                <input id="promised_at" type="datetime-local" name="promised_at" x-model="promised" @input="promiseKind = ''" class="{{ $input }}">

                <label for="technician_id" class="{{ $lbl }} mt-4">Technician</label>
                <select id="technician_id" name="technician_id" class="{{ $input }}">
                    <option value="">Assign later</option>
                    @foreach ($technicians as $t)
                        <option value="{{ $t->id }}" @selected((string) old('technician_id') === (string) $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
                @if ($technicians->isEmpty())
                    <p class="mt-1 text-[13px] text-zinc-500">No technician logins yet. Add them in Staff.</p>
                @endif

                <details class="mt-4" @if (old('odometer') || old('fuel_level') || old('bay') || old('notes')) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold text-ink">More details <span class="font-normal text-zinc-500">(km, fuel, bay, notes)</span></summary>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <div>
                            <label for="odometer" class="{{ $lbl }}">Odometer km</label>
                            <input id="odometer" name="odometer" value="{{ old('odometer') }}" inputmode="numeric" class="{{ $input }} tabular-nums" placeholder="42,150">
                        </div>
                        <div>
                            <label for="bay" class="{{ $lbl }}">Bay</label>
                            <input id="bay" name="bay" value="{{ old('bay') }}" maxlength="20" class="{{ $input }}" placeholder="Bay 2">
                        </div>
                    </div>
                    <p class="{{ $lbl }} mt-3">Fuel</p>
                    <div class="inline-flex w-full rounded-lg bg-zinc-100 p-1">
                        @foreach (\App\Models\JobCard::FUEL_LEVELS as $f)
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="fuel_level" value="{{ $f }}" class="peer sr-only" @checked(old('fuel_level') === $f)>
                                <span class="flex h-8 items-center justify-center rounded-md text-sm font-medium text-zinc-600 peer-checked:bg-white peer-checked:text-ink peer-checked:shadow-[0_0_0_1px_#E4E4E7]">{{ $f }}</span>
                            </label>
                        @endforeach
                    </div>
                    <label for="notes" class="{{ $lbl }} mt-3">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="1000" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-[15px]" placeholder="Scratch on rear bumper, customer waiting">{{ old('notes') }}</textarea>
                </details>
            </section>

            <div class="rounded-xl border border-zinc-200 bg-white p-4">
                <div class="mb-3 flex items-baseline justify-between">
                    <span class="text-sm text-zinc-600"><span x-text="items.length"></span> work items · estimate</span>
                    <span class="text-2xl font-bold tabular-nums" x-text="rupees(total())">₹0</span>
                </div>
                <button class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-[#FFD51C] text-[17px] font-semibold text-ink hover:bg-[#F2C800] disabled:opacity-50"
                        :disabled="!items.length || (!customer && mobileDigits().length !== 10)">
                    Open job card
                </button>
                <p class="mt-2 text-center text-xs text-zinc-500">Ctrl + Enter saves. Take before photos on the next screen.</p>
            </div>
        </aside>
    </form>
</x-layouts.app>
