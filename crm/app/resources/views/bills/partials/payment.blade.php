{{-- Payment mode + the mode's fields. $pc = desktop POS layout. --}}
<div>
    @unless ($pc)<p class="mb-2 text-[15px] font-semibold text-ink">How is the customer paying?</p>@endunless
    <div class="grid grid-cols-5 gap-1 rounded-lg bg-zinc-100 p-1" role="radiogroup" aria-label="Payment mode">
        @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'split' => 'Split', 'later' => 'Later'] as $m => $label)
            <button type="button" role="radio" :aria-checked="mode === '{{ $m }}'" @click="setMode('{{ $m }}')"
                    :class="mode === '{{ $m }}' ? 'bg-ink text-white' : 'text-zinc-600 hover:text-ink'"
                    class="flex {{ $pc ? 'h-10' : 'h-12' }} flex-col items-center justify-center rounded-md text-sm font-semibold">
                {{ $label }}
                @if ($pc)<span class="text-[10px] font-medium opacity-70">[{{ $loop->iteration }}]</span>@endif
            </button>
        @endforeach
    </div>

    <div class="mt-1.5 rounded-lg border border-zinc-200 bg-white p-2">
        {{-- Cash --}}
        <div x-show="mode === 'cash'" class="flex items-center gap-3">
            <label class="flex flex-1 items-center rounded-md border border-zinc-300 focus-within:border-ink">
                <span class="px-2 text-xs font-semibold text-zinc-600">Cash given ₹</span>
                <input x-model="cashGiven" inputmode="decimal" placeholder="optional" class="h-9 min-w-0 flex-1 border-0 bg-transparent pr-2 text-right font-semibold tabular-nums focus:outline-none">
            </label>
            <span class="text-sm font-semibold tabular-nums" :class="change > 0 ? 'text-ink' : 'text-zinc-500'">Change <span x-text="money(change)"></span></span>
        </div>

        {{-- UPI --}}
        <div x-show="mode === 'upi'" class="flex items-center gap-3">
            <template x-if="cfg.upi.id && t.total > 0">
                <button type="button" @click="qrOpen = true" class="shrink-0 rounded border border-zinc-200 bg-white p-1" aria-label="Show UPI QR bigger">
                    <img :src="qrUrl(200)" alt="UPI QR" class="{{ $pc ? 'size-[76px]' : 'size-32' }}">
                </button>
            </template>
            <div class="min-w-0 flex-1 text-[13px]">
                <template x-if="cfg.upi.id">
                    <p class="text-zinc-700">Scan to pay <span class="font-bold text-ink" x-text="money(t.total)"></span> · <span x-text="cfg.upi.id"></span></p>
                </template>
                <template x-if="!cfg.upi.id">
                    <p class="text-amber-700">Add the shop’s UPI ID in Settings to show a QR here.</p>
                </template>
                <input x-model="ref" placeholder="UPI ref no. (optional)" class="mt-1.5 h-9 w-full rounded-md border border-zinc-300 px-2 text-sm focus:border-ink focus:outline-none">
            </div>
        </div>

        {{-- Card --}}
        <div x-show="mode === 'card'">
            <input x-model="ref" placeholder="Card slip / last 4 digits (optional)" class="h-9 w-full rounded-md border border-zinc-300 px-2 text-sm focus:border-ink focus:outline-none">
        </div>

        {{-- Split --}}
        <div x-show="mode === 'split'">
            <div class="grid grid-cols-3 gap-2">
                @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card'] as $m => $label)
                    <label class="flex items-center rounded-md border border-zinc-300 bg-white focus-within:border-ink">
                        <span class="pl-2 text-xs font-semibold text-zinc-600">{{ $label }} ₹</span>
                        <input x-model="split.{{ $m }}" inputmode="decimal" @focus="!split.{{ $m }} && fillSplit('{{ $m }}')" @dblclick="fillSplit('{{ $m }}')"
                               class="h-9 min-w-0 flex-1 border-0 bg-transparent px-2 text-right font-semibold tabular-nums focus:outline-none" aria-label="{{ $label }} amount">
                    </label>
                @endforeach
            </div>
            <div class="mt-1.5 flex items-center justify-between text-[13px]">
                <span x-show="remaining === 0" class="flex items-center gap-1 font-semibold text-green-700"><x-bills.icon name="check-circle" class="size-4" />Remaining: ₹0 (settled)</span>
                <span x-show="remaining > 0" class="font-semibold text-red-600">Remaining: <span x-text="money(remaining)"></span></span>
                <span x-show="remaining < 0" class="font-semibold text-red-600">Over by <span x-text="money(-remaining)"></span></span>
                <button type="button" x-show="cfg.upi.id && qrAmount > 0" @click="qrOpen = true" class="flex items-center gap-1 text-xs font-semibold text-ink"><x-bills.icon name="qr" class="size-4" />Show UPI QR @if($pc)<x-bills.kbd>Q</x-bills.kbd>@endif</button>
            </div>
        </div>

        {{-- Later (due) --}}
        <div x-show="mode === 'later'">
            <div class="flex items-center gap-3">
                <label class="flex flex-1 items-center rounded-md border border-zinc-300 focus-within:border-ink">
                    <span class="px-2 text-xs font-semibold text-zinc-600">Paid now (cash) ₹</span>
                    <input x-model="laterPaid" inputmode="decimal" placeholder="0" class="h-9 min-w-0 flex-1 border-0 bg-transparent pr-2 text-right font-semibold tabular-nums focus:outline-none">
                </label>
                <span class="text-sm font-semibold text-red-600 tabular-nums">Due <span x-text="money(Math.max(0, remaining))"></span></span>
            </div>
            <p class="mt-1.5 text-xs text-zinc-600" x-show="cfg.limits.duePaise !== null">Sales can leave up to <span x-text="money(cfg.limits.duePaise)"></span> due. Above that needs Admin or the Owner.</p>
        </div>
    </div>
</div>
