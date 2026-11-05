<?php

/*
 * Audit H2 / L1 / L18: the client IP can't be faked with X-Forwarded-For, the login limit
 * holds across IPs, and "keep me signed in" doesn't outlive the Owner/Finance idle rule.
 */

use App\Models\Branch;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    Branch::create(['name' => 'Main', 'code' => 'JG']);
});

it('ignores X-Forwarded-For from an untrusted client by default', function () {
    Route::get('/_test/ip', fn () => request()->ip());

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4'])
        ->get('/_test/ip')->assertSee('203.0.113.9')->assertDontSee('1.2.3.4');
});

it('trusts X-Forwarded-For only from the proxies in TRUSTED_PROXIES', function () {
    config(['trustedproxy.proxies' => '127.0.0.1,::1']);
    Route::get('/_test/ip', fn () => request()->ip());

    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
        ->get('/_test/ip')->assertSee('198.51.100.7');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
        ->withHeaders(['X-Forwarded-For' => '198.51.100.7'])
        ->get('/_test/ip')->assertSee('203.0.113.9');
});

it('does not reopen the login lock when the client rotates X-Forwarded-For', function () {
    staff('sales', ['phone' => '+919823100041']);
    foreach (range(1, 5) as $i) {
        $this->withHeaders(['X-Forwarded-For' => "10.0.0.$i"])
            ->post('/login', ['login' => '9823100041', 'password' => 'bad']);
    }
    $this->withHeaders(['X-Forwarded-For' => '10.0.0.99'])
        ->post('/login', ['login' => '9823100041', 'password' => 'secret-pass'])
        ->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toContain('Too many attempts');
    $this->assertGuest();
});

it('locks one login after 10 wrong passwords even from different real IPs', function () {
    staff('sales', ['phone' => '+919823100042']);
    foreach (range(1, 10) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.$i"])
            ->post('/login', ['login' => '+91 98231 00042', 'password' => 'bad']);
    }
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.200'])
        ->post('/login', ['login' => '9823100042', 'password' => 'secret-pass'])
        ->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toContain('Too many attempts');
    $this->assertGuest();
});

it('gives the same answer for an unknown login and a wrong password', function () {
    staff('sales', ['phone' => '+919823100043']);
    $this->post('/login', ['login' => '9823100043', 'password' => 'bad']);
    $known = session('errors')->first('login');
    $this->post('/login', ['login' => '9000000000', 'password' => 'bad']);
    expect(session('errors')->first('login'))->toBe($known);
});

it('does not set a remember cookie for Owner and Finance', function () {
    staff('owner', ['phone' => '+919823100044']);
    $this->post('/login', ['login' => '9823100044', 'password' => 'secret-pass', 'remember' => '1'])
        ->assertRedirect(route('dashboard'));
    expect(Auth::user()->getRememberToken())->toBeEmpty();
});

it('keeps "keep me signed in" for the counter', function () {
    staff('sales', ['phone' => '+919823100045']);
    $this->post('/login', ['login' => '9823100045', 'password' => 'secret-pass', 'remember' => '1'])
        ->assertRedirect(route('dashboard'));
    expect(Auth::user()->getRememberToken())->not->toBeEmpty();
});
