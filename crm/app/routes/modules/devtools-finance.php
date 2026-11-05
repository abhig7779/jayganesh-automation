<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Local-only helper for screenshots: /dev/login-as/finance signs in as the demo user with that role.
// Never registered outside APP_ENV=local.
if (app()->environment('local')) {
    Route::withoutMiddleware(['auth', 'active'])->get('/dev/login-as/{role}', function (string $role) {
        $user = User::role($role)->where('is_active', true)->orderBy('id')->firstOrFail();
        Auth::login($user);
        request()->session()->regenerate();
        request()->session()->put('last_seen_at', now()->timestamp);
        $to = (string) request()->query('to', '/');

        return redirect(str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : '/');
    })->whereIn('role', ['owner', 'admin', 'finance', 'sales', 'technician'])->name('dev.login-as');
}
