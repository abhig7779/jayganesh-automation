<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** bcrypt of a random string; checked when the login doesn't exist, to even out timing. */
    private const DUMMY_HASH = '$2y$12$9pr6YBScXCYJN5ZmdK8/3.d08gsEZM6Lj3CD5qtlirPyhBj57Gwe2';

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

        // Staff can sign in with their email or their mobile number.
        $field = str_contains($data['login'], '@') ? 'email' : 'phone';
        $value = $field === 'phone' ? Phone::normalize($data['login']) : Str::lower($data['login']);

        // Two limits (audit H2): 5 tries per login + IP, and 10 per login from any IP, so
        // rotating IPs (or a faked X-Forwarded-For) can't be used to guess one staff password.
        $throttleKey = Str::lower($data['login']).'|'.$request->ip();
        $accountKey = 'login-account:'.sha1($field.'|'.$value);
        foreach ([[$throttleKey, 5], [$accountKey, 10]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);
                throw ValidationException::withMessages([
                    'login' => "Too many attempts. Try again in {$seconds} seconds.",
                ]);
            }
        }

        $user = User::query()->where($field, $value)->first();

        // Always run one hash check so the reply time doesn't show whether the login exists (L18).
        $passwordOk = Hash::check($data['password'], $user?->password ?? self::DUMMY_HASH);

        if (! $user || ! $passwordOk) {
            RateLimiter::hit($throttleKey, 60);
            RateLimiter::hit($accountKey, 15 * 60);
            throw ValidationException::withMessages(['login' => 'Wrong login or password.']);
        }

        // Only shown after a correct password, so it doesn't reveal which logins exist.
        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['login' => 'This login is switched off. Ask the owner or admin.']);
        }

        RateLimiter::clear($throttleKey);
        RateLimiter::clear($accountKey);
        // Owner/Finance sign out after 30 idle minutes; a remember cookie would skip that (L1).
        $remember = (bool) ($data['remember'] ?? false) && $user->idleTimeoutMinutes() > 30;
        Auth::login($user, $remember);
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
