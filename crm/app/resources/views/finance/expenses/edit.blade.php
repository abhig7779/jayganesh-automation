<x-layouts.app title="Edit expense">
    <x-slot:header>
        <div class="flex items-center gap-2">
            <a href="{{ route('finance.expenses.index') }}" class="-ml-2 grid size-11 place-items-center rounded-lg text-ink hover:bg-zinc-100" aria-label="Back to expenses"><x-finance.icon name="back" class="size-6" /></a>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Edit expense</h1>
                <p class="text-sm font-medium text-zinc-600">Entered by {{ $expense->creator?->name ?? 'someone' }} on {{ $expense->created_at->timezone('Asia/Kolkata')->format('j M, g:i A') }}</p>
            </div>
        </div>
    </x-slot:header>

    <div class="mx-auto max-w-xl space-y-4">
        <div class="card">
            @include('finance.expenses._form', ['expense' => $expense, 'prefix' => 'edit'])
        </div>
        <form method="POST" action="{{ route('finance.expenses.cancel', $expense) }}" class="card flex flex-wrap items-center justify-between gap-3"
              x-data @submit="if (! confirm('Cancel this expense? It will stop counting in totals and the cash close.')) $event.preventDefault()">
            @csrf
            <p class="text-sm text-zinc-700">Entered by mistake? Cancel it. It stays in the history but stops counting.</p>
            <button class="btn border border-red-600 bg-white text-red-600 hover:bg-red-50">Cancel expense</button>
        </form>
    </div>
</x-layouts.app>
