<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Services\Customers\CustomerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->service = app(CustomerService::class);
});

function counterCustomer(string $mobile, string $name = 'Rahul Kale', array $car = []): Customer
{
    $c = app(CustomerService::class)->findOrCreateByMobile($mobile, ['name' => $name], staff('sales'));
    if ($car) {
        app(CustomerService::class)->addCar($c, $car, staff('sales'));
    }

    return $c;
}

it('stores the same number typed three ways as one customer', function () {
    $by = staff('sales');
    $a = $this->service->findOrCreateByMobile('98231 07676', ['name' => 'Rahul Kale'], $by);
    $b = $this->service->findOrCreateByMobile('+91-98231-07676', ['name' => 'Someone Else'], $by);
    $c = $this->service->findOrCreateByMobile('09823107676', ['name' => 'Third'], $by);

    expect($a->id)->toBe($b->id)->toBe($c->id)
        ->and(Customer::count())->toBe(1)
        ->and($a->fresh()->mobile)->toBe('+919823107676')
        ->and($a->fresh()->name)->toBe('Rahul Kale') // a repeat lookup never overwrites
        ->and($a->branch_id)->toBe($this->branch->id)
        ->and($a->created_by)->toBe($by->id);
});

it('refuses a second customer with the same mobile through the API', function () {
    Sanctum::actingAs(staff('sales'));
    $this->postJson('/api/v1/customers', ['name' => 'Rahul', 'mobile' => '9823107676'])->assertCreated();
    $this->postJson('/api/v1/customers', ['name' => 'Rahul again', 'mobile' => '+91 98231 07676'])
        ->assertUnprocessable()->assertJsonValidationErrors('mobile');
    expect(Customer::count())->toBe(1);
});

it('rejects a bad mobile with a validation error', function (string $mobile) {
    Sanctum::actingAs(staff('sales'));
    $this->postJson('/api/v1/customers', ['name' => 'Test', 'mobile' => $mobile])
        ->assertUnprocessable()->assertJsonValidationErrors('mobile');

    expect(fn () => app(CustomerService::class)->findOrCreateByMobile($mobile, ['name' => 'Test'], staff('sales')))
        ->toThrow(ValidationException::class);
})->with(['too short' => '98231', 'landline' => '020 2691 3456', 'starts with 5' => '5823107676', 'letters' => 'abcdefghij']);

it('needs a name to create a new customer from the counter', function () {
    expect(fn () => $this->service->findOrCreateByMobile('9823107676', [], staff('sales')))
        ->toThrow(ValidationException::class);
});

it('searches by partial number, name and reg no typed any way', function () {
    $rahul = counterCustomer('9823107676', 'Rahul Kale', ['make' => 'Hyundai', 'model' => 'Creta', 'reg_no' => 'mh-12 ab 1234']);
    $sneha = counterCustomer('9922001122', 'Sneha Kulkarni', ['make' => 'Tata', 'model' => 'Nexon', 'reg_no' => 'MH14XY9999']);
    expect(CustomerCar::first()->reg_no)->toBe('MH12AB1234');

    Sanctum::actingAs(staff('sales'));
    $ids = fn (string $q) => collect($this->getJson('/api/v1/customers?q='.urlencode($q))->assertOk()->json('data'))->pluck('id')->all();

    expect($ids('07676'))->toBe([$rahul->id])
        ->and($ids('098231 07676'))->toBe([$rahul->id])
        ->and($ids('kulkarni'))->toBe([$sneha->id])
        ->and($ids('MH 12 ab 1234'))->toBe([$rahul->id])
        ->and($ids('xy9999'))->toBe([$sneha->id])
        ->and($ids('nobody'))->toBe([])
        ->and($ids(''))->toHaveCount(2);
});

it('returns 20 per page with cars and the phone pretty and raw', function () {
    Customer::factory()->count(25)->has(CustomerCar::factory(), 'cars')->create();
    Sanctum::actingAs(staff('finance'));

    $res = $this->getJson('/api/v1/customers')->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.total', 25)
        ->assertJsonStructure(['data' => [['id', 'name', 'mobile', 'mobile_pretty', 'cars' => [['reg_no', 'reg_no_pretty']]]], 'links', 'meta']);

    $first = $res->json('data.0');
    expect($first['mobile'])->toStartWith('+91')
        ->and($first['mobile_pretty'])->toMatch('/^\+91 \d{5} \d{5}$/');
});

it('looks a number up for the bill screen, 404 when unknown', function () {
    counterCustomer('9823107676', 'Rahul Kale');
    Sanctum::actingAs(staff('sales'));

    $this->getJson('/api/v1/customers/lookup?mobile=98231%2007676')->assertOk()
        ->assertJsonPath('data.name', 'Rahul Kale')
        ->assertJsonPath('data.mobile_pretty', '+91 98231 07676');
    $this->getJson('/api/v1/customers/lookup?mobile=9000000001')->assertNotFound();
    $this->getJson('/api/v1/customers/lookup?mobile=123')->assertUnprocessable();
});

