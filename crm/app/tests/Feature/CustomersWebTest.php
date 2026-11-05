<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Services\Customers\CustomerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
});

function webCustomer(string $mobile = '9823104512', string $name = 'Rahul Pawar'): Customer
{
    $c = app(CustomerService::class)->findOrCreateByMobile($mobile, ['name' => $name], staff('sales'));
    app(CustomerService::class)->addCar($c, ['make' => 'Hyundai', 'model' => 'Creta', 'reg_no' => 'MH12 KT 4521'], staff('sales'));

    return $c->fresh('cars');
}

it('applies the role matrix to the customer pages', function (string $role, int $list, int $show, int $create, int $edit) {
    $c = webCustomer();
    $u = staff($role);
    $this->actingAs($u)->get(route('customers.index'))->assertStatus($list);
    $this->actingAs($u)->get(route('customers.show', $c))->assertStatus($show);
    $this->actingAs($u)->get(route('customers.create'))->assertStatus($create);
    $this->actingAs($u)->get(route('customers.edit', $c))->assertStatus($edit);
})->with([
    'owner' => ['owner', 200, 200, 200, 200],
    'admin' => ['admin', 200, 200, 200, 200],
    'sales' => ['sales', 200, 200, 200, 200],
    'finance (view only)' => ['finance', 200, 200, 403, 403],
    'marketing (view only)' => ['marketing', 200, 200, 403, 403],
    'technician' => ['technician', 403, 403, 403, 403],
]);

it('adds a customer with a first car, normalising the number and plate', function () {
    $this->actingAs(staff('sales'))->post(route('customers.store'), [
        'mobile' => '098231 07788', 'name' => 'Sneha Kulkarni', 'source' => 'instagram',
        'car' => 'Creta', 'car_make' => '', 'reg_no' => 'mh-12 ab 1234', 'whatsapp_opt_in' => '1',
    ])->assertRedirect();

    $c = Customer::where('mobile', '+919823107788')->with('cars')->firstOrFail();
    expect($c->name)->toBe('Sneha Kulkarni')->and($c->source)->toBe('instagram')
        ->and($c->cars)->toHaveCount(1)
        ->and($c->cars[0]->make)->toBe('Hyundai')->and($c->cars[0]->model)->toBe('Creta')
        ->and($c->cars[0]->reg_no)->toBe('MH12AB1234');
});

it('refuses a duplicate number and names the existing customer', function () {
    $existing = webCustomer('9823104512', 'Rahul Pawar');

    $this->actingAs(staff('sales'))->from(route('customers.create'))->post(route('customers.store'), [
        'mobile' => '+91 98231 04512', 'name' => 'Someone Else', 'source' => 'walk_in',
    ])->assertRedirect(route('customers.create'))
        ->assertSessionHasErrors(['mobile' => 'This number already belongs to Rahul Pawar.']);

    expect(Customer::count())->toBe(1);

    // The form then offers to open the existing profile.
    $this->actingAs(staff('sales'))->withSession(['_old_input' => ['mobile' => '+919823104512']])
        ->get(route('customers.create'));
    $this->actingAs(staff('sales'))->get(route('customers.check', ['mobile' => '98231 04512']))
        ->assertOk()->assertSee('This number already belongs to')->assertSee('Rahul Pawar')
        ->assertSee(route('customers.show', $existing), false);
});

it('says nothing in the duplicate check for a new or incomplete number', function () {
    webCustomer();
    $this->actingAs(staff('sales'))->get(route('customers.check', ['mobile' => '98231']))->assertOk()->assertDontSee('already belongs');
    $this->actingAs(staff('sales'))->get(route('customers.check', ['mobile' => '9999999999']))->assertOk()->assertDontSee('already belongs');
});

it('ignores the customer being edited in the duplicate check', function () {
    $c = webCustomer();
    $this->actingAs(staff('sales'))->get(route('customers.check', ['mobile' => '9823104512', 'ignore' => $c->id]))
        ->assertOk()->assertDontSee('already belongs');
});

it('validates the add form in plain words', function () {
    $this->actingAs(staff('sales'))->post(route('customers.store'), ['mobile' => '98231', 'name' => ''])
        ->assertSessionHasErrors(['mobile', 'name']);
    expect(session('errors')->first('name'))->toBe('Enter the customer’s name.');
});

it('edits a customer and keeps the mobile unique', function () {
    $c = webCustomer();
    $other = webCustomer('9823107788', 'Sneha Kulkarni');

    $this->actingAs(staff('sales'))->put(route('customers.update', $c), [
        'mobile' => '9823104512', 'name' => 'Rahul S. Pawar', 'source' => 'google', 'area' => 'Hadapsar, Pune',
    ])->assertRedirect(route('customers.show', $c));
    expect($c->fresh()->name)->toBe('Rahul S. Pawar')->and($c->fresh()->area)->toBe('Hadapsar, Pune');

    $this->actingAs(staff('sales'))->put(route('customers.update', $c), [
        'mobile' => '9823107788', 'name' => 'Rahul', 'source' => 'walk_in',
    ])->assertSessionHasErrors(['mobile' => 'This number already belongs to Sneha Kulkarni.']);
    expect($c->fresh()->mobile)->toBe('+919823104512');
});

