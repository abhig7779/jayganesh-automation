@php use App\Support\Permissions; use App\Support\Phone; @endphp
<x-layouts.app title="Staff">
    <x-ui.page-header title="Staff logins" subtitle="Everyone signs in with their own mobile number. Switch a login off when someone leaves.">
        <x-slot:actions>
            <a href="{{ route('staff.create') }}" class="btn-primary"><x-icon name="plus" class="size-5" /> Add staff</a>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- PC table --}}
    <div class="hidden overflow-hidden rounded-xl border border-line bg-surface lg:block">
        <table class="table-pc">
            <thead><tr><th>Name</th><th>Mobile</th><th>Role</th><th>Extra access</th><th>Last sign-in</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($staff as $s)
                    <tr data-href="{{ route('staff.edit', $s) }}">
                        <td>
                            <a href="{{ route('staff.edit', $s) }}" class="flex items-center gap-3 py-1.5">
                                <x-ui.avatar-initials :name="$s->name" size="sm" />
                                <span class="min-w-0"><span class="block truncate font-semibold text-ink">{{ $s->name }}</span>@if ($s->email)<span class="block truncate text-xs text-muted">{{ $s->email }}</span>@endif</span>
                            </a>
                        </td>
                        <td class="tnum whitespace-nowrap">{{ Phone::pretty($s->phone) }}</td>
                        <td>{{ $s->roleLabel() }}</td>
                        <td>
                            @forelse ($s->getDirectPermissions() as $p)
                                <x-ui.status-badge tone="ink">{{ Permissions::EXTRA_ACCESS[$p->name] ?? $p->name }}</x-ui.status-badge>
                            @empty <span class="text-faint">—</span> @endforelse
                        </td>
                        <td class="whitespace-nowrap text-muted">{{ $s->last_login_at?->timezone('Asia/Kolkata')->format('j M, h:i A') ?? 'Never' }}</td>
                        <td><x-ui.status-badge :status="$s->is_active ? 'active' : 'off'" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Phone rows --}}
    <div class="-mx-4 divide-y divide-line border-y border-line bg-surface lg:hidden">
        @foreach ($staff as $s)
            <x-ui.list-row :href="route('staff.edit', $s)" :avatar="$s->name" :title="$s->name" :subtitle="$s->roleLabel().' · '.Phone::pretty($s->phone)">
                <x-slot:trailing><x-ui.status-badge :status="$s->is_active ? 'active' : 'off'" /></x-slot:trailing>
            </x-ui.list-row>
        @endforeach
    </div>
</x-layouts.app>
