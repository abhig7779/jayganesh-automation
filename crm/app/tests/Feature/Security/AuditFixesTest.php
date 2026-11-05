<?php

/*
 * Medium and Low items from crm/AUDIT-2026-11-06.md (M1–M4, M8, M9, L2, L5, L6, L9–L13, L16, L18).
 */

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JobCard;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Quotation;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\NumberSeries;
use App\Services\JobCards\JobCardService;
use App\Services\JobCards\PhotoResizer;
use App\Services\Pdf\InvoicePdf;
use App\Services\Quotations\QuotationService;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../Billing/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    billingSetup($this);
    $this->product = billProduct(['name' => 'DHC Gleam 2.0 LED', 'price_paise' => 499900], 10);
    $this->customer = billCustomer();
});

// ---------------------------------------------------------------- M1 customer history

it('lists only the viewer\'s own bills and quotes on the customer page, with a count of the rest', function () {
    $mine = staff('sales');
    $other = staff('sales');
    $ownBill = makeBill($mine, [[$this->product]], ['customer_id' => $this->customer->id, 'payments' => payFull([[$this->product]])]);
    $otherBill = makeBill($other, [[$this->product]], ['customer_id' => $this->customer->id, 'payments' => payFull([[$this->product]])]);

    $this->actingAs($mine)->get(route('customers.show', $this->customer))->assertOk()
        ->assertSee($ownBill->number)->assertDontSee($otherBill->number)
        ->assertSee('1 more bill or quote by other staff');

    $this->actingAs(staff('owner'))->get(route('customers.show', $this->customer))->assertOk()
        ->assertSee($ownBill->number)->assertSee($otherBill->number)->assertDontSee('by other staff');
});

// ---------------------------------------------------------------- M2 receipt IDOR

it('shows a payment receipt only to whoever took the money, or all-staff roles', function () {
    $taker = staff('sales');
    $bill = makeBill($taker, [[$this->product]], ['customer_id' => $this->customer->id, 'payments' => payFull([[$this->product]])]);
    $payment = Payment::query()->where('invoice_id', $bill->id)->firstOrFail();

    $this->actingAs($taker)->get(route('payments.receipt', $payment))->assertOk();
    $this->actingAs(staff('sales'))->get(route('payments.receipt', $payment))->assertForbidden();
    $this->actingAs(staff('finance'))->get(route('payments.receipt', $payment))->assertOk();
});

// ---------------------------------------------------------------- M3 WhatsApp resend / manual

it('lets counter staff resend only their own bill messages, never offers', function () {
    Bus::fake();
    $sales = staff('sales');
    $own = makeBill($sales, [[$this->product]], ['customer_id' => $this->customer->id, 'payments' => payFull([[$this->product]])]);
    $theirs = makeBill(staff('sales'), [[$this->product]], ['customer_id' => $this->customer->id, 'payments' => payFull([[$this->product]])]);
    $row = fn (array $a) => MessageLog::create(array_merge([
        'branch_id' => $this->branch->id, 'to' => '+919823104512', 'channel' => 'cloud_api', 'direction' => 'out',
        'template' => 'invoice_ebill', 'category' => 'utility', 'body' => ['params' => ['Rahul', 'JG', '₹1', 'Paid']], 'status' => 'failed',
    ], $a));

    $ownLog = $row(['related_type' => $own->getMorphClass(), 'related_id' => $own->id]);
    $theirLog = $row(['related_type' => $theirs->getMorphClass(), 'related_id' => $theirs->id]);
    $offerLog = $row(['template' => 'diwali_offer', 'category' => 'marketing', 'related_type' => $own->getMorphClass(), 'related_id' => $own->id]);

    $this->actingAs($sales)->post(route('whatsapp.resend', $ownLog))->assertRedirect();
    $this->actingAs($sales)->post(route('whatsapp.resend', $theirLog))->assertForbidden();
    $this->actingAs($sales)->post(route('whatsapp.resend', $offerLog))->assertForbidden();
    $this->actingAs(staff('owner'))->post(route('whatsapp.resend', $theirLog))->assertRedirect();
});

// ---------------------------------------------------------------- M4 / L2 idempotency

