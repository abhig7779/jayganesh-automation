{{-- Customer: mobile → found card / new mini form / walk-in. $pc = desktop POS layout. --}}
@php
    $sfx = $pc ? 'Pc' : 'Phone';
    $historyUrl = \Illuminate\Support\Facades\Route::has('customers.show') ? url('/customers') : null;
@endphp
<div>
    @unless ($pc)
        <label for="mobile{{ $sfx }}" class="mb-2 block text-[15px] font-semibold text-ink">Customer mobile</label>
    @endunless
    <div class="flex items-center gap-2">
        <div @class([
            'flex items-center rounded-lg border bg-white focus-within:border-ink focus-within:ring-2 focus-within:ring-ink/80',
            'h-10 w-60 border-zinc-300' => $pc,
            'h-14 flex-1 border-zinc-300' => ! $pc,
        ]) :class="mobileError ? '!border-red-600' : ''">
            <span @class(['pl-3 font-semibold text-zinc-600 tabular-nums', 'text-sm' => $pc, 'text-lg' => ! $pc])>+91</span>
            <input id="mobile{{ $sfx }}" x-ref="mobile{{ $sfx }}" type="tel" inputmode="numeric" autocomplete="off" maxlength="16"
                   x-model="mobile" @input="onMobileInput()" @keydown.enter.prevent="custState === 'found' ? $refs.search{{ $sfx }}?.focus() : null"
                   placeholder="98231 04512" aria-label="Customer mobile number"
                   @class(['h-full min-w-0 flex-1 border-0 bg-transparent px-2 font-semibold tracking-wide text-ink tabular-nums placeholder:font-normal placeholder:text-zinc-400 focus:outline-none', 'text-[15px]' => $pc, 'text-[22px]' => ! $pc])>
            <span x-show="custState === 'loading'" class="mr-2 size-4 animate-spin rounded-full border-2 border-zinc-300 border-t-ink" aria-label="Checking"></span>
            <button type="button" x-show="mobile && custState !== 'loading'" @click="changeCustomer()" class="mr-1 grid size-8 place-items-center rounded text-zinc-500 hover:bg-zinc-100" aria-label="Clear number"><x-bills.icon name="x" class="size-4" /></button>
        </div>
        @if ($pc)<x-bills.kbd>F3</x-bills.kbd>@endif
        <span class="flex-1"></span>
        <template x-if="custState === 'found' && ctx && ctx.due_paise > 0">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-600"><span class="size-1.5 rounded-full bg-red-600"></span>Due <span x-text="money(ctx.due_paise)"></span></span>
        </template>
    </div>
    <p class="mt-1 text-[13px] text-red-600" x-show="mobileError" x-text="mobileError"></p>
    <p class="mt-1 text-[13px] text-zinc-600" x-show="custState === 'short'">Mobile number needs 10 digits.</p>
    <p class="mt-1 text-[13px] text-red-600" x-show="custState === 'error'">Couldn’t check this number. <button type="button" class="font-semibold underline" @click="lookup(mobileDigits(mobile))">Try again</button></p>

    {{-- Idle / walk-in --}}
    <div x-show="custState === 'idle'" class="{{ $pc ? 'mt-1.5' : 'mt-4' }} text-[13px] text-zinc-600">
        The app finds the customer after the 10th digit.
        <button type="button" class="font-semibold text-ink underline underline-offset-2" @click="walkInSheet = true">Skip, walk-in without number</button>
    </div>
    <div x-show="custState === 'walkin'" class="{{ $pc ? 'mt-2' : 'mt-4' }} flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[13px] text-amber-800">
        <span><span class="font-semibold">Walk-in customer.</span> No WhatsApp bill and no dues without a number.</span>
        <button type="button" class="font-semibold underline" @click="changeCustomer()">Add number</button>
    </div>

    {{-- Known customer --}}
    <template x-if="custState === 'found' && cust">
        <div class="{{ $pc ? 'mt-2' : 'mt-4 rounded-xl border border-zinc-200 bg-white p-4' }}">
            <div class="flex items-start gap-3">
                @unless ($pc)
                    <span class="grid size-12 shrink-0 place-items-center rounded-lg bg-zinc-100 text-sm font-bold text-ink" x-text="cust.name.split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase()"></span>
                @endunless
                <div class="min-w-0 flex-1 text-[13px] text-zinc-600">
                    <span class="{{ $pc ? 'text-[15px]' : 'block text-[17px]' }} font-semibold text-ink" x-text="cust.name"></span>
                    <span x-show="cust.area"> · <span x-text="cust.area"></span></span>
                    <span x-show="ctx"> · <span x-text="ctx?.bills_count ? ctx.bills_count + ' previous bill' + (ctx.bills_count === 1 ? '' : 's') : 'First bill'"></span></span>
                    <span x-show="ctx?.last_visit" class="{{ $pc ? '' : 'block' }}"><span class="{{ $pc ? '' : 'hidden' }}"> · </span>Last visit <span x-text="ctx?.last_visit"></span><span x-show="ctx?.last_items"> · <span x-text="ctx?.last_items"></span></span></span>
                </div>
                @if ($historyUrl)
                    <a :href="'{{ $historyUrl }}/' + cust.id" target="_blank" class="text-[13px] font-semibold text-ink underline underline-offset-2">History</a>
                @endif
            </div>
            <template x-if="ctx?.open_quote">
                <div class="mt-2 flex items-center justify-between rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-[13px] text-amber-800">
                    <span class="flex items-center gap-1.5"><x-bills.icon name="alert" class="size-4" />Open quote <span class="font-semibold" x-show="ctx.open_quote.total_paise" x-text="money(ctx.open_quote.total_paise)"></span><span x-show="ctx.open_quote.date" x-text="'· ' + ctx.open_quote.date"></span></span>
                    <a x-show="ctx.open_quote.url" :href="ctx.open_quote.url" class="font-semibold underline">Use quote</a>
                </div>
            </template>
            <div class="mt-2.5 flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Which car?">
                @unless ($pc)<span class="w-full text-[13px] font-semibold text-ink">Which car?</span>@endunless
                <template x-for="c in cust.cars" :key="c.id">
                    <button type="button" role="radio" :aria-checked="carId === c.id" @click="carId = (carId === c.id ? null : c.id)"
                            :class="carId === c.id ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white text-ink hover:border-ink'"
                            class="inline-flex {{ $pc ? 'h-8' : 'h-11' }} items-center gap-1.5 rounded-full border px-3 text-[13px] font-semibold">
                        <x-bills.icon name="check" class="size-3.5" x-show="carId === c.id" />
                        <span x-text="c.model + (c.reg_pretty ? ' · ' + c.reg_pretty : '')"></span>
                    </button>
                </template>
                <button type="button" @click="carId = null; modelOpen = true; newCust.model = ''" x-show="!newCust.model || carId"
                        class="inline-flex {{ $pc ? 'h-8' : 'h-11' }} items-center gap-1 rounded-full border border-dashed border-zinc-400 px-3 text-[13px] font-semibold text-ink hover:border-ink">
                    <x-bills.icon name="plus" class="size-3.5" />Add car
                </button>
            </div>
            <div x-show="modelOpen || (newCust.model && !carId)" class="mt-2">
                @include('bills.partials.car-fields', ['pc' => $pc])
            </div>
        </div>
    </template>

    {{-- New number: mini form --}}
    <div x-show="custState === 'new'" class="{{ $pc ? 'mt-2' : 'mt-4 rounded-xl border border-zinc-200 bg-white p-4' }}">
        <p class="text-[13px] font-semibold text-green-700">New customer. They’re saved with this bill.</p>
        <div class="mt-2 grid gap-2 {{ $pc ? 'grid-cols-2' : '' }}">
            <div>
                <label class="mb-1 block text-xs font-semibold text-zinc-700" for="newName{{ $sfx }}">Name <span class="text-red-600">*</span></label>
                <input id="newName{{ $sfx }}" x-ref="newName{{ $sfx }}" x-model="newCust.name" autocomplete="off" placeholder="e.g. Vikram Shinde"
                       class="input {{ $pc ? '!min-h-9 !text-sm' : '!min-h-12' }}" @keydown.enter.prevent="$refs.search{{ $sfx }}?.focus()">
            </div>
            @include('bills.partials.car-fields', ['pc' => $pc, 'inline' => true])
        </div>
        <div class="mt-2">
            <span class="mb-1 block text-xs font-semibold text-zinc-700">How did they hear about us?</span>
            <div class="flex flex-wrap gap-1.5">
                @foreach (['walk_in' => 'Walk-in', 'instagram' => 'Instagram', 'whatsapp_ad' => 'WhatsApp ad', 'google' => 'Google', 'referral' => 'Friend'] as $v => $l)
                    <button type="button" @click="newCust.source = '{{ $v }}'"
                            :class="newCust.source === '{{ $v }}' ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white text-ink'"
                            class="{{ $pc ? 'h-7 text-xs' : 'h-10 text-[13px]' }} rounded-full border px-3 font-semibold">{{ $l }}</button>
                @endforeach
            </div>
        </div>
    </div>
</div>
