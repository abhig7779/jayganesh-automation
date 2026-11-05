<?php

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);

    // Leads and offers belong to another module; stand-in routes with the same names and the
    // same permission middleware prove the access rule without depending on that branch.
    Route::middleware(['web', 'auth', 'permission:leads.manage'])->get('/_t/leads', fn () => 'leads')->name('leads.index');
    Route::middleware(['web', 'auth', 'permission:offers.manage'])->get('/_t/offers', fn () => 'offers')->name('offers.index');
    Route::getRoutes()->refreshNameLookups();
});

it('keeps leads and offers away from the counter by default', function () {
    $sales = staff('sales');
    expect($sales->can('leads.manage'))->toBeFalse()->and($sales->can('offers.manage'))->toBeFalse();
    $this->actingAs($sales)->get(route('leads.index'))->assertForbidden();
    $this->actingAs($sales)->get(route('offers.index'))->assertForbidden();
});

it('lets the owner switch on leads for one counter person', function () {
    $owner = staff('owner');
    $sales = staff('sales', ['phone' => '+919876500777']);

    $this->actingAs($owner)->put(route('staff.update', $sales), [
        'name' => $sales->name, 'phone' => '9876500777', 'role' => 'sales', 'branch_id' => $this->branch->id,
        'is_active' => '1', 'extra' => ['leads.manage'],
    ])->assertRedirect(route('staff.index'));

    $sales = User::find($sales->id);
    expect($sales->hasRole('sales'))->toBeTrue()
        ->and($sales->getDirectPermissions()->pluck('name')->all())->toBe(['leads.manage'])
        ->and($sales->can('leads.manage'))->toBeTrue()
        ->and($sales->can('offers.manage'))->toBeFalse();
    $this->actingAs($sales)->get(route('leads.index'))->assertOk();
    $this->actingAs($sales)->get(route('offers.index'))->assertForbidden();

    // Switching it off again removes it.
    $this->actingAs($owner)->put(route('staff.update', $sales), [
        'name' => $sales->name, 'phone' => '9876500777', 'role' => 'sales', 'branch_id' => $this->branch->id, 'is_active' => '1',
    ]);
    expect(User::find($sales->id)->can('leads.manage'))->toBeFalse();
});

it('gives the marketing role leads and offers', function () {
    $m = staff('marketing');
    $this->actingAs($m)->get(route('leads.index'))->assertOk();
    $this->actingAs($m)->get(route('offers.index'))->assertOk();
});

it('lets only the owner change extra access', function () {
    $admin = staff('admin');
    $sales = staff('sales', ['phone' => '+919876500666']);
    $sales->givePermissionTo('leads.manage');

    // Admin saves the form without the extra field: nothing changes, and admin can't add any.
    $this->actingAs($admin)->put(route('staff.update', $sales), [
        'name' => $sales->name, 'phone' => '9876500666', 'role' => 'sales', 'branch_id' => $this->branch->id,
        'is_active' => '1', 'extra' => ['offers.manage'],
    ])->assertRedirect();
    $sales = User::find($sales->id);
    expect($sales->can('leads.manage'))->toBeTrue()->and($sales->can('offers.manage'))->toBeFalse();

    $this->actingAs($admin)->get(route('staff.edit', $sales))->assertOk()->assertDontSee('name="extra[]"', false);
    $this->actingAs(staff('owner'))->get(route('staff.edit', $sales))->assertOk()
        ->assertSee('Extra access')->assertSee('Role access')->assertSee('name="extra[]"', false)->assertSee('Marketing');
});

it('rejects unknown extra permissions', function () {
    $sales = staff('sales', ['phone' => '+919876500555']);
    $this->actingAs(staff('owner'))->put(route('staff.update', $sales), [
        'name' => $sales->name, 'phone' => '9876500555', 'role' => 'sales', 'branch_id' => $this->branch->id,
        'is_active' => '1', 'extra' => ['settings.manage'],
    ])->assertSessionHasErrors('extra.0');
    expect(User::find($sales->id)->can('settings.manage'))->toBeFalse();
});

it('shows leads and offers on Home only for people who have them', function () {
    $sales = staff('sales');
    $this->actingAs($sales)->get('/')->assertOk()->assertDontSee('Send offer')->assertDontSee('Ad &amp; WhatsApp enquiries', false);
    $sales->givePermissionTo('offers.manage');
    $this->actingAs(User::find($sales->id))->get('/')->assertOk()->assertSee('Send offer');
});
