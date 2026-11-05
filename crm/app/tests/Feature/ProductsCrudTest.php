<?php

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\StockMove;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->gleam = Product::create([
        'name' => 'DHC Gleam 2.0 LED headlight H4',
        'brand_id' => Brand::create(['name' => 'DHC'])->id,
        'sku' => 'DHC-GLM2-H4',
        'aliases' => 'gleam, h4 led',
        'price_paise' => 499_900,
        'mrp_paise' => 649_900,
        'cost_paise' => 315_000,
        'hsn' => '85395200',
        'reorder_level' => 3,
    ]);
});

function productForm(array $over = []): array
{
    return array_merge([
        'name' => '7D floor mats Hyundai Creta',
        'aliases' => 'mats, 7D mat, paydan',
        'brand_name' => '7D',
        'category_name' => 'Mats',
        'type' => 'product',
        'price' => '3,200',
        'mrp' => '3800',
        'gst_rate' => '18',
        'price_includes_gst' => '1',
        'hsn' => '8708 99 00',
        'opening_stock' => '6',
        'reorder_level' => '2',
        'compatible_cars' => 'Hyundai Creta, Kia Seltos',
    ], $over);
}

it('shows the stock list and detail to every role except the technician', function (string $role, int $status) {
    $this->actingAs(staff($role))->get(route('products.index'))->assertStatus($status);
    $this->actingAs(staff($role))->get(route('products.show', $this->gleam))->assertStatus($status);
    $this->actingAs(staff($role))->get(route('stock.check'))->assertStatus($status);
})->with([['owner', 200], ['admin', 200], ['finance', 200], ['sales', 200], ['technician', 403]]);

it('never shows cost or margin to sales, in pages or the API', function () {
    $sales = staff('sales');
    $this->actingAs($sales)->get(route('products.index'))->assertOk()
        ->assertSee('₹4,999')->assertDontSee('₹3,150')->assertDontSee('Margin');
    $this->actingAs($sales)->get(route('products.show', $this->gleam))->assertOk()
        ->assertSee('₹4,999')->assertDontSee('₹3,150')->assertDontSee('data-margins', false);
    $this->actingAs($sales)->get(route('products.index', ['sort' => 'cost']))->assertOk(); // silently ignored

    Sanctum::actingAs($sales);
    $json = $this->getJson('/api/v1/products?q=gleam')->assertOk()->json('data.0');
    expect($json)->not->toHaveKey('cost_paise')->not->toHaveKey('margin_paise')
        ->and($json['price_paise'])->toBe(499_900)
        ->and($json['discount_percent'])->toBe(23);
});

it('shows cost and margin to the owner', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->get(route('products.index'))->assertOk()->assertSee('₹3,150')->assertSee('Margin');
    $this->actingAs($owner)->get(route('products.show', $this->gleam))->assertOk()->assertSee('₹1,849');

    Sanctum::actingAs($owner);
    expect($this->getJson('/api/v1/products?q=gleam')->json('data.0.cost_paise'))->toBe(315_000);
});

it('creates a product with rupees stored as paise and an opening stock move', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->post(route('products.store'), productForm())->assertRedirect();

    $p = Product::where('name', '7D floor mats Hyundai Creta')->firstOrFail();
    expect($p->price_paise)->toBe(320_000)
        ->and($p->mrp_paise)->toBe(380_000)
        ->and($p->hsn)->toBe('87089900')
        ->and($p->brand->name)->toBe('7D')
        ->and($p->category->name)->toBe('Mats')
        ->and($p->aliases)->toBe('mats, 7d mat, paydan')
        ->and($p->compatible_cars)->toBe(['Hyundai Creta', 'Kia Seltos'])
        ->and($p->on_hand)->toBe(6)
        ->and($p->created_by)->toBe($owner->id)
        ->and(StockMove::where('product_id', $p->id)->where('type', 'opening')->value('qty'))->toBe(6);
});

it('reuses an existing brand typed in another case', function () {
    $this->actingAs(staff('owner'))->post(route('products.store'), productForm(['name' => 'DHC fog', 'brand_name' => 'dhc']))->assertRedirect();
    expect(Brand::count())->toBe(1)->and(Product::where('name', 'DHC fog')->first()->brand->name)->toBe('DHC');
});

it('creates a service without stock', function () {
    $this->actingAs(staff('owner'))->post(route('products.store'), productForm(['name' => 'Fitting charge', 'type' => 'service', 'price' => '500', 'mrp' => '', 'opening_stock' => '5', 'hsn' => '998714']))->assertRedirect();
    $fit = Product::where('name', 'Fitting charge')->first();
    expect($fit->track_stock)->toBeFalse()->and($fit->on_hand)->toBe(0)->and(StockMove::where('product_id', $fit->id)->exists())->toBeFalse();
});

it('validates the form in plain words', function () {
    $this->actingAs(staff('owner'))
        ->post(route('products.store'), productForm(['price' => '', 'gst_rate' => '15', 'hsn' => '12', 'sku' => 'dhc-glm2-h4']))
        ->assertSessionHasErrors(['price', 'gst_rate', 'hsn', 'sku']);
    $this->actingAs(staff('owner'))->post(route('products.store'), productForm(['price' => '4000', 'mrp' => '3800']))
        ->assertSessionHasErrors(['price' => "Our price can't be more than the normal price (MRP)."]);
});

