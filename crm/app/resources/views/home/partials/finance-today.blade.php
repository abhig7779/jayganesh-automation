{{--
    Today's money from the finance module (Dashboard\FinanceHome::today()): expenses, cash expected
    in the drawer and whether the day is closed. Owner, Admin (view) and Finance homes.
--}}
@php
    $has = fn (string $r) => \Illuminate\Support\Facades\Route::has($r);
    $exp = $finance['expenses'];
@endphp
<x-ui.panel title="Money today" subtitle="Expenses and the cash drawer">
    <x-slot:aside>
        @if ($has('finance.index')) <a href="{{ route('finance.index') }}" class="text-[13px] font-semibold hover:underline">Finance</a> @endif
    </x-slot:aside>
    <div class="grid grid-cols-2 gap-4">
        <x-ui.stat label="Expenses" :paise="$exp['total_paise']" :hint="$exp['count'] ? $exp['count'].' '.str('entry')->plural($exp['count']).' · cash '.\App\Support\Money::format($exp['cash_paise']) : 'None yet today'"
                   :href="$has('finance.expenses.index') ? route('finance.expenses.index') : null" />
        <x-ui.stat label="Cash expected" :paise="$finance['expected_cash_paise']" :hint="$finance['billing_installed'] ? 'Opening + cash in − cash out' : 'Opening cash minus cash expenses'" />
    </div>
    <div class="mt-4 flex items-center justify-between gap-3 border-t border-line pt-3">
        @if ($finance['cash_closed'])
            <span class="flex items-center gap-1.5 text-[13px] font-semibold text-ok"><x-icon name="check" class="size-4" stroke="2" /> Today's cash is closed</span>
        @else
            <span class="flex items-center gap-1.5 text-[13px] font-medium text-muted"><x-icon name="lock" class="size-4" /> Close the drawer before you leave</span>
            @if ($has('finance.cash-close') && $user->can('finance.manage'))
                <a href="{{ route('finance.cash-close') }}" class="btn-ghost min-h-10 px-3 text-[13px] lg:min-h-8">Cash close</a>
            @endif
        @endif
    </div>
    @if ($finance['missing_close'])
        <a href="{{ $finance['missing_close']['url'] ?? '#' }}" class="mt-3 flex items-center gap-2 rounded-lg border border-bad-line bg-bad-tint px-3 py-2 text-[13px] font-semibold text-bad">
            <x-icon name="alert" class="size-4 shrink-0" /> {{ $finance['missing_close']['label'] }}
        </a>
    @endif
</x-ui.panel>
