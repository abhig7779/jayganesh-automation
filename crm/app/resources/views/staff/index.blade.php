<x-layouts.app title="Staff">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold">Staff logins</h1>
            <p class="text-sm text-zinc-500">Everyone signs in with their own mobile number. Switch a login off when someone leaves.</p>
        </div>
        <a href="{{ route('staff.create') }}" class="btn-primary"><x-icon name="plus" /> Add staff</a>
    </x-slot:header>

    <div class="card overflow-hidden p-0 sm:p-0">
        <table class="w-full text-left text-sm">
            <thead class="hidden bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 sm:table-header-group">
                <tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Mobile</th><th class="px-5 py-3">Role</th><th class="px-5 py-3">Last sign-in</th><th class="px-5 py-3">Status</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-zinc-100">
                @foreach ($staff as $s)
                    <tr class="block px-4 py-3 sm:table-row sm:p-0">
                        <td class="block font-semibold sm:table-cell sm:px-5 sm:py-3">{{ $s->name }}<div class="text-xs font-normal text-zinc-500">{{ $s->email }}</div></td>
                        <td class="block text-zinc-700 sm:table-cell sm:px-5 sm:py-3">{{ \App\Support\Phone::pretty($s->phone) }}</td>
                        <td class="inline-block pr-2 sm:table-cell sm:px-5 sm:py-3"><span class="badge bg-zinc-100 text-zinc-800">{{ $s->roleLabel() }}</span></td>
                        <td class="hidden text-zinc-500 sm:table-cell sm:px-5 sm:py-3">{{ $s->last_login_at?->timezone('Asia/Kolkata')->format('d M, h:i A') ?? 'Never' }}</td>
                        <td class="inline-block sm:table-cell sm:px-5 sm:py-3">
                            @if ($s->is_active)
                                <span class="badge bg-emerald-50 text-emerald-700">Active</span>
                            @else
                                <span class="badge bg-red-50 text-red-700">Off</span>
                            @endif
                        </td>
                        <td class="block pt-2 sm:table-cell sm:px-5 sm:py-3 sm:text-right">
                            <a href="{{ route('staff.edit', $s) }}" class="font-semibold text-ink underline decoration-brand-400 decoration-2 underline-offset-4">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