it('creates, shows and edits a customer and their cars', function () {
    Sanctum::actingAs(staff('sales'));
    $id = $this->postJson('/api/v1/customers', [
        'name' => 'Amit Jadhav', 'mobile' => '97300 12345', 'area' => 'Hadapsar, Pune',
        'source' => 'whatsapp_ad', 'tags' => 'ppf, vip',
    ])->assertCreated()->assertJsonPath('data.mobile', '+919730012345')->json('data.id');

    $carId = $this->postJson("/api/v1/customers/$id/cars", ['make' => 'Toyota', 'model' => 'Innova Hycross', 'year' => 2024, 'reg_no' => 'mh 12 xz 4321'])
        ->assertCreated()->assertJsonPath('data.reg_no', 'MH12XZ4321')->assertJsonPath('data.reg_no_pretty', 'MH 12 XZ 4321')->json('data.id');

    $this->patchJson("/api/v1/cars/$carId", ['colour' => 'Pearl White'])->assertOk()->assertJsonPath('data.colour', 'Pearl White');
    $this->patchJson("/api/v1/customers/$id", ['whatsapp_opt_in' => false])->assertOk()->assertJsonPath('data.whatsapp_opt_in', false);

    $this->getJson("/api/v1/customers/$id")->assertOk()
        ->assertJsonPath('data.tags', ['ppf', 'vip'])
        ->assertJsonPath('data.cars.0.model', 'Innova Hycross');
    expect(Customer::find($id)->opted_out_at)->not->toBeNull();

    $this->postJson('/api/v1/customers', ['name' => 'X', 'mobile' => '9730099999', 'source' => 'tv'])
        ->assertJsonValidationErrors('source');
});

it('applies the role matrix to the customers API', function () {
    $c = counterCustomer('9823107676');

    Sanctum::actingAs(staff('technician'));
    $this->postJson('/api/v1/customers', ['name' => 'T', 'mobile' => '9822000001'])->assertForbidden();
    $this->getJson('/api/v1/customers')->assertForbidden();

    Sanctum::actingAs(staff('sales'));
    $this->postJson('/api/v1/customers', ['name' => 'S', 'mobile' => '9822000002'])->assertCreated();

    Sanctum::actingAs(staff('finance'));
    $this->getJson('/api/v1/customers')->assertOk();
    $this->getJson("/api/v1/customers/{$c->id}")->assertOk();
    $this->patchJson("/api/v1/customers/{$c->id}", ['name' => 'Changed'])->assertForbidden();
    $this->postJson("/api/v1/customers/{$c->id}/cars", ['make' => 'Tata', 'model' => 'Punch'])->assertForbidden();
    $this->postJson('/api/v1/customers', ['name' => 'F', 'mobile' => '9822000003'])->assertForbidden();

    Sanctum::actingAs(staff('sales', ['is_active' => false]));
    $this->getJson('/api/v1/customers')->assertForbidden();
});

it('needs a login: 401 JSON, not a redirect', function () {
    $this->getJson('/api/v1/customers')->assertUnauthorized();
});

it('accepts the web login cookie from the CRM own domain', function () {
    $this->actingAs(staff('sales'))
        ->withHeader('Referer', config('app.url'))
        ->getJson('/api/v1/customers')->assertOk();
});

it('hides archived customers from search, and brings them back when they return', function () {
    $c = counterCustomer('9823107676', 'Rahul Kale');
    $owner = staff('owner');
    Sanctum::actingAs($owner);

    $this->postJson("/api/v1/customers/{$c->id}/archive")->assertOk()->assertJsonPath('data.archived', true);
    expect(Customer::count())->toBe(1); // never hard-deleted
    $this->getJson('/api/v1/customers?q=Rahul')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/customers/lookup?mobile=9823107676')->assertOk()->assertJsonPath('data.archived', true);

    $again = $this->service->findOrCreateByMobile('9823107676', ['name' => 'Rahul'], $owner);
    expect($again->id)->toBe($c->id)->and($again->archived_at)->toBeNull();
});

it('writes activity log rows for customer and car changes', function () {
    $owner = staff('owner');
    $this->actingAs($owner);
    $c = $this->service->findOrCreateByMobile('9823107676', ['name' => 'Rahul Kale'], $owner);
    $this->service->update($c, ['area' => 'Theur, Pune']);
    $this->service->addCar($c, ['make' => 'Maruti Suzuki', 'model' => 'Swift'], $owner);
    $this->service->archive($c, $owner);

    $this->assertDatabaseHas('activity_log', ['log_name' => 'customers', 'event' => 'created', 'subject_type' => Customer::class, 'subject_id' => $c->id, 'causer_id' => $owner->id]);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'customers', 'event' => 'updated', 'subject_id' => $c->id]);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'customers', 'event' => 'created', 'subject_type' => CustomerCar::class]);
    $this->assertDatabaseHas('activity_log', ['log_name' => 'customers', 'event' => 'archived', 'subject_id' => $c->id]);
});
