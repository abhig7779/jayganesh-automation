<?php

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
| Developer-only helpers for screenshots and manual checks. Registered only when APP_ENV=local:
| on staging and production these routes do not exist at all (404). They live in routes/public/
| because routes/modules/ load behind `auth`, and a guest must reach them. This is the ONE place
| for them; modules don't add their own copies.
|
|   /dev/login-as/{role}?to=/followups     sign in as the first active demo user with that role
|   /dev/frame?src=/jobcards/mine&w=390     a page inside a phone-sized iframe (headless Chrome
|                                           can't make a window narrower than ~500 px)
|   /dev/frame/{role}?to=/offers&w=390      the same, signing in as that role first
*/
if (app()->environment('local')) {
    $safePath = fn (?string $to): string => is_string($to) && str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : '/';

    $frame = function (string $src, Request $request) {
        $w = max(320, min(1600, $request->integer('w', 390)));
        $h = max(400, min(2400, $request->integer('h', 844)));

        return response('<!doctype html><html><body style="margin:0;background:#e4e4e7">'
            .'<iframe src="'.e($src).'" style="border:0;width:'.$w.'px;height:'.$h.'px;display:block;margin:0 auto;background:#fff"></iframe></body></html>');
    };

    Route::get('/dev/login-as/{role}', function (Request $request, string $role) use ($safePath) {
        // Only signs in as an existing demo user (migrate:fresh --seed). It never creates an
        // account, so no Owner with a known password can appear through this route.
        $user = User::role($role)->where('is_active', true)->orderBy('id')->first();
        abort_unless($user, 404, "No active {$role} user. Run php artisan migrate:fresh --seed.");
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('last_seen_at', now()->timestamp);

        return redirect($safePath($request->query('to')));
    })->whereIn('role', array_keys(Permissions::ROLES))->name('dev.login-as');

    Route::get('/dev/frame', fn (Request $request) => $frame($safePath($request->query('src')), $request))->name('dev.frame');

    Route::get('/dev/frame/{role}', fn (Request $request, string $role) => $frame(
        url('/dev/login-as/'.$role).'?to='.rawurlencode($safePath($request->query('to'))), $request
    ))->whereIn('role', array_keys(Permissions::ROLES))->name('dev.frame.as');
}
