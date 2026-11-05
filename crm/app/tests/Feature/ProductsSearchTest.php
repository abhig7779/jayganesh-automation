<?php

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Services\Stock\StockService;
use App\Support\SearchText;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Branch::create(['name' => 'Main', 'code' => 'JG']);
    $dhc = Brand::create(['name' => 'DHC']);
    $af = Brand::create(['name' => 'Autoform']);
    $this->gleam = Product::create(['name' => 'DHC Gleam 2.0 LED headlight H4', 'brand_id' => $dhc->id, 'sku' => 'DHC-GLM2-H4', 'aliases' => 'gleam, h4 led, headlight bulb', 'price_paise' => 499_900, 'mrp_paise' => 649_900]);
    $this->fog = Product::create(['name' => 'Gleam fog lamp H11', 'brand_id' => $dhc->id, 'sku' => 'DHC-FOG', 'price_paise' => 249_900]);
    $this->mats = Product::create(['name' => '7D floor mats (Creta)', 'aliases' => 'mats, paydan', 'price_paise' => 320_000]);
    $this->cover = Product::create(['name' => 'Seat covers Hyundai Creta', 'brand_id' => $af->id, 'aliases' => 'seat kavar', 'price_paise' => 850_000]);
    $this->old = Product::create(['name' => 'Old gleam stock', 'price_paise' => 100, 'is_active' => false]);
    // sold_count is not fillable (billing keeps it), so set it directly.
    foreach ([[$this->gleam, 50], [$this->fog, 90], [$this->mats, 10], [$this->cover, 5]] as [$p, $sold]) {
        $p->forceFill(['sold_count' => $sold])->save();
    }
});

function names(string $q): array
{
    return Product::query()->active()->search($q)->rankedFor($q)->pluck('name')->all();
}

it('matches the start of every typed word across name, brand, code and aliases', function () {
    expect(names('creta mat'))->toBe(['7D floor mats (Creta)'])
        ->and(names('mats creta'))->toBe(['7D floor mats (Creta)'])
        ->and(names('seat kavar'))->toBe(['Seat covers Hyundai Creta'])
        ->and(names('autoform'))->toBe(['Seat covers Hyundai Creta'])
        ->and(names('paydan'))->toBe(['7D floor mats (Creta)'])
        ->and(names('h4'))->toBe(['DHC Gleam 2.0 LED headlight H4'])
        ->and(names('eam'))->toBe([])        // middle of a word is not a match
        ->and(names('dhc-glm2'))->toBe(['DHC Gleam 2.0 LED headlight H4'])
        ->and(names('covers'))->toBe(['Seat covers Hyundai Creta']);
});

it('puts an exact code first, then names that start with the text, then best sellers', function () {
    // "gleam" is in both; the fog lamp sells more but the headlight name does not start with it.
    expect(names('gleam'))->toBe(['Gleam fog lamp H11', 'DHC Gleam 2.0 LED headlight H4'])
        ->and(names('dhc'))->toBe(['DHC Gleam 2.0 LED headlight H4', 'Gleam fog lamp H11'])
        ->and(names('DHC-FOG'))->toBe(['Gleam fog lamp H11']);
});

it('keeps search words in step when the brand is renamed', function () {
    Brand::where('name', 'Autoform')->first()->update(['name' => 'Autoform India']);
    expect(names('india'))->toBe(['Seat covers Hyundai Creta']);
});

it('highlights the matched word starts and escapes the rest', function () {
    expect((string) SearchText::highlight('Seat covers <Creta>', 'cre sea'))
        ->toBe('<mark class="hl">Sea</mark>t covers &lt;<mark class="hl">Cre</mark>ta&gt;')
        ->and((string) SearchText::highlight('DHC Gleam', ''))->toBe('DHC Gleam');
});

it('searches the stock list live and highlights matches', function () {
    $this->actingAs(staff('sales'))
        ->get(route('products.index', ['q' => 'creta']), ['HX-Request' => 'true', 'HX-Target' => 'product-results'])
        ->assertOk()
        ->assertDontSee('<html', false)
        ->assertSee('<mark class="hl">Creta</mark>', false)
        ->assertDontSee('Gleam fog lamp');
});

it('shows best sellers on the stock check page until 2 letters are typed', function () {
    $this->actingAs(staff('sales'))->get(route('stock.check'))->assertOk()
        ->assertSee('Most sold')->assertSeeInOrder(['Gleam fog lamp H11', 'DHC Gleam 2.0', '7D floor mats'])
        ->assertDontSee('Old gleam stock');

    $this->actingAs(staff('sales'))->get(route('stock.check', ['q' => 'g']), ['HX-Request' => 'true', 'HX-Target' => 'stock-results'])
        ->assertOk()->assertSee('Type one more letter');
});

it('finds items on the stock check page with price and stock', function () {
    app(StockService::class)->move($this->gleam, 2, 'opening');

    $this->actingAs(staff('sales'))
        ->get(route('stock.check', ['q' => 'glea']), ['HX-Request' => 'true', 'HX-Target' => 'stock-results'])
        ->assertOk()
        ->assertSee('2 found')
        ->assertSee('₹4,999')->assertSee('₹6,499')->assertSee('23% off')
        ->assertSee('2 in stock')
        ->assertSee('Out of stock')
        ->assertDontSee('Old gleam stock')
        ->assertSee(route('products.show', $this->gleam));

    $this->actingAs(staff('sales'))->get(route('stock.check', ['q' => 'zzzz']))->assertSee('Nothing matches');
});

it('gives billing a ranked search and a full index with an ETag', function () {
    Sanctum::actingAs(staff('sales'));
    $this->getJson('/api/v1/products?q=gleam')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Gleam fog lamp H11')
        ->assertJsonPath('data.1.aliases', ['gleam', 'h4 led', 'headlight bulb']);

    $res = $this->getJson('/api/v1/products/index')->assertOk()->assertJsonCount(4, 'data');
    $etag = $res->headers->get('ETag');
    $this->getJson('/api/v1/products/index', ['If-None-Match' => $etag])->assertStatus(304);

    Sanctum::actingAs(staff('technician'));
    $this->getJson('/api/v1/products?q=gleam')->assertForbidden();
});
