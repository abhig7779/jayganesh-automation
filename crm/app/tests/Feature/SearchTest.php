<?php

use App\Models\Branch;
use App\Models\Product;
use App\Services\Customers\CustomerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $svc = app(CustomerService::class);
    $this->rahul = $svc->findOrCreateByMobile('9823104512', ['name' => 'Rahul Pawar'], staff('sales'));
    $svc->addCar($this->rahul, ['make' => 'Hyundai', 'model' => 'Creta', 'reg_no' => 'MH12KT4521'], staff('sales'));
    $this->sneha = $svc->findOrCreateByMobile('9823107788', ['name' => 'Sneha Kulkarni'], staff('sales'));
    Product::create(['name' => 'DHC Gleam 2.0 LED headlight', 'price_paise' => 499900, 'branch_id' => $this->branch->id]);
});

it('returns grouped results as an htmx partial', function () {
    $html = $this->actingAs(staff('sales'))->withHeader('HX-Request', 'true')
        ->get(route('search', ['q' => 'rahul']))->assertOk()->getContent();

    expect($html)->toContain('Customers')->toContain('Rahul Pawar')->toContain('data-search-result')
        ->not->toContain('Sneha')->not->toContain('<html');
});

it('finds customers by partial mobile digits and cars by plate typed any way', function () {
    $this->actingAs(staff('sales'))->withHeader('HX-Request', 'true')->get(route('search', ['q' => '07788']))
        ->assertSee('Sneha Kulkarni')->assertDontSee('Rahul Pawar');

    $this->actingAs(staff('sales'))->withHeader('HX-Request', 'true')->get(route('search', ['q' => 'mh 12 kt']))
        ->assertSee('Cars')->assertSee('MH 12 KT 4521')->assertSee('Rahul Pawar');
});

it('finds products for roles that can see stock, not for others', function () {
    $this->actingAs(staff('sales'))->withHeader('HX-Request', 'true')->get(route('search', ['q' => 'gleam']))
        ->assertSee('Products')->assertSee('DHC Gleam 2.0 LED headlight')->assertSee('₹4,999');

    $this->actingAs(staff('marketing'))->withHeader('HX-Request', 'true')->get(route('search', ['q' => 'gleam']))
        ->assertDontSee('DHC Gleam');
});

it('jumps straight to the customer when all 10 digits match', function () {
    $this->actingAs(staff('sales'))->get(route('search', ['q' => '+91 98231 04512']))
        ->assertRedirect(route('customers.show', $this->rahul));
});

it('renders the full page with an empty state and an add-customer link', function () {
    $this->actingAs(staff('sales'))->get(route('search', ['q' => '9000000001']))->assertOk()
        ->assertSee('Nothing found')->assertSee(route('customers.create', ['mobile' => '9000000001']), false);

    $this->actingAs(staff('sales'))->get(route('search'))->assertOk()->assertSee('Type a mobile number');
});

it('hides archived customers and shows nothing to a technician', function () {
    app(CustomerService::class)->archive($this->sneha, staff('owner'));
    $this->actingAs(staff('sales'))->withHeader('HX-Request', 'true')->get(route('search', ['q' => 'sneha']))->assertDontSee('Sneha');
    $this->actingAs(staff('technician'))->withHeader('HX-Request', 'true')->get(route('search', ['q' => 'rahul']))->assertDontSee('Rahul');
});
