{{-- Expense form: amount first, then what it was for, how it was paid, when. Used on create, edit and the desktop quick-add panel. --}}
@php
    $isEdit = $expense->exists;
    $prefix = $prefix ?? 'exp';
    $amountValue = old('amount', $expense->amount_paise ? \App\Support\Money::format($expense->amount_paise, false, false) : '');
    $catValue = (int) old('expense_category_id', $expense->expense_category_id);
    $modeValue = old('mode', $expense->mode ?: 'cash');
@endphp
<form method="POST" enctype="multipart/form-data"
      action="{{ $isEdit ? route('finance.expenses.update', $expense) : route('finance.expenses.store') }}"
      class="space-y-5" x-data="{ busy: false }" @submit="busy = true">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div>
        <label class="label" for="{{ $prefix }}-amount">Amount</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-lg font-semibold text-zinc-600">₹</span>
            <input id="{{ $prefix }}-amount" name="amount" type="text" inputmode="decimal" autocomplete="off" required
                   value="{{ $amountValue }}" placeholder="0" @if ($autofocus ?? false) autofocus @endif
                   class="input min-h-14 pl-8 text-right text-2xl font-bold tabular-nums sm:text-2xl @error('amount') border-red-600 @enderror">
        </div>
        @error('amount') <p class="error">{{ $message }}</p> @enderror
    </div>

    <fieldset>
        <legend class="label">Spent on</legend>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 {{ $prefix === 'quick' ? 'lg:grid-cols-2' : '' }}">
            @foreach ($categories as $cat)
                <label class="relative">
                    <input type="radio" name="expense_category_id" value="{{ $cat->id }}" class="peer sr-only" @checked($catValue === $cat->id) required>
                    <span class="flex min-h-11 cursor-pointer items-center justify-center rounded-lg border border-zinc-300 bg-white px-2 text-center text-sm font-semibold text-ink peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-400">{{ $cat->name }}</span>
                </label>
            @endforeach
        </div>
        @error('expense_category_id') <p class="error">{{ $message }}</p> @enderror
    </fieldset>

    <fieldset>
        <legend class="label">Paid by</legend>
        <div class="grid grid-cols-3 gap-1 rounded-lg bg-zinc-200/70 p-1">
            @foreach (\App\Models\Expense::MODES as $key => $label)
                <label>
                    <input type="radio" name="mode" value="{{ $key }}" class="peer sr-only" @checked($modeValue === $key)>
                    <span class="flex min-h-11 cursor-pointer items-center justify-center rounded-md text-sm font-medium text-zinc-600 peer-checked:border peer-checked:border-zinc-200 peer-checked:bg-white peer-checked:font-semibold peer-checked:text-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand-400">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <p class="mt-1 text-xs font-medium text-zinc-600">Cash expenses come out of the drawer and lower the expected cash at day close.</p>
        @error('mode') <p class="error">{{ $message }}</p> @enderror
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="label" for="{{ $prefix }}-date">Date</label>
            <input id="{{ $prefix }}-date" name="date" type="date" required class="input @error('date') border-red-600 @enderror"
                   value="{{ old('date', optional($expense->date)->toDateString() ?? \App\Services\Finance\Period::today()->toDateString()) }}"
                   max="{{ \App\Services\Finance\Period::today()->toDateString() }}">
            @error('date') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label" for="{{ $prefix }}-paid-to">Paid to <span class="font-normal text-zinc-500">(optional)</span></label>
            <input id="{{ $prefix }}-paid-to" name="paid_to" type="text" maxlength="120" class="input" value="{{ old('paid_to', $expense->paid_to) }}" placeholder="e.g. MSEDCL, tea stall, tempo">
            @error('paid_to') <p class="error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label class="label" for="{{ $prefix }}-note">Note <span class="font-normal text-zinc-500">(optional)</span></label>
        <textarea id="{{ $prefix }}-note" name="note" rows="2" maxlength="500" class="input py-2" placeholder="What was it for?">{{ old('note', $expense->note) }}</textarea>
        @error('note') <p class="error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="label" for="{{ $prefix }}-receipt">Receipt photo <span class="font-normal text-zinc-500">(optional)</span></label>
        <label for="{{ $prefix }}-receipt" class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-dashed border-zinc-300 bg-white px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-50"
               x-data="{ name: '' }">
            <x-finance.icon name="camera" class="size-5 text-zinc-600" />
            <span x-text="name || 'Take a photo or choose a file'">Take a photo or choose a file</span>
            <input id="{{ $prefix }}-receipt" name="receipt" type="file" accept="image/*,application/pdf" class="sr-only" @change="name = $event.target.files[0]?.name || ''">
        </label>
        @if ($isEdit && $expense->receipt_path)
            <div class="mt-2 flex items-center gap-4 text-sm">
                <a href="{{ route('finance.expenses.receipt', $expense) }}" target="_blank" class="font-semibold underline decoration-brand-400 decoration-2 underline-offset-4">View current receipt</a>
                <label class="flex items-center gap-2 text-zinc-700"><input type="checkbox" name="remove_receipt" value="1" class="size-4 rounded"> Remove it</label>
            </div>
        @endif
        @error('receipt') <p class="error">{{ $message }}</p> @enderror
    </div>

    <button type="submit" class="btn-accent min-h-14 w-full text-[17px] lg:min-h-11 lg:text-sm" :disabled="busy">
        <span x-show="busy" x-cloak class="size-4 animate-spin rounded-full border-2 border-ink border-t-transparent"></span>
        {{ $isEdit ? 'Save changes' : 'Save expense' }}
    </button>
</form>
