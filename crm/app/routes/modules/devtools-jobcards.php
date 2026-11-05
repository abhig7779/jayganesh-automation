<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Local-only helper for screenshots: /dev/login-as/technician signs in as the first active user
// with that role. Never registered outside APP_ENV=local.
if (app()->environment('local')) {
    Route::withoutMiddleware(['auth', 'active'])->get('/dev/login-as/{role}', function (string $role) {
        $user = User::role($role)->where('is_active', true)->orderBy('id')->firstOrFail();
        Auth::login($user);
        request()->session()->regenerate();
        request()->session()->put('last_seen_at', now()->timestamp);

        $to = request('to');
        $safe = is_string($to) && str_starts_with($to, '/') && ! str_starts_with($to, '//');

        return redirect($safe ? $to : ($role === 'technician' ? route('jobcards.mine') : route('jobcards.index')));
    })->name('dev.login-as');

    // Headless Chrome can't make a window narrower than 500 px, so phone screenshots load the
    // page inside a 390 px iframe: /dev/frame?w=390&h=844&src=/jobcards/mine
    Route::get('/dev/frame', function () {
        $w = max(320, min(1440, (int) request('w', 390)));
        $h = max(480, min(2000, (int) request('h', 844)));
        $src = (string) request('src', '/');
        abort_unless(str_starts_with($src, '/') && ! str_starts_with($src, '//'), 400);

        return response('<!doctype html><html><body style="margin:0;background:#fff">'
            .'<iframe src="'.e($src).'" style="border:0;width:'.$w.'px;height:'.$h.'px;display:block"></iframe></body></html>');
    })->name('dev.frame');
}
