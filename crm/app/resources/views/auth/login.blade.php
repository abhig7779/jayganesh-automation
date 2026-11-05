<x-layouts.base title="Sign in">
<div class="flex min-h-dvh items-center justify-center bg-ink px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <img src="{{ asset('img/logo.png') }}" alt="Jay Ganesh Car Accessories" class="mx-auto h-24 w-auto">
            <p class="mt-2 text-xs font-semibold tracking-[0.3em] text-brand-400">STAFF CRM</p>
        </div>
        <form method="POST" action="{{ url('/login') }}" class="rounded-3xl bg-white p-6 shadow-xl">
            @csrf
            <h1 class="mb-5 text-xl font-bold">Sign in</h1>
            <div class="mb-4">
                <label for="login" class="label">Mobile number or email</label>
                <input id="login" name="login" type="text" inputmode="email" autocomplete="username" required autofocus
                       value="{{ old('login') }}" class="input" placeholder="98XXXXXXXX">
                @error('login') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="mb-4">
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required class="input">
            </div>
            <label class="mb-5 flex items-center gap-2 text-sm text-zinc-700">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-zinc-300"> Keep me signed in on this device
            </label>
            <button class="btn-accent w-full">Sign in</button>
            <p class="mt-4 text-center text-xs text-zinc-500">Forgot your password? Ask the owner or admin to reset it.</p>
        </form>
    </div>
</div>
</x-layouts.base>