it('records one payment when Collect is submitted twice with the same key', function () {
    $bill = makeBill(staff('owner'), [[$this->product]], ['customer_id' => $this->customer->id]);
    $finance = staff('finance');
    $post = fn () => $this->actingAs($finance)->post(route('payments.collect.store'), [
        'customer_id' => $this->customer->id, 'amount' => '2000', 'mode' => 'cash',
        'invoices' => [$bill->id], 'client_key' => 'b3c1f6a2-1111-4c4c-9999-0123456789ab',
    ]);

    $first = $post()->assertRedirect()->headers->get('Location');
    $second = $post()->assertRedirect()->headers->get('Location');

    expect($second)->toBe($first)
        ->and(Payment::query()->where('client_key', 'b3c1f6a2-1111-4c4c-9999-0123456789ab')->count())->toBe(1)
        ->and($bill->fresh()->due_paise)->toBe(299900);
});

it('puts a fresh key and a busy guard on the Collect form', function () {
    makeBill(staff('owner'), [[$this->product]], ['customer_id' => $this->customer->id]);
    $this->actingAs(staff('finance'))->get(route('payments.collect', ['customer' => $this->customer->id]))
        ->assertOk()->assertSee('name="client_key"', false)->assertSee(':disabled="busy', false);
});

it('saves one expense for a double-tapped Save', function () {
    $cat = ExpenseCategory::query()->firstOrCreate(['name' => 'Tea & snacks'], ['is_active' => true]);
    $u = staff('finance');
    $data = ['date' => now('Asia/Kolkata')->toDateString(), 'expense_category_id' => $cat->id, 'amount' => '300', 'mode' => 'cash', 'client_key' => 'exp-key-1'];

    $this->actingAs($u)->post(route('finance.expenses.store'), $data)->assertRedirect();
    $this->actingAs($u)->post(route('finance.expenses.store'), $data)->assertRedirect();

    expect(Expense::query()->count())->toBe(1);
});

it('opens one job card and one quotation per client key', function () {
    $by = staff('owner');
    $car = CustomerCar::query()->where('customer_id', $this->customer->id)->first();
    $card = ['customer_id' => $this->customer->id, 'customer_car_id' => $car->id, 'client_key' => 'jc-1',
        'items' => [['description' => 'Ceramic coating', 'qty' => 1, 'price_paise' => 100000]]];
    $a = app(JobCardService::class)->create($card, $by);
    $b = app(JobCardService::class)->create($card, $by);
    expect($b->id)->toBe($a->id)->and(JobCard::query()->count())->toBe(1);

    $quote = ['customer_id' => $this->customer->id, 'client_key' => 'q-1', 'items' => [['product_id' => $this->product->id, 'qty' => 1]]];
    $q1 = app(QuotationService::class)->create($quote, $by);
    $q2 = app(QuotationService::class)->create($quote, $by);
    expect($q2->id)->toBe($q1->id)->and(Quotation::query()->count())->toBe(1);
});

// ---------------------------------------------------------------- M8 unit price

it('refuses a changed unit price from Sales but accepts it from the Owner', function () {
    $line = ['customer_id' => $this->customer->id, 'items' => [['product_id' => $this->product->id, 'qty' => 1, 'unit_price_paise' => 520000]], 'payments' => []];

    expect(fn () => app(InvoiceService::class)->create($line, staff('sales')))
        ->toThrow(ValidationException::class, 'needs the Owner');

    $bill = app(InvoiceService::class)->create($line, staff('owner'));
    expect($bill->items->first()->unit_price_paise)->toBe(520000);
});

it('lets Sales bill the price on their own quotation', function () {
    $owner = staff('owner');
    $sales = staff('sales');
    $q = app(QuotationService::class)->create(['customer_id' => $this->customer->id,
        'items' => [['product_id' => $this->product->id, 'qty' => 1, 'unit_price_paise' => 450000]]], $owner);
    $q->forceFill(['assigned_to' => $sales->id])->save();

    $bill = makeBill($sales, [], ['quotation_id' => $q->id, 'customer_id' => $this->customer->id,
        'items' => [['product_id' => $this->product->id, 'qty' => 1, 'unit_price_paise' => 450000]],
        'payments' => [['mode' => 'cash', 'amount_paise' => 450000]]]);
    expect($bill->items->first()->unit_price_paise)->toBe(450000);
});

