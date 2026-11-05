<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $throttleKey = Str::lower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'login' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        // Staff can sign in with their email or their mobile number.
        $field = str_contains($data['login'], '@') ? 'email' : 'phone';
        $value = $field === 'phone' ? Phone::normalize($data['login']) : Str::lower($data['login']);

        $user = User::query()->where($field, $value)->first();

        if (! $user || ! Auth::validate([$field => $value, 'password' => $data['password']])) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['login' => 'Wrong login or password.']);
        }

        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['login' => 'This login is switched off. Ask the owner or admin.']);
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, (bool) ($data['remember'] ?? false));
        $request->session()->regenerate();
        $request->session()->put('last_seen_at', now()->timestamp);
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        activity('auth')->causedBy($user)->log('Logged in');

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        if ($user = $request->user()) {
            activity('auth')->causedBy($user)->log('Logged out');
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
