<x-layouts.base title="Sign in">
<div class="flex min-h-dvh flex-col items-center justify-center bg-ink px-4 py-10" style="padding-top: max(2.5rem, env(safe-area-inset-top))">
    <div class="w-full max-w-[400px]">
        <div class="mb-8 flex flex-col items-center text-center">
            <img src="{{ asset('img/logo.png') }}" alt="Jay Ganesh Car Accessories" class="h-20 w-auto">
            <div class="mt-3 flex items-center gap-2">
                <span class="text-lg font-bold tracking-[-0.01em] text-white">Jay Ganesh</span>
                <span class="rounded bg-brand px-1.5 py-px text-[10px] leading-4 font-bold tracking-wide text-on-brand">CRM</span>
            </div>
            <p class="mt-1 text-[13px] font-medium text-zinc-400">Staff sign in</p>
        </div>
        <form method="POST" action="{{ url('/login') }}" class="rounded-2xl bg-surface p-5 sm:p-6">
            @csrf
            <div class="space-y-4">
                <div>
                    <label for="login" class="label">Mobile number or email</label>
                    <input id="login" name="login" type="text" inputmode="email" autocomplete="username" required autofocus
                           value="{{ old('login') }}" @class(['input', 'border-bad' => $errors->has('login')]) placeholder="98231 07676">
                    @error('login') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div x-data="{ show: false }">
                    <label for="password" class="label">Password</label>
                    <div class="relative">
                        <input id="password" name="password" :type="show ? 'text' : 'password'" type="password" autocomplete="current-password" required class="input pr-16">
                        <button type="button" @click="show = !show" class="absolute top-1/2 right-1.5 inline-flex h-9 -translate-y-1/2 items-center rounded-md px-2.5 text-[13px] font-semibold text-muted hover:text-ink" x-text="show ? 'Hide' : 'Show'">Show</button>
                    </div>
                </div>
                <label class="flex min-h-11 items-center gap-3 text-[15px] font-medium text-ink-2 lg:text-sm">
                    <input type="checkbox" name="remember" value="1" class="size-5 accent-ink"> Keep me signed in on this device
                </label>
                <button class="btn-accent w-full text-[17px] lg:min-h-12 lg:text-[15px]">Sign in</button>
            </div>
            <p class="mt-4 text-center text-[13px] text-muted">Forgot your password? Ask the owner or admin to reset it.</p>
        </form>
        <p class="mt-6 text-center text-xs text-zinc-500">Jay Ganesh Car Accessories · Kawadipat Toll Naka, Loni Kalbhor</p>
    </div>
</div>
</x-layouts.base>
