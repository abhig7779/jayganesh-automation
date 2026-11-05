@php $editing = $user->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit staff' : 'Add staff'">
    <x-slot:header>
        <h1 class="text-2xl font-bold">{{ $editing ? 'Edit '.$user->name : 'Add staff' }}</h1>
        <a href="{{ route('staff.index') }}" class="btn-ghost">Back</a>
    </x-slot:header>

    <form method="POST" action="{{ $editing ? route('staff.update', $user) : route('staff.store') }}" class="card max-w-2xl space-y-4">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div>
            <label class="label" for="name">Full name</label>
            <input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="80">
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="phone">Mobile number (used to sign in)</label>
                <input class="input" id="phone" name="phone" inputmode="tel" value="{{ old('phone', $user->phone ? substr($user->phone, 3) : '') }}" required placeholder="98XXXXXXXX">
                @error('phone') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="email">Email (optional)</label>
                <input class="input" id="email" name="email" type="email" value="{{ old('email', $user->email) }}">
                @error('email') <p class="error">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="role">Role</label>
                @php $current = old('role', $user->exists ? $user->getRoleNames()->first() : 'sales'); @endphp
                <select class="input" id="role" name="role" @disabled($editing && $user->is(auth()->user()))>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @if ($editing && $user->is(auth()->user()))
                    <input type="hidden" name="role" value="{{ $current }}">
                    <p class="mt-1 text-xs text-zinc-500">You can't change your own role.</p>
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
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label" for="password">{{ $editing ? 'New password (leave empty to keep)' : 'Password' }}</label>
                <input class="input" id="password" name="password" type="password" autocomplete="new-password" @required(!$editing) minlength="8">
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">Repeat password</label>
                <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
            </div>
        </div>
        @if ($editing)
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="size-4 rounded" @checked(old('is_active', $user->is_active))> Login is active
            </label>
            @error('is_active') <p class="error">{{ $message }}</p> @enderror
        @endif
        <div class="flex justify-end gap-2 pt-2">
            <button class="btn-primary">{{ $editing ? 'Save' : 'Create login' }}</button>
        </div>
    </form>
</x-layouts.app>
