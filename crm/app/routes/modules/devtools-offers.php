<?php

use App\Models\Branch;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Local-only helper for screenshots: /dev/login-as/owner?to=/offers/new signs in as the demo
// user for that role. Never registered outside APP_ENV=local.
if (app()->environment('local')) {
    Route::get('/dev/login-as/{role}', function (Request $request, string $role) {
        abort_unless(array_key_exists($role, Permissions::ROLES), 404);
        $user = User::role($role)->orderBy('id')->first() ?? tap(User::create([
            'name' => ucfirst($role).' (demo)',
            'email' => "$role@crm.test",
            'password' => 'password',
            'branch_id' => Branch::query()->value('id'),
        ]))->syncRoles([$role]);
        Auth::login($user);
        $request->session()->regenerate();
        $to = (string) $request->query('to', '/');

        return redirect(str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : '/');
    })->withoutMiddleware(['auth', 'active'])->name('dev.login-as');

    // Headless Chrome won't make a window narrower than ~500 px, so phone screenshots load the
    // page in a 390 px iframe: /dev/frame/owner?to=/offers&w=390&h=844
    Route::get('/dev/frame/{role}', function (Request $request, string $role) {
        $w = max(320, min(1600, $request->integer('w', 390)));
        $h = max(480, min(2000, $request->integer('h', 844)));
        $src = url('/dev/login-as/'.$role).'?to='.rawurlencode((string) $request->query('to', '/'));

        return response('<!doctype html><html><body style="margin:0;background:#888">'
            .'<iframe src="'.e($src).'" style="border:0;width:'.$w.'px;height:'.$h.'px;display:block"></iframe></body></html>');
    })->withoutMiddleware(['auth', 'active'])->name('dev.frame');
}