// ---------------------------------------------------------------- M9 IGST / B2B

it('accepts a buyer GSTIN and bills IGST for another state', function () {
    $r = $this->actingAs(staff('owner'))->postJson(route('bills.store'), [
        'customer_id' => $this->customer->id,
        'customer_gstin' => '24aaacb1234c1z5',
        'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        'payments' => [],
    ])->assertCreated();

    $bill = Invoice::find($r->json('id'));
    expect($bill->customer_gstin)->toBe('24AAACB1234C1Z5')
        ->and($bill->place_of_supply)->toBe('24')
        ->and($bill->igst_paise)->toBeGreaterThan(0)
        ->and($bill->cgst_paise)->toBe(0);
});

it('rejects a malformed GSTIN or place of supply', function () {
    $this->actingAs(staff('owner'))->postJson(route('bills.store'), [
        'customer_gstin' => 'NOT-A-GSTIN', 'place_of_supply' => 'MH',
        'items' => [['product_id' => $this->product->id, 'qty' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors(['customer_gstin', 'place_of_supply']);
});

// ---------------------------------------------------------------- L5 / L6

it('numbers a bill made just after midnight IST on 1 April in the new year', function () {
    // 31 Mar 2027 19:00 UTC is 1 Apr 2027 00:30 IST.
    expect(NumberSeries::financialYear(CarbonImmutable::parse('2027-03-31 19:00', 'UTC')))->toBe('2728');
});

it('accepts the 40% GST slab on a product', function () {
    $this->actingAs(staff('owner'))->post(route('products.store'), [
        'name' => 'Luxury item', 'type' => 'product', 'gst_rate' => '40', 'price' => '1000', 'unit' => 'pcs',
    ])->assertSessionDoesntHaveErrors('gst_rate');
});

// ---------------------------------------------------------------- L9 / L10 / L11

it('keeps dompdf away from the project root and .env', function () {
    expect(InvoicePdf::options()['chroot'])->not->toContain(base_path());
});

it('registers no public /storage route for the private disk', function () {
    expect(Route::has('storage.local'))->toBeFalse()->and(config('filesystems.disks.local.serve'))->toBeFalse();
});

it('shuts a switched-off login out of the API and expires tokens', function () {
    $u = staff('sales');
    $u->forceFill(['is_active' => false])->save();
    Sanctum::actingAs($u);

    $this->getJson(route('api.v1.products.index'))->assertForbidden();
    expect(config('sanctum.expiration'))->toBe(60 * 24 * 30);
});

// ---------------------------------------------------------------- L12 / L13 / L16 / L18

it('uses the all-staff permission, not role names, for seeing every quote', function () {
    $u = staff('marketing');
    expect(Quotation::seesAll($u))->toBeFalse();
    $u->givePermissionTo('reports.all_staff');
    expect(Quotation::seesAll($u->fresh()))->toBeTrue();
});

it('defines each role once', function () {
    $src = file_get_contents(app_path('Support/Permissions.php'));
    expect(substr_count($src, "'marketing' => ["))->toBe(1)
        ->and(substr_count($src, "'marketing' => 'Marketing'"))->toBe(1)
        ->and(Permissions::ROLES['marketing'])->not->toContain('finance.view');
});

it('does not mass-assign money or status on billing models', function (string $model) {
    expect(fn () => (new $model)->fill(['status' => 'paid', 'paid_paise' => 1]))->toThrow(MassAssignmentException::class);
})->with([Invoice::class, Payment::class, InvoiceItem::class, PaymentAllocation::class, CreditNote::class, CreditNoteItem::class]);

it('reads only real photo formats', function () {
    expect(PhotoResizer::looksLikePhoto("\xFF\xD8\xFF\xE0".str_repeat("\0", 12)))->toBeTrue()
        ->and(PhotoResizer::looksLikePhoto("\x89PNG\r\n\x1a\n".str_repeat("\0", 8)))->toBeTrue()
        ->and(PhotoResizer::looksLikePhoto("\0\0\0\x18ftypheic\0\0\0\0"))->toBeTrue()
        ->and(PhotoResizer::looksLikePhoto('%PDF-1.7 ......'))->toBeFalse()
        ->and(PhotoResizer::looksLikePhoto('<svg xmlns="ht'))->toBeFalse();
});
