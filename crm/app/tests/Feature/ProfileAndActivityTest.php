<?php

use App\Models\Branch;
use App\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
});

it('lets any signed-in person open My profile and change their name', function (string $role) {
    $u = staff($role);
    $this->actingAs($u)->get(route('profile.edit'))->assertOk()->assertSee('Change password');
    $this->actingAs($u)->put(route('profile.update'), ['name' => 'Ravi Pawar'])->assertRedirect(route('profile.edit'))->assertSessionHasNoErrors();
    expect($u->refresh()->name)->toBe('Ravi Pawar')->and(Hash::check('secret-pass', $u->password))->toBeTrue();
})->with(['owner', 'sales', 'technician']);

it('changes the password only with the right current password', function () {
    $u = staff('sales');

    $this->actingAs($u)->put(route('profile.update'), ['name' => $u->name, 'current_password' => 'wrong-pass', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
        ->assertSessionHasErrors('current_password');
    expect(Hash::check('secret-pass', $u->refresh()->password))->toBeTrue();

    $this->actingAs($u)->put(route('profile.update'), ['name' => $u->name, 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($u)->put(route('profile.update'), ['name' => $u->name, 'current_password' => 'secret-pass', 'password' => 'short', 'password_confirmation' => 'short'])
        ->assertSessionHasErrors('password');
    $this->actingAs($u)->put(route('profile.update'), ['name' => $u->name, 'current_password' => 'secret-pass', 'password' => 'new-secret-1', 'password_confirmation' => 'other-secret'])
        ->assertSessionHasErrors('password');

    $this->actingAs($u)->put(route('profile.update'), ['name' => $u->name, 'current_password' => 'secret-pass', 'password' => 'new-secret-1', 'password_confirmation' => 'new-secret-1'])
        ->assertSessionHasNoErrors()->assertSessionHas('status');
    expect(Hash::check('new-secret-1', $u->refresh()->password))->toBeTrue();
});

it('shows the activity log to owner and admin only', function () {
    $this->actingAs(staff('owner'))->get(route('activity.index'))->assertOk()->assertSee('Activity log');
    $this->actingAs(staff('admin'))->get(route('activity.index'))->assertOk();
    foreach (['finance', 'sales', 'technician'] as $role) {
        $this->actingAs(staff($role))->get(route('activity.index'))->assertForbidden();
    }
});

it('shows who changed what with money as rupees and no raw JSON', function () {
    $owner = staff('owner', ['name' => 'Sachin Owner']);
    $this->actingAs($owner);
    $p = Product::create(['branch_id' => $this->branch->id, 'name' => 'DHC Gleam 2.0 H4', 'sku' => 'GLM-H4', 'price_paise' => 499900, 'gst_rate' => 18]);
    $p->update(['price_paise' => 449900]);

    $res = $this->get(route('activity.index'))->assertOk()
        ->assertSee('Sachin Owner')
        ->assertSee('Changed product · DHC Gleam 2.0 H4')
        ->assertSee('Our price')
        ->assertSee('₹4,999')
        ->assertSee('₹4,499')
        ->assertDontSee('"attributes"', false)
        ->assertDontSee('price_paise');

    // Filters: by type and by person.
    $this->get(route('activity.index', ['type' => 'staff']))->assertOk()->assertDontSee('DHC Gleam 2.0 H4');
    $this->get(route('activity.index', ['type' => 'products', 'user' => $owner->id]))->assertOk()->assertSee('DHC Gleam 2.0 H4');
    $this->get(route('activity.index', ['from' => '2000-01-01', 'to' => '2000-01-02']))->assertOk()->assertSee('Nothing matches these filters');
});

it('shows the new menu items by permission', function () {
    $this->actingAs(staff('owner'))->get(route('dashboard'))->assertSee(route('purchases.index'))->assertSee(route('activity.index'))->assertSee(route('profile.edit'));
    $this->actingAs(staff('finance'))->get(route('dashboard'))->assertSee(route('purchases.index'))->assertDontSee(route('activity.index'));
    $this->actingAs(staff('sales'))->get(route('dashboard'))->assertDontSee(route('purchases.index'))->assertSee(route('profile.edit'));
});