it('adds and edits a car from the profile', function () {
    $c = webCustomer();
    $this->actingAs(staff('sales'))->post(route('customers.cars.store', $c), ['car' => 'Tata Nexon', 'car_make' => 'Tata', 'reg_no' => 'MH12RD8890', 'year' => '2023'])
        ->assertRedirect(route('customers.show', [$c, 'tab' => 'cars']));
    $car = CustomerCar::where('reg_no', 'MH12RD8890')->firstOrFail();
    expect($car->make)->toBe('Tata')->and($car->model)->toBe('Nexon')->and($car->year)->toBe(2023);

    $this->actingAs(staff('sales'))->put(route('customers.cars.update', [$c, $car]), ['car' => 'Nexon', 'colour' => 'White'])->assertRedirect();
    expect($car->fresh()->colour)->toBe('White');

    $this->actingAs(staff('finance'))->post(route('customers.cars.store', $c), ['car' => 'Swift'])->assertForbidden();
});

it('saves notes, archives and restores a customer', function () {
    $c = webCustomer();
    $sales = staff('sales');
    $this->actingAs($sales)->put(route('customers.notes', $c), ['notes' => 'Fleet owner, 4 cars'])->assertRedirect();
    expect($c->fresh()->notes)->toBe('Fleet owner, 4 cars');

    $this->actingAs($sales)->post(route('customers.archive', $c))->assertRedirect(route('customers.index'));
    expect($c->fresh()->isArchived())->toBeTrue();
    $this->actingAs($sales)->get(route('customers.index'))->assertDontSee(route('customers.show', $c), false);

    $this->actingAs($sales)->post(route('customers.restore', $c))->assertRedirect(route('customers.show', $c));
    expect($c->fresh()->isArchived())->toBeFalse();

    $this->actingAs(staff('finance'))->post(route('customers.archive', $c))->assertForbidden();
});

it('lists, searches and filters customers', function () {
    webCustomer('9823104512', 'Rahul Pawar');
    $amit = webCustomer('9823011245', 'Amit Jadhav');
    $amit->update(['source' => 'google']);
    $amit->forceFill(['last_visit_at' => now()->subDays(3)])->save();

    $sales = staff('sales');
    $this->actingAs($sales)->get(route('customers.index'))->assertOk()->assertSee('Rahul Pawar')->assertSee('Amit Jadhav');
    $this->actingAs($sales)->get(route('customers.index', ['q' => '04512']))->assertSee('Rahul Pawar')->assertDontSee('Amit Jadhav');
    $this->actingAs($sales)->get(route('customers.index', ['q' => 'mh 12 kt']))->assertSee('Rahul Pawar');
    $this->actingAs($sales)->get(route('customers.index', ['source' => 'google']))->assertSee('Amit Jadhav')->assertDontSee('Rahul Pawar');
    $this->actingAs($sales)->get(route('customers.index', ['visit' => '30']))->assertSee('Amit Jadhav')->assertDontSee('Rahul Pawar');
    $this->actingAs($sales)->get(route('customers.index', ['visit' => 'never']))->assertSee('Rahul Pawar')->assertDontSee('Amit Jadhav');
    $this->actingAs($sales)->get(route('customers.index', ['q' => 'zzzz']))->assertSee('No customer matches');
});

it('paginates the customer list', function () {
    Customer::factory()->count(30)->create();
    $this->actingAs(staff('sales'))->get(route('customers.index'))->assertSee('1–25 of 30')->assertSee('Next');
    $this->actingAs(staff('sales'))->get(route('customers.index', ['page' => 2]))->assertSee('26–30 of 30');
});

it('shows the profile with call and WhatsApp links, cars and a guarded history', function () {
    $c = webCustomer();
    $this->actingAs(staff('sales'))->get(route('customers.show', $c))->assertOk()
        ->assertSee('tel:+919823104512', false)
        ->assertSee('https://wa.me/919823104512', false)
        ->assertSee('MH 12 KT 4521')
        ->assertSee('Total spent')->assertSee('Last visit')
        ->assertSee('once Billing and Quotations are switched on') // not merged here
        ->assertSee(route('customers.edit', $c), false);

    // View-only roles see the profile without edit or archive actions.
    $this->actingAs(staff('finance'))->get(route('customers.show', $c))->assertOk()
        ->assertDontSee(route('customers.edit', $c), false)
        ->assertDontSee(route('customers.archive', $c), false);
});
