<x-layouts.app title="My profile">
    <div class="mx-auto max-w-xl">
    <x-ui.page-header title="My profile" :subtitle="$user->roleLabel().' · '.(\App\Support\Phone::pretty($user->phone) ?: $user->email)" />

    <form method="POST" action="{{ route('profile.update') }}" class="space-y-5" x-data="{ show: false }">
        @csrf
        @method('PUT')

        <section class="card space-y-4">
            <h2 class="section-label">Your details</h2>
            <div>
                <label class="label" for="name">Name</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" class="input" required maxlength="80" autocomplete="name">
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <p class="text-[13px] text-muted">Your mobile number is your login. To change it, or your role, ask the owner (Staff page).</p>
        </section>

        <section class="card space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="section-label">Change password</h2>
                <button type="button" class="text-[13px] font-semibold text-ink underline-offset-4 hover:underline" @click="show = !show" x-text="show ? 'Hide passwords' : 'Show passwords'"></button>
            </div>
            <p class="text-[13px] text-muted">Leave these empty to keep your password. If the owner gave you a temporary password, set your own here.</p>
            <div>
                <label class="label" for="current_password">Current password</label>
                <input id="current_password" name="current_password" :type="show ? 'text' : 'password'" type="password" class="input" autocomplete="current-password">
                @error('current_password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="password">New password <span class="font-normal text-muted">(8+ characters)</span></label>
                    <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" class="input" autocomplete="new-password" minlength="8">
                    @error('password') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="password_confirmation">New password again</label>
                    <input id="password_confirmation" name="password_confirmation" :type="show ? 'text' : 'password'" type="password" class="input" autocomplete="new-password">
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button class="btn-accent w-full sm:w-auto"><x-icon name="check" class="size-5" stroke="2" /> Save</button>
        </div>
    </form>
    </div>
</x-layouts.app>
