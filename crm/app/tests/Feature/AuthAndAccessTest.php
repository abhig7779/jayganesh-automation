<?php

use App\Models\Branch;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
});

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('lets staff sign in with their mobile number in any format', function () {
    $u = staff('sales', ['phone' => '+919823100001']);
    $this->post('/login', ['login' => '98231 00001', 'password' => 'secret-pass'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($u);
    expect($u->fresh()->last_login_at)->not->toBeNull();
});

it('lets staff sign in with email', function () {
    $u = staff('admin', ['email' => 'admin@example.com']);
    $this->post('/login', ['login' => 'ADMIN@example.com', 'password' => 'secret-pass'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($u);
});

it('rejects a wrong password', function () {
    staff('sales', ['phone' => '+919823100002']);
    $this->post('/login', ['login' => '9823100002', 'password' => 'nope'])->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('blocks switched-off logins', function () {
    staff('sales', ['phone' => '+919823100003', 'is_active' => false]);
    $this->post('/login', ['login' => '9823100003', 'password' => 'secret-pass'])->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('signs out a user whose login is switched off mid-session', function () {
    $u = staff('sales');
    $this->actingAs($u)->get('/')->assertOk();
    $u->update(['is_active' => false]);
    $this->actingAs($u->fresh())->get('/')->assertRedirect(route('login'));
});

it('locks the login after 5 wrong attempts', function () {
    staff('sales', ['phone' => '+919823100004']);
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['login' => '9823100004', 'password' => 'bad']);
    }
    $this->post('/login', ['login' => '9823100004', 'password' => 'secret-pass'])->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toContain('Too many attempts');
    $this->assertGuest();
});

it('signs out the owner after 30 idle minutes but keeps the counter signed in', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->withSession(['last_seen_at' => now()->subMinutes(31)->timestamp])
        ->get('/')->assertRedirect(route('login'));

    $sales = staff('sales');
    $this->actingAs($sales)->withSession(['last_seen_at' => now()->subMinutes(31)->timestamp])
        ->get('/')->assertOk();
});

it('applies the role matrix to staff and settings pages', function (string $role, int $staffStatus, int $settingsStatus) {
    $u = staff($role);
    $this->actingAs($u)->get('/staff')->assertStatus($staffStatus);
    $this->actingAs($u)->get('/settings')->assertStatus($settingsStatus);
})->with([
    'owner' => ['owner', 200, 200],
    'admin' => ['admin', 200, 403],
    'finance' => ['finance', 403, 403],
    'sales' => ['sales', 403, 403],
    'technician' => ['technician', 403, 403],
]);

it('hides menu items a role cannot use', function () {
    $this->actingAs(staff('technician'))->get('/')->assertOk()
        ->assertDontSee(route('settings.edit'), false)->assertDontSee(route('staff.index'), false)->assertSee('Job cards');
    $this->actingAs(staff('owner'))->get('/')->assertSee(route('settings.edit'), false)->assertSee(route('staff.index'), false);
});

it('lets the owner create a staff login that can then sign in', function () {
    $this->actingAs(staff('owner'))->post('/staff', [
        'name' => 'Ravi Counter', 'phone' => '9876500999', 'role' => 'sales',
        'branch_id' => $this->branch->id, 'password' => 'counter-123', 'password_confirmation' => 'counter-123',
    ])->assertRedirect(route('staff.index'));

    $u = User::where('phone', '+919876500999')->firstOrFail();
    expect($u->hasRole('sales'))->toBeTrue()->and($u->created_by)->not->toBeNull();

    auth()->logout();
    $this->post('/login', ['login' => '9876500999', 'password' => 'counter-123'])->assertRedirect(route('dashboard'));
});

it('stops an admin from creating an owner or editing the owner', function () {
    $admin = staff('admin');
    $owner = staff('owner');
    $this->actingAs($admin)->post('/staff', [
        'name' => 'Sneaky', 'phone' => '9876500888', 'role' => 'owner',
        'branch_id' => $this->branch->id, 'password' => 'whatever-1', 'password_confirmation' => 'whatever-1',
    ])->assertSessionHasErrors('role');
    $this->actingAs($admin)->get(route('staff.edit', $owner))->assertForbidden();
});

it('does not let users switch off their own login', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->put(route('staff.update', $owner), [
        'name' => $owner->name, 'phone' => substr($owner->phone, 3), 'role' => 'owner',
        'branch_id' => $this->branch->id, 'is_active' => '0',
    ])->assertSessionHasErrors('is_active');
    expect($owner->fresh()->is_active)->toBeTrue();
});

it('saves settings, validates them and logs the change', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->put('/settings', [
        'shop__name' => 'Jay Ganesh Car Accessories',
        'shop__address' => 'Loni Kalbhor, Pune',
        'tax__state_code' => '27',
        'tax__default_gst_rate' => '28',
        'billing__invoice_prefix' => 'JG',
        'billing__quotation_prefix' => 'JGQ',
        'billing__quotation_valid_days' => '10',
        'billing__upi_id' => 'jayganesh@okaxis',
        'whatsapp__mode' => 'manual',
        'whatsapp__auto_send_invoice' => '1',
    ])->assertRedirect(route('settings.edit'));

    expect(Setting::get('tax.default_gst_rate'))->toBe(28)
        ->and(Setting::get('billing.upi_id'))->toBe('jayganesh@okaxis')
        ->and(Setting::get('billing.quotation_valid_days'))->toBe(10);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'settings']);

    $this->actingAs($owner)->put('/settings', ['tax__gstin' => 'not-a-gstin', 'tax__default_gst_rate' => '7'])
        ->assertSessionHasErrors(['tax__gstin', 'tax__default_gst_rate', 'shop__name']);
});
