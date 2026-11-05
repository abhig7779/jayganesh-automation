<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Support\Dashboard\HomeData;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
});

it('renders a Home for every role without errors', function (string $role, array $see, array $dontSee) {
    Customer::factory()->count(3)->create();
    $res = $this->actingAs(staff($role))->get('/')->assertOk();
    foreach ($see as $text) {
        $res->assertSee($text, false);
    }
    foreach ($dontSee as $text) {
        $res->assertDontSee($text, false);
    }
})->with([
    'owner' => ['owner', ["Today's business", 'Needs attention', 'Send offer', 'New customers', 'Follow-ups today'], []],
    'admin' => ['admin', ["Today's business", 'Needs attention'], ['Shop settings']],
    'finance' => ['finance', ['collections', 'Dues ageing', 'Payables to suppliers', 'Collect payment'], ['New bill</span>']],
    'sales' => ['sales', ['Follow-ups today', 'My today', 'Add customer', 'Collect payment', 'Car in', 'Check price'], ["Today's business", 'Send offer', 'Leads']],
    'technician' => ['technician', ['My job cards'], ['Collect payment', '₹', 'My today']],
    'marketing' => ['marketing', ['Send offer', 'Leads'], ['Collect payment', 'My today']],
]);

it('shows real customer numbers on the owner home', function () {
    Customer::factory()->count(4)->create();
    $data = (new HomeData(staff('owner')))->toArray();
    expect($data['business']['newCustomers'])->toBe(4)
        ->and($data['business']['billing'])->toBeFalse()
        ->and($data['month']['newCustomers'])->toBe(4)
        ->and($data['recentBills'])->toBeNull()
        ->and($data['staffToday'])->toBeNull();
});

it('lists low stock on the owner home', function () {
    Product::create(['name' => 'Gleam Ceramic Spray 250ml', 'price_paise' => 89900, 'reorder_level' => 5, 'branch_id' => $this->branch->id]);
    $this->actingAs(staff('owner'))->get('/')->assertOk()
        ->assertSee('Low stock alerts')->assertSee('Gleam Ceramic Spray 250ml')->assertSee('1 item low on stock');
});

it('links tiles only to routes that exist and marks the rest as soon', function () {
    $tiles = collect((new HomeData(staff('sales')))->tiles())->keyBy('key');
    expect($tiles['customer']['url'])->toBe(route('customers.create'))
        ->and($tiles['new-bill']['url'])->toBeNull()
        ->and($tiles['new-bill']['primary'])->toBeTrue()
        ->and($tiles['customer']['shortcut'])->toBe('F7')
        ->and($tiles->has('offer'))->toBeFalse()
        ->and($tiles->has('leads'))->toBeFalse();
});

it('shows modules that are not built yet as Soon in the menu, and live ones as links', function () {
    $html = $this->actingAs(staff('owner'))->get('/')->assertOk()->getContent();
    // Customers is built here: a real link.
    expect($html)->toContain('href="'.route('customers.index').'"');
    // Every menu item is a real link once its module registers the route, and "Soon" until then.
    foreach (require resource_path('views/partials/nav.php') as $item) {
        if (Route::has($item['route'])) {
            expect($html)->toContain('href="'.route($item['route']).'"');
        } else {
            expect($html)->toMatch('/aria-disabled="true">\\s*<svg[^>]*>.*?<\\/svg>\\s*'.preg_quote($item['label'], '/').'\\s*<span[^>]*>Soon<\\/span>/s');
        }
    }
});

it('hides menu items the role cannot use instead of greying them out', function () {
    $html = $this->actingAs(staff('sales'))->get('/')->getContent();
    expect($html)->not->toContain('>Staff<')->not->toContain('>Settings<')->not->toContain('Leads');
});

it('offers New bill as the raised centre tab only to roles that can bill', function () {
    $this->actingAs(staff('sales'))->get('/')->assertSee('aria-label="New bill"', false);
    $this->actingAs(staff('technician'))->get('/')->assertDontSee('aria-label="New bill"', false);
});

it('compares with the same day last week in plain words', function () {
    $home = new HomeData(staff('owner'));
    [$up, $trendUp] = $home->compareWords(1_000_000, 580_000);
    [$same] = $home->compareWords(5, 5, money: false);
    expect($up)->toStartWith('₹4,200 more than last ')->and($trendUp)->toBe('up')
        ->and($same)->toStartWith('Same as last');
});
