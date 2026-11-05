<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
| Developer-only: /dev/login-as/{role} signs in as the first active demo user with that role, so
| screenshots and manual checks don't need a password. Registered only when APP_ENV=local; on
| staging and production the route does not exist at all (404).
| (It lives in routes/public/ because routes/modules/ load behind `auth`, and a guest must reach it.)
*/
if (app()->environment('local')) {
    Route::get('/dev/login-as/{role}', function (string $role) {
        $user = User::role($role)->where('is_active', true)->orderBy('id')->firstOrFail();
        Auth::login($user);
        request()->session()->regenerate();
        request()->session()->put('last_seen_at', now()->timestamp);

        $to = (string) request()->query('to', '/');

        return redirect(str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : '/');
    })->whereIn('role', ['owner', 'admin', 'finance', 'sales', 'technician'])->name('dev.login-as');
}
