<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMove;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Purchases\PurchaseService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->supplier = Supplier::create(['branch_id' => $this->branch->id, 'name' => 'Pune Auto Distributors', 'mobile' => '+919822012345']);
    $this->gleam = Product::create(['branch_id' => $this->branch->id, 'name' => 'DHC Gleam 2.0 H4', 'sku' => 'GLM-H4', 'price_paise' => 499900, 'cost_paise' => 300000, 'gst_rate' => 18]);
    $this->mat = Product::create(['branch_id' => $this->branch->id, 'name' => '7D floor mats Creta', 'sku' => '7D-CRT', 'price_paise' => 450000, 'cost_paise' => 250000, 'gst_rate' => 28]);
});

function purchasePayload(array $over = []): array
{
    return array_merge([
        'supplier_id' => test()->supplier->id,
        'bill_no' => 'PAD/1184',
        'bill_date' => now('Asia/Kolkata')->toDateString(),
        'items' => [
            ['product_id' => test()->gleam->id, 'qty' => 10, 'cost' => '3,150', 'gst_rate' => 18],
            ['product_id' => test()->mat->id, 'qty' => 4, 'cost' => '2400.50', 'gst_rate' => 28],
        ],
    ], $over);
}

it('adds stock, writes purchase moves and updates the cost price', function () {
    Storage::fake('local');
    $finance = staff('finance');

    $this->actingAs($finance)->post(route('purchases.store'), purchasePayload([
        'photo' => UploadedFile::fake()->image('bill.jpg'), 'paid' => '10,000', 'paid_mode' => 'upi',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $p = Purchase::query()->sole();
    // 10 × 3,150 = 31,500 + 18% 5,670; 4 × 2,400.50 = 9,602 + 28% 2,688.56
    expect($p->subtotal_paise)->toBe(3150000 + 960200)
        ->and($p->gst_paise)->toBe(567000 + 268856)
        ->and($p->total_paise)->toBe(3150000 + 960200 + 567000 + 268856)
        ->and($p->paid_paise)->toBe(1000000)
        ->and($p->photo_path)->not->toBeNull()
        ->and($p->items)->toHaveCount(2);
    Storage::disk('local')->assertExists($p->photo_path);

    expect($this->gleam->refresh()->on_hand)->toBe(10)
        ->and($this->gleam->cost_paise)->toBe(315000)
        ->and($this->mat->refresh()->on_hand)->toBe(4)
        ->and($this->mat->cost_paise)->toBe(240050);

    $move = StockMove::query()->where('product_id', $this->gleam->id)->sole();
    expect($move->type)->toBe('purchase')->and($move->qty)->toBe(10)->and($move->unit_cost_paise)->toBe(315000)
        ->and($move->reference_type)->toBe((new Purchase)->getMorphClass())->and($move->reference_id)->toBe($p->id);

    expect(SupplierPayment::query()->sole()->only(['purchase_id', 'amount_paise', 'mode']))
        ->toBe(['purchase_id' => $p->id, 'amount_paise' => 1000000, 'mode' => 'upi']);

    $this->actingAs($finance)->get(route('purchases.show', $p))->assertOk()->assertSee('PB-0001')->assertSee('₹49,460.56')->assertSee('Pune Auto Distributors');
    $this->actingAs($finance)->get(route('purchases.photo', $p))->assertOk();
});

it('creates a new supplier from the purchase screen and rejects a bill entered twice', function () {
    $admin = staff('admin');
    $this->actingAs($admin)->post(route('purchases.store'), purchasePayload(['supplier_id' => null, 'new_supplier' => 'Shree Seat Covers', 'bill_no' => 'SSC-1']))
        ->assertSessionHasNoErrors();
    $shree = Supplier::query()->where('name', 'Shree Seat Covers')->sole();
    expect(Purchase::query()->sole()->supplier_id)->toBe($shree->id);

    $this->actingAs($admin)->post(route('purchases.store'), purchasePayload(['supplier_id' => $shree->id, 'bill_no' => 'SSC-1']))
        ->assertSessionHasErrors('bill_no');
    expect(Purchase::query()->count())->toBe(1);
});

it('validates lines and refuses paying more than the bill', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->post(route('purchases.store'), purchasePayload(['items' => []]))->assertSessionHasErrors('items');
    $this->actingAs($owner)->post(route('purchases.store'), purchasePayload(['items' => [['product_id' => $this->gleam->id, 'qty' => 1, 'cost' => 'abc', 'gst_rate' => 18]]]))
        ->assertSessionHasErrors('items.0.cost');
    $this->actingAs($owner)->post(route('purchases.store'), purchasePayload(['paid' => '9,99,999']))->assertSessionHasErrors('paid');
    expect(Purchase::query()->count())->toBe(0)->and($this->gleam->refresh()->on_hand)->toBe(0);
});

it('cancels a purchase: stock goes back out and the bill stops counting', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->post(route('purchases.store'), purchasePayload(['paid' => '5000']));
    $p = Purchase::query()->sole();

    $this->actingAs($owner)->post(route('purchases.cancel', $p), ['reason' => 'other'])->assertSessionHasErrors('reason_text');
    $this->actingAs($owner)->post(route('purchases.cancel', $p), ['reason' => 'duplicate'])->assertRedirect(route('purchases.show', $p));

    $p->refresh();
    expect($p->isCancelled())->toBeTrue()
        ->and($p->status)->toBe('cancelled')
        ->and($p->cancel_reason)->toBe('Entered twice')
        ->and($this->gleam->refresh()->on_hand)->toBe(0)
        ->and($this->mat->refresh()->on_hand)->toBe(0)
        ->and(StockMove::query()->where('product_id', $this->gleam->id)->pluck('qty')->all())->toBe([10, -10]);

    // The ₹5,000 already paid stays, so the supplier now holds an advance.
    expect($this->supplier->refresh()->balancePaise())->toBe(-500000);

    // Cancelling twice does nothing more.
    $this->actingAs($owner)->post(route('purchases.cancel', $p), ['reason' => 'duplicate']);
    expect(StockMove::query()->where('product_id', $this->gleam->id)->count())->toBe(2);
});

it('lets only owner and admin cancel a purchase', function () {
    $finance = staff('finance');
    $this->actingAs($finance)->post(route('purchases.store'), purchasePayload());
    $p = Purchase::query()->sole();

    $this->actingAs($finance)->get(route('purchases.show', $p))->assertOk()->assertDontSee('Cancel this purchase');
    $this->actingAs($finance)->post(route('purchases.cancel', $p), ['reason' => 'duplicate'])->assertForbidden();
    expect($p->refresh()->isCancelled())->toBeFalse();

    $this->actingAs(staff('admin'))->get(route('purchases.show', $p))->assertSee('Cancel this purchase');
});

it('keeps the supplier balance: opening + bills − payments, oldest bills paid first', function () {
    $this->supplier->update(['opening_balance_paise' => 100000]); // ₹1,000 owed from before
    $svc = app(PurchaseService::class);
    $by = staff('finance');
    $line = fn ($qty) => [['product_id' => $this->gleam->id, 'qty' => $qty, 'unit_cost_paise' => 100000, 'gst_rate' => 0]];

    $old = $svc->create(['supplier_id' => $this->supplier->id, 'bill_no' => 'A', 'bill_date' => '2026-10-01', 'items' => $line(5)], $by);   // ₹5,000
    $new = $svc->create(['supplier_id' => $this->supplier->id, 'bill_no' => 'B', 'bill_date' => '2026-10-20', 'items' => $line(3)], $by);   // ₹3,000
    expect($this->supplier->balancePaise())->toBe(900000);

    // ₹4,000 paid without picking a bill: ₹1,000 clears the opening, ₹3,000 goes to the oldest bill.
    $this->actingAs($by)->post(route('suppliers.payments.store', $this->supplier), ['amount' => '4,000', 'date' => '2026-10-25', 'mode' => 'cash'])
        ->assertRedirect(route('suppliers.show', $this->supplier))->assertSessionHasNoErrors();
    expect($this->supplier->refresh()->balancePaise())->toBe(500000)
        ->and($old->refresh()->paid_paise)->toBe(300000)
        ->and($new->refresh()->paid_paise)->toBe(0);

    // ₹3,000 against bill B pays B first.
    $this->actingAs($by)->post(route('suppliers.payments.store', $this->supplier), ['amount' => '3000', 'date' => '2026-10-26', 'mode' => 'upi', 'purchase_id' => $new->id]);
    expect($new->refresh()->payState())->toBe('paid')->and($old->refresh()->duePaise())->toBe(200000)
        ->and($this->supplier->refresh()->balancePaise())->toBe(200000);

    // Ledger and list show it.
    $this->actingAs($by)->get(route('suppliers.show', $this->supplier))->assertOk()->assertSee('We owe')->assertSee('₹2,000')->assertSee('Bill A');
    $this->actingAs($by)->get(route('suppliers.index'))->assertOk()->assertSee('₹2,000 to pay');
    $this->actingAs($by)->get(route('purchases.index', ['state' => 'due']))->assertOk()->assertSee('PB-0001')->assertDontSee('PB-0002');

    // Zero and bad amounts are refused.
    $this->actingAs($by)->post(route('suppliers.payments.store', $this->supplier), ['amount' => '0', 'date' => '2026-10-26', 'mode' => 'cash'])->assertSessionHasErrors('amount');
});

it('adds and edits suppliers with a clean mobile and GSTIN', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->post(route('suppliers.store'), ['name' => 'Garware Films', 'mobile' => '097630 11122', 'gstin' => '27aaacg4321p1zx', 'opening_balance' => '12,500'])
        ->assertSessionHasNoErrors();
    $s = Supplier::query()->where('name', 'Garware Films')->sole();
    expect($s->mobile)->toBe('+919763011122')->and($s->gstin)->toBe('27AAACG4321P1ZX')->and($s->opening_balance_paise)->toBe(1250000);

    $this->actingAs($owner)->post(route('suppliers.store'), ['name' => 'Bad', 'gstin' => '123'])->assertSessionHasErrors('gstin');
    $this->actingAs($owner)->put(route('suppliers.update', $s), ['name' => 'Garware Surface Films', 'opening_balance' => '-1500', 'is_active' => '1'])->assertSessionHasNoErrors();
    expect($s->refresh()->name)->toBe('Garware Surface Films')->and($s->balancePaise())->toBe(-150000);
});

