@php
    $editing = $supplier->exists;
    $opening = $supplier->opening_balance_paise ? \App\Support\Money::format($supplier->opening_balance_paise, false, false) : '';
@endphp
<x-layouts.app :title="$editing ? 'Edit supplier' : 'Add supplier'">
    <x-ui.page-header :title="$editing ? 'Edit '.$supplier->name : 'Add supplier'" :back="$editing ? route('suppliers.show', $supplier) : route('suppliers.index')" :back-label="$editing ? 'Ledger' : 'Suppliers'" />

    <form method="POST" action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card mx-auto max-w-2xl space-y-4">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div>
            <label class="label" for="name">Supplier name</label>
            <input id="name" name="name" value="{{ old('name', $supplier->name) }}" class="input" required maxlength="120" autofocus placeholder="e.g. Pune Auto Distributors">
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="mobile">Mobile</label>
                <input id="mobile" name="mobile" value="{{ old('mobile', $supplier->mobile ? \App\Support\Phone::pretty($supplier->mobile) : '') }}" class="input" inputmode="tel" placeholder="98231 07676">
                @error('mobile') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="gstin">GSTIN <span class="font-normal text-muted">(optional)</span></label>
                <input id="gstin" name="gstin" value="{{ old('gstin', $supplier->gstin) }}" class="input uppercase" maxlength="15" placeholder="27ABCDE1234F1Z5">
                @error('gstin') <p class="error">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label class="label" for="address">Address <span class="font-normal text-muted">(optional)</span></label>
            <textarea id="address" name="address" rows="2" class="input py-2" maxlength="500">{{ old('address', $supplier->address) }}</textarea>
        </div>
        <div>
            <label class="label" for="opening_balance">Opening balance <span class="font-normal text-muted">(what we already owe them; minus for an advance)</span></label>
            <div class="relative max-w-xs"><span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted">₹</span>
                <input id="opening_balance" name="opening_balance" value="{{ old('opening_balance', $opening) }}" class="input pl-7 text-right tnum" inputmode="decimal" placeholder="0"></div>
            @error('opening_balance') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="notes">Notes <span class="font-normal text-muted">(optional)</span></label>
            <input id="notes" name="notes" value="{{ old('notes', $supplier->notes) }}" class="input" maxlength="500" placeholder="e.g. Delivers Tuesdays, 30 days credit">
        </div>
        @if ($editing)
            <label class="flex items-center gap-3 text-sm font-medium">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="size-5 rounded border-line-strong" @checked(old('is_active', $supplier->is_active))>
                Active (shows in the supplier list on New purchase)
            </label>
        @endif
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ $editing ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn-ghost">Back</a>
            <button class="btn-accent">{{ $editing ? 'Save changes' : 'Add supplier' }}</button>
        </div>
    </form>
</x-layouts.app>
