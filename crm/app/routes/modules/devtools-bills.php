<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// LOCAL ONLY: sign in as a demo role for screenshots (/dev/login-as/sales?to=/bills/new).
// Never registered outside APP_ENV=local. This file loads inside the auth group, so the
// route opts out of auth + active.
if (app()->environment('local')) {
    Route::get('/dev/login-as/{role}', function (string $role) {
        $user = User::query()->role($role)->where('is_active', true)->first();
        abort_unless($user, 404, "No active {$role} user. Run php artisan migrate:fresh --seed.");
        Auth::login($user);
        request()->session()->regenerate();
        $to = (string) request()->query('to', '/');

        return redirect(str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : '/');
    })->where('role', 'owner|admin|finance|sales|technician')
        ->withoutMiddleware(['auth', 'active'])
        ->name('dev.login-as');
}