it('blocks sales and technician from purchases and suppliers', function (string $role) {
    $u = staff($role);
    $this->actingAs($u)->get(route('purchases.index'))->assertForbidden();
    $this->actingAs($u)->get(route('purchases.create'))->assertForbidden();
    $this->actingAs($u)->post(route('purchases.store'), purchasePayload())->assertForbidden();
    $this->actingAs($u)->get(route('purchases.product-index'))->assertForbidden();
    $this->actingAs($u)->get(route('suppliers.index'))->assertForbidden();
    $this->actingAs($u)->post(route('suppliers.payments.store', $this->supplier), ['amount' => '100', 'date' => '2026-11-01', 'mode' => 'cash'])->assertForbidden();
    $this->actingAs($u)->get(route('dashboard'))->assertDontSee(route('purchases.index'));
    expect(Purchase::query()->count())->toBe(0);
})->with(['sales', 'technician']);

it('shows the screens and the product list to finance', function () {
    $finance = staff('finance');
    $this->actingAs($finance)->get(route('purchases.index'))->assertOk()->assertSee('No stock in yet');
    $this->actingAs($finance)->get(route('purchases.create'))->assertOk()->assertSee('Save &amp; add to stock', false);
    $this->actingAs($finance)->get(route('suppliers.create'))->assertOk();
    $this->actingAs($finance)->getJson(route('purchases.product-index'))->assertOk()
        ->assertJsonFragment(['sku' => 'GLM-H4', 'cost_paise' => 300000]);
});
