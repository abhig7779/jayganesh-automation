<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Billing\GstCalculator;
use App\Services\Billing\InvoiceService;
use App\Services\Stock\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;

/** Billing test fixtures. Loaded with require_once by the billing test files. */
function billingSetup($test, bool $gst = true): void
{
    $test->seed(RolesAndPermissionsSeeder::class);
    $test->branch = Branch::create(['name' => 'Main', 'code' => 'JG', 'state_code' => '27']);
    $test->seed(SettingsSeeder::class);
    Setting::put('tax.gstin', $gst ? '27AAJFJ1234K1Z5' : null);
    Setting::put('billing.upi_id', 'jayganesh@upi');
}

function billProduct(array $attrs = [], int $stock = 10): Product
{
    static $n = 0;
    $n++;
    $p = Product::create(array_merge([
        'name' => "Test product $n",
        'sku' => "TP-$n",
        'hsn' => '8512',
        'gst_rate' => 18,
        'price_paise' => 499900,
        'price_includes_gst' => true,
    ], $attrs));
    if ($stock && $p->track_stock) {
        app(StockService::class)->move($p, $stock, 'opening');
    }

    return $p->fresh();
}

function billCustomer(string $mobile = '9823104512', string $name = 'Rahul Pawar'): Customer
{
    $c = Customer::create(['branch_id' => Branch::first()->id, 'name' => $name, 'mobile' => $mobile]);
    CustomerCar::create(['customer_id' => $c->id, 'make' => 'Hyundai', 'model' => 'Creta', 'reg_no' => 'MH12KT4521']);

    return $c->load('cars');
}

/** Make a bill through the service. $items: [[Product, qty], …] */
function makeBill(User $by, array $items, array $extra = [])
{
    return app(InvoiceService::class)->create(array_merge([
        'items' => array_map(fn ($i) => ['product_id' => $i[0]->id, 'qty' => $i[1] ?? 1], $items),
        'payments' => [],
    ], $extra), $by);
}

/** Pay the full total in one mode. */
function payFull(array $items, string $mode = 'cash', int $billDiscount = 0): array
{
    $calc = GstCalculator::fromSettings();
    $total = $calc->calculate(array_map(fn ($i) => [
        'qty' => $i[1] ?? 1, 'unit_price_paise' => $i[0]->price_paise, 'gst_rate' => $i[0]->gst_rate, 'price_includes_gst' => $i[0]->price_includes_gst,
    ], $items), $billDiscount)['total_paise'];

    return [['mode' => $mode, 'amount_paise' => $total]];
}
