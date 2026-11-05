<x-layouts.app title="Home">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold">Hi {{ \Illuminate\Support\Str::before($user->name, ' ') }} 👋</h1>
            <p class="text-sm text-zinc-500">{{ now()->timezone('Asia/Kolkata')->format('l, d M Y') }} · {{ $user->roleLabel() }}</p>
        </div>
    </x-slot:header>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([['Today\'s sales', '₹0'], ['Bills today', '0'], ['Dues pending', '₹0'], ['Low stock items', '0']] as [$label, $value])
            <div class="card">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</div>
                <div class="mt-1 text-2xl font-bold">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="card mt-5">
        <h2 class="text-base font-semibold">CRM setup progress</h2>
        <p class="mt-1 text-sm text-zinc-500">Modules switch on here as they are built. Nothing to do yet.</p>
        <ol class="mt-4 space-y-2 text-sm">
            @foreach ([
                ['Staff logins, roles and shop settings', true],
                ['Customers and their cars', false],
                ['Products and price list', false],
                ['Quotations (PDF, send on WhatsApp)', false],
                ['Bills with GST, payments and print', false],
                ['WhatsApp e-bill sent automatically', false],
                ['Daily sales report and backups', false],
            ] as [$step, $done])
                <li class="flex items-center gap-3">
                    <span @class(['grid size-6 place-items-center rounded-full text-xs font-bold', 'bg-brand-400 text-ink' => $done, 'bg-zinc-100 text-zinc-400' => !$done])>{{ $done ? '✓' : $loop->iteration }}</span>
                    <span @class(['text-zinc-400' => !$done])>{{ $step }}</span>
                </li>
            @endforeach
        </ol>
    </div>
</x-layouts.app>
