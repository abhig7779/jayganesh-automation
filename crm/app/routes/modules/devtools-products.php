<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Local-only: sign in as a demo role to screenshot pages (headless Chrome can't fill the form).
// Never registered outside APP_ENV=local.
if (app()->environment('local')) {
    Route::get('/dev/login-as/{role}', function (string $role) {
        $user = User::query()->where('email', "{$role}@crm.test")->firstOrFail();
        Auth::login($user);
        request()->session()->regenerate();

        $to = (string) request('to', '/');

        return redirect(str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : '/');
    })->withoutMiddleware(['auth', 'active'])->where('role', 'owner|admin|finance|sales|technician')->name('dev.login-as');

    // Headless Chrome can't make a window narrower than ~500 px, so phone screenshots load the
    // page in a 390 px iframe on the same origin (the login cookie still works).
    Route::get('/dev/frame', function () {
        $w = min(max((int) request('w', 390), 320), 1600);
        $h = min(max((int) request('h', 844), 400), 2000);
        $src = (string) request('src', '/');
        $src = str_starts_with($src, '/') && ! str_starts_with($src, '//') ? $src : '/';

        return response('<!doctype html><html><body style="margin:0;background:#fff"><iframe src="'.e($src).'" style="border:0;width:'.$w.'px;height:'.$h.'px;display:block;margin:0 auto"></iframe></body></html>');
    })->name('dev.frame');
}
