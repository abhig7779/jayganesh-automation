<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Local-only helper for screenshots: /dev/login-as/sales signs in as the demo user of that role.
// Never registered outside APP_ENV=local. This file is loaded inside auth + active, so the
// route is registered on its own group without those middlewares.
if (app()->environment('local')) {
    Route::withoutMiddleware(['auth', 'active'])->get('/dev/login-as/{role}', function (string $role) {
        $user = User::role($role)->where('is_active', true)->orderBy('id')->firstOrFail();
        Auth::login($user);
        request()->session()->regenerate();

        return redirect(request('to', route('followups.index')));
    })->whereIn('role', ['owner', 'admin', 'finance', 'sales', 'marketing', 'technician'])->name('dev.login-as');
}
