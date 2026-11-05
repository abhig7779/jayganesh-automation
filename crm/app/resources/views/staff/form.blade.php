@php
    use App\Support\Permissions;
    $editing = $user->exists;
    $isSelf = $editing && $user->is(auth()->user());
    $current = old('role', $user->exists ? $user->getRoleNames()->first() : 'sales');
    $canExtra = auth()->user()->can('settings.manage') && ! $isSelf;
    $rolePerms = collect(Permissions::ROLES)->map(fn ($p) => $p === ['*'] ? Permissions::ALL : $p)->all();
    $extraOn = old('extra', $editing ? $user->getDirectPermissions()->pluck('name')->all() : []);
    // Plain-English names for the "Role access" ticks.
    $labels = [
        'customers.view' => 'See customers', 'customers.manage' => 'Add & edit customers',
        'quotations.view' => 'See quotations', 'quotations.manage' => 'Make quotations',
        'invoices.view' => 'See bills', 'invoices.create' => 'Make bills', 'invoices.cancel' => 'Cancel bills',
        'payments.collect' => 'Collect payments', 'inventory.view' => 'See stock & prices',
        'inventory.manage' => 'Edit products & stock', 'prices.manage' => 'Change prices',
        'purchases.manage' => 'Purchases', 'jobcards.view' => 'See job cards', 'jobcards.manage' => 'Run job cards',
        'finance.view' => 'Finance (view)', 'finance.manage' => 'Finance (edit)',
        'leads.manage' => 'Ad & WhatsApp leads', 'offers.manage' => 'Send offers',
        'reports.view' => 'Reports', 'reports.margins' => 'Margins & profit', 'reports.all_staff' => 'All staff numbers',
        'settings.manage' => 'Shop settings', 'staff.manage' => 'Staff logins', 'audit.view' => 'Activity log',
    ];
@endphp
<x-layouts.app :title="$editing ? 'Edit staff' : 'Add staff'">
    <x-ui.page-header :title="$editing ? $user->name : 'Add staff'" :subtitle="$editing ? 'Login, role and access' : 'They sign in with their own mobile number.'"
                      :back="route('staff.index')" back-label="Staff" />

    <form method="POST" action="{{ $editing ? route('staff.update', $user) : route('staff.store') }}" class="grid max-w-5xl gap-5 lg:grid-cols-[minmax(0,1fr)_380px]"
          x-data="{ role: @js($current), rolePerms: @js($rolePerms), has(p) { return (this.rolePerms[this.role] || []).includes(p) } }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="card space-y-5">
            <div>
                <label class="label" for="name">Full name</label>
                <input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="80" autocomplete="off" @unless ($editing) autofocus @endunless>
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="phone">Mobile number <span class="font-normal text-muted">(used to sign in)</span></label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 border-r border-line pr-2.5 text-base font-semibold text-muted lg:text-sm">+91</span>
                        <input class="input tnum pl-16 lg:pl-14" id="phone" name="phone" inputmode="numeric" value="{{ old('phone', $user->phone ? substr($user->phone, 3) : '') }}" required placeholder="98231 07676">
                    </div>
                    @error('phone') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="email">Email <span class="font-normal text-muted">(optional)</span></label>
                    <input class="input" id="email" name="email" type="email" value="{{ old('email', $user->email) }}">
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="role">Role</label>
                    <select class="input" id="role" name="role" x-model="role" @disabled($isSelf)>
                        @foreach ($roles as $value => $label)
                            <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($isSelf)
                        <input type="hidden" name="role" value="{{ $current }}">
                        <p class="mt-1.5 text-xs text-muted">You can't change your own role.</p>
                    @endif
                    @error('role') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="branch_id">Branch</label>
                    <select class="input" id="branch_id" name="branch_id">
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected((int) old('branch_id', $user->branch_id) === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid gap-4 border-t border-line pt-5 sm:grid-cols-2">
                <div>
                    <label class="label" for="password">{{ $editing ? 'New password' : 'Password' }} @if ($editing) <span class="font-normal text-muted">(leave empty to keep)</span> @endif</label>
                    <input class="input" id="password" name="password" type="password" autocomplete="new-password" @required(! $editing) minlength="8">
                    @error('password') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="password_confirmation">Repeat password</label>
                    <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                </div>
            </div>
            @if ($editing)
                <label class="flex min-h-11 items-center gap-3 border-t border-line pt-4 text-[15px] font-medium lg:text-sm">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="size-5 accent-ink" @checked(old('is_active', $user->is_active))> Login is switched on
                </label>
                @error('is_active') <p class="error">{{ $message }}</p> @enderror
            @endif
        </div>

        <div class="space-y-5">
            {{-- Role access (read-only) --}}
            <x-ui.panel title="Role access" subtitle="What this role can already do">
                <ul class="grid grid-cols-1 gap-x-4 gap-y-1.5 sm:grid-cols-2 lg:grid-cols-1">
                    @foreach ($labels as $perm => $label)
                        <li x-show="has('{{ $perm }}')" class="flex items-center gap-2 text-[13px] font-medium text-ink-2">
                            <x-icon name="check" class="size-4 shrink-0 text-ok" stroke="2" /> {{ $label }}
                        </li>
                    @endforeach
                </ul>
            </x-ui.panel>

            {{-- Extra access (Owner only) --}}
            @if ($canExtra)
                <x-ui.panel title="Extra access" subtitle="Switch on for this person only, on top of the role">
                    <ul class="-mx-4 divide-y divide-line border-t border-line lg:-mx-5">
                        @foreach (Permissions::EXTRA_ACCESS as $perm => $label)
                            <li class="px-4 lg:px-5">
                                <label class="flex min-h-12 cursor-pointer items-center justify-between gap-3 lg:min-h-11" :class="has('{{ $perm }}') && 'cursor-default'">
                                    <span class="text-[15px] font-medium lg:text-sm">{{ $label }}</span>
                                    <span x-show="has('{{ $perm }}')" class="text-xs font-semibold text-muted">In role</span>
                                    <span x-show="!has('{{ $perm }}')" class="relative inline-flex">
                                        <input type="checkbox" name="extra[]" value="{{ $perm }}" class="peer sr-only" @checked(in_array($perm, $extraOn, true)) :disabled="has('{{ $perm }}')">
                                        <span class="h-7 w-12 rounded-full bg-line-strong transition-colors peer-checked:bg-ink peer-focus-visible:ring-2 peer-focus-visible:ring-ink peer-focus-visible:ring-offset-2"></span>
                                        <span class="absolute top-1 left-1 size-5 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-5"></span>
                                    </span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                    @error('extra.*') <p class="error">{{ $message }}</p> @enderror
                </x-ui.panel>
            @elseif ($editing && $user->getDirectPermissions()->isNotEmpty())
                <x-ui.panel title="Extra access" subtitle="Set by the owner">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($user->getDirectPermissions() as $p)
                            <x-ui.status-badge tone="ink">{{ Permissions::EXTRA_ACCESS[$p->name] ?? $p->name }}</x-ui.status-badge>
                        @endforeach
                    </div>
                </x-ui.panel>
            @endif
        </div>

        <div class="flex items-center gap-3 lg:col-span-2">
            <button class="btn-primary">{{ $editing ? 'Save changes' : 'Create login' }}</button>
            <a href="{{ route('staff.index') }}" class="btn-quiet">Cancel</a>
        </div>
    </form>
</x-layouts.app>
