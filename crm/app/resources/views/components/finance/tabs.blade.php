@php
    $u = auth()->user();
    $tabs = array_filter([
        ['Today', 'finance.index', $u->can('finance.view')],
        ['Expenses', 'finance.expenses.index', $u->can('finance.view')],
        ['Cash close', 'finance.cash-close', $u->can('finance.view')],
        ['Dues', 'finance.dues', $u->can('finance.view')],
        ['Reports', 'reports.index', $u->can('reports.view')],
    ], fn ($t) => $t[2]);
    $current = fn ($route) => request()->routeIs($route) || ($route === 'finance.expenses.index' && request()->routeIs('finance.expenses.*')) || ($route === 'finance.cash-close' && request()->routeIs('finance.cash-closes.*'));
@endphp
@if (count($tabs) > 1)
<nav aria-label="Finance sections" class="-mx-4 mb-5 overflow-x-auto border-b border-zinc-200 px-4 print:hidden sm:mx-0 sm:px-0">
    <div class="flex min-w-max gap-1">
        @foreach ($tabs as [$label, $route])
            <a href="{{ route($route) }}" @class([
                'relative inline-flex min-h-11 items-center px-3 text-sm font-semibold',
                'text-ink after:absolute after:inset-x-2 after:-bottom-px after:h-[3px] after:rounded-full after:bg-brand-400' => $current($route),
                'text-zinc-600 hover:text-ink' => ! $current($route),
            ]) @if ($current($route)) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </div>
</nav>
@endif