it('lets only inventory managers add or edit products', function (string $role, bool $allowed) {
    $u = staff($role);
    $this->actingAs($u)->get(route('products.create'))->assertStatus($allowed ? 200 : 403);
    $this->actingAs($u)->post(route('products.store'), productForm())->assertStatus($allowed ? 302 : 403);
    $this->actingAs($u)->get(route('products.edit', $this->gleam))->assertStatus($allowed ? 200 : 403);
})->with([['owner', true], ['admin', true], ['sales', false], ['finance', false]]);

it('does not change prices without prices.manage', function () {
    $admin = staff('admin'); // inventory.manage, no prices.manage
    $this->actingAs($admin)->get(route('products.edit', $this->gleam))->assertOk()->assertSee('Only the owner can change prices');

    $this->actingAs($admin)->put(route('products.update', $this->gleam), productForm([
        'name' => 'DHC Gleam 2.0 H4 (renamed)', 'price' => '1', 'mrp' => '2', 'cost' => '1', 'gst_rate' => '18', 'is_active' => '1',
    ]))->assertRedirect();

    $p = $this->gleam->fresh();
    expect($p->name)->toBe('DHC Gleam 2.0 H4 (renamed)')
        ->and($p->price_paise)->toBe(499_900)
        ->and($p->mrp_paise)->toBe(649_900)
        ->and($p->cost_paise)->toBe(315_000)
        ->and($p->on_hand)->toBe(0); // opening_stock is ignored on edit
});

it('lets the owner change prices and cost', function () {
    $this->actingAs(staff('owner'))->put(route('products.update', $this->gleam), productForm([
        'name' => $this->gleam->name, 'price' => '4,799', 'mrp' => '6499', 'cost' => '3000', 'is_active' => '1',
    ]))->assertRedirect(route('products.show', $this->gleam));

    expect($this->gleam->fresh())
        ->price_paise->toBe(479_900)
        ->cost_paise->toBe(300_000);
});

it('adjusts stock from the sheet with a reason', function () {
    $admin = staff('admin');
    $this->actingAs($admin)->post(route('products.adjust', $this->gleam), ['new_count' => 5, 'reason' => 'count'])
        ->assertRedirect(route('products.show', $this->gleam))->assertSessionHas('status');
    $this->actingAs($admin)->post(route('products.adjust', $this->gleam), ['new_count' => 4, 'reason' => 'damaged', 'note' => 'Box crushed'])->assertRedirect();
    $this->actingAs($admin)->post(route('products.adjust', $this->gleam), ['new_count' => 3, 'reason' => 'other'])->assertSessionHasErrors('note');
    $this->actingAs(staff('sales'))->post(route('products.adjust', $this->gleam), ['new_count' => 0, 'reason' => 'count'])->assertForbidden();

    expect($this->gleam->fresh()->on_hand)->toBe(4)
        ->and(StockMove::latest('id')->value('note'))->toBe('Damaged: Box crushed');
});

it('works out the discount from the normal price', function () {
    expect($this->gleam->discountPaise())->toBe(150_000)
        ->and($this->gleam->discountPercent())->toBe(23);

    $noMrp = new Product(['price_paise' => 50_000]);
    $sameAsMrp = new Product(['price_paise' => 50_000, 'mrp_paise' => 50_000]);
    $tenOff = new Product(['price_paise' => 90_000, 'mrp_paise' => 100_000]);
    expect($noMrp->discountPaise())->toBe(0)->and($noMrp->discountPercent())->toBe(0)
        ->and($sameAsMrp->discountPercent())->toBe(0)
        ->and($tenOff->discountPercent())->toBe(10);
});

it('shows the normal price struck through with the % off on the list and detail', function () {
    $this->actingAs(staff('sales'))->get(route('products.index'))
        ->assertSee('₹6,499')->assertSee('23% off');
    $this->actingAs(staff('sales'))->get(route('products.show', $this->gleam))
        ->assertSee('You save ₹1,500')->assertSee('wa.me', false);
});

it('returns the detail as a panel for htmx', function () {
    $this->actingAs(staff('sales'))
        ->get(route('products.show', $this->gleam), ['HX-Request' => 'true'])
        ->assertOk()->assertSee('data-product-detail', false)->assertDontSee('<html', false);
});

it('filters low and out of stock and services', function () {
    $mats = Product::create(['name' => 'Floor mats', 'price_paise' => 100_000, 'reorder_level' => 1, 'on_hand' => 0]);
    app(StockService::class)->move($mats, 9, 'opening');
    Product::create(['name' => 'Fitting charge', 'type' => 'service', 'price_paise' => 50_000]);
    $u = staff('sales');

    $this->actingAs($u)->get(route('products.index', ['stock' => 'out']))->assertSee('DHC Gleam')->assertDontSee('Floor mats');
    $this->actingAs($u)->get(route('products.index', ['stock' => 'in']))->assertSee('Floor mats')->assertDontSee('DHC Gleam');
    $this->actingAs($u)->get(route('products.index', ['type' => 'service']))->assertSee('Fitting charge')->assertDontSee('Floor mats');
    $this->actingAs($u)->get(route('products.index', ['sort' => 'price', 'dir' => 'asc']))->assertSeeInOrder(['Fitting charge', 'Floor mats', 'DHC Gleam']);
});
