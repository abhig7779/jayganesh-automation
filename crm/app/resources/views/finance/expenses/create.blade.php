<x-layouts.app title="Add expense">
    <x-slot:header>
        <div class="flex items-center gap-2">
            <a href="{{ route('finance.expenses.index') }}" class="-ml-2 grid size-11 place-items-center rounded-lg text-ink hover:bg-zinc-100" aria-label="Back to expenses"><x-finance.icon name="back" class="size-6" /></a>
            <h1 class="text-2xl font-bold tracking-tight">Add expense</h1>
        </div>
    </x-slot:header>

    <div class="card mx-auto max-w-xl">
        @include('finance.expenses._form', ['expense' => $expense, 'prefix' => 'new', 'autofocus' => true])
    </div>
</x-layouts.app>
