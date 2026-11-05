@php use App\Support\Money; use App\Support\Phone; @endphp
<x-layouts.app title="Suppliers">
    <x-ui.page-header title="Suppliers" :subtitle="$suppliers->count().' '.($suppliers->count() === 1 ? 'supplier' : 'suppliers').' · we owe '.Money::format($owed)" :back="route('purchases.index')" back-label="Purchases">
        <x-slot:actions>
            <a href="{{ route('suppliers.create') }}" class="btn-ghost"><x-icon name="user-plus" class="size-5" /> Add supplier</a>
            <a href="{{ route('purchases.create') }}" class="btn-accent"><x-icon name="plus" class="size-5" stroke="2" /> New purchase</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="GET" action="{{ route('suppliers.index') }}" class="mb-4 flex gap-2">
        <input type="search" name="q" value="{{ $q }}" class="input max-w-md" placeholder="Search name, mobile or GSTIN" aria-label="Search suppliers">
        <button class="btn-ghost">Search</button>
    </form>

    <section class="overflow-hidden rounded-xl border border-line bg-surface">
        @if ($suppliers->isEmpty())
            <x-ui.empty-state icon="users" :text="$q ? 'No supplier matches “'.$q.'”.' : 'No suppliers yet. Add one, or type a new name while entering a purchase.'">
                <a href="{{ route('suppliers.create') }}" class="btn-ghost">+ Add supplier</a>
            </x-ui.empty-state>
        @else
            <table class="table-pc hidden lg:table">
                <thead><tr><th>Supplier</th><th>Mobile</th><th>GSTIN</th><th>Last bill</th><th class="text-right">Balance</th></tr></thead>
                <tbody>
                    @foreach ($suppliers as $s)
                        @php $bal = $s->balancePaise(); @endphp
                        <tr data-href="{{ route('suppliers.show', $s) }}" @class(['opacity-60' => ! $s->is_active])>
                            <td><a href="{{ route('suppliers.show', $s) }}" class="font-semibold text-ink hover:underline">{{ $s->name }}</a> @unless ($s->is_active) <x-ui.status-badge status="off" /> @endunless</td>
                            <td class="whitespace-nowrap">{{ $s->mobile ? Phone::pretty($s->mobile) : '—' }}</td>
                            <td class="whitespace-nowrap">{{ $s->gstin ?: '—' }}</td>
                            <td class="whitespace-nowrap">{{ $s->last_bill_date ? \Illuminate\Support\Carbon::parse($s->last_bill_date)->format('j M Y') : '—' }}</td>
                            <td @class(['tnum text-right font-semibold', 'text-bad' => $bal > 0, 'text-ok' => $bal < 0, 'text-faint' => $bal === 0])>
                                {{ $bal > 0 ? Money::format($bal).' to pay' : ($bal < 0 ? Money::format(-$bal).' advance' : 'Clear') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <ul class="divide-y divide-line lg:hidden">
                @foreach ($suppliers as $s)
                    @php $bal = $s->balancePaise(); @endphp
                    <li>
                        <a href="{{ route('suppliers.show', $s) }}" class="flex min-h-16 items-center gap-3 px-4 py-3 active:bg-sunken">
                            <x-ui.avatar-initials :name="$s->name" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[15px] font-semibold">{{ $s->name }}</div>
                                <div class="truncate text-[13px] text-muted">{{ $s->mobile ? Phone::pretty($s->mobile) : 'No mobile' }}</div>
                            </div>
                            <div @class(['tnum text-right text-[15px] font-semibold', 'text-bad' => $bal > 0, 'text-ok' => $bal < 0, 'text-faint' => $bal === 0])>
                                {{ $bal === 0 ? 'Clear' : Money::format(abs($bal)) }}
                                @if ($bal !== 0) <div class="text-[11px] font-semibold">{{ $bal > 0 ? 'to pay' : 'advance' }}</div> @endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layouts.app>
