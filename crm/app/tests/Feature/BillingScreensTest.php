<?php

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\Billing\BillShare;
use App\Services\Pdf\InvoicePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

require_once __DIR__.'/Billing/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    billingSetup($this);
    $this->product = billProduct(['name' => 'DHC Gleam 2.0 LED', 'price_paise' => 499900, 'mrp_paise' => 650000, 'cost_paise' => 300000], 10);
    $this->customer = billCustomer();
    $this->bill = makeBill(staff('sales'), [[$this->product]], [
        'customer_id' => $this->customer->id,
        'payments' => payFull([[$this->product]]),
    ]);
});

// ------------------------------------------------------------------ who can open what

it('lets each role reach only the billing pages it should', function (string $role, array $expect) {
    $u = staff($role);
    $bill = $this->bill;
    $routes = [
        'create' => fn () => $this->actingAs($u)->get(route('bills.create')),
        'index' => fn () => $this->actingAs($u)->get(route('bills.index')),
        'show' => fn () => $this->actingAs($u)->get(route('bills.show', $bill)),
        'done' => fn () => $this->actingAs($u)->get(route('bills.done', $bill)),
        'print' => fn () => $this->actingAs($u)->get(route('bills.print', [$bill, 'format' => 'thermal', 'preview' => 1])),
        'pdf' => fn () => $this->actingAs($u)->get(route('bills.pdf', $bill)),
        'status' => fn () => $this->actingAs($u)->getJson(route('bills.status', $bill)),
        'products' => fn () => $this->actingAs($u)->getJson(route('bills.product-index')),
        'context' => fn () => $this->actingAs($u)->getJson(route('bills.customer-context', ['mobile' => '9823104512'])),
        'collect' => fn () => $this->actingAs($u)->get(route('payments.collect')),
        'draft' => fn () => $this->actingAs($u)->getJson(route('bills.draft')),
        'qr' => fn () => $this->actingAs($u)->get(route('bills.upi-qr', ['amount_paise' => 10000])),
    ];
    foreach ($expect as $name => $status) {
        expect($routes[$name]()->getStatusCode())->toBe($status, "$role → $name");
    }
})->with([
    // The bill was made by another Sales login, so a second Sales user gets 403 on it.
    'owner' => ['owner', ['create' => 200, 'index' => 200, 'show' => 200, 'done' => 200, 'print' => 200, 'pdf' => 200, 'status' => 200, 'products' => 200, 'context' => 200, 'collect' => 200, 'draft' => 200, 'qr' => 200]],
    'admin' => ['admin', ['create' => 200, 'index' => 200, 'show' => 200, 'done' => 200, 'print' => 200, 'pdf' => 200, 'status' => 200, 'products' => 200, 'context' => 200, 'collect' => 200, 'draft' => 200, 'qr' => 200]],
    'finance' => ['finance', ['create' => 403, 'index' => 200, 'show' => 200, 'done' => 200, 'print' => 200, 'pdf' => 200, 'status' => 200, 'products' => 403, 'context' => 403, 'collect' => 200, 'draft' => 403, 'qr' => 200]],
    'other sales' => ['sales', ['create' => 200, 'index' => 200, 'show' => 403, 'done' => 403, 'print' => 403, 'pdf' => 403, 'status' => 403, 'products' => 200, 'context' => 200, 'collect' => 200, 'draft' => 200, 'qr' => 200]],
    'technician' => ['technician', ['create' => 403, 'index' => 403, 'show' => 403, 'done' => 403, 'print' => 403, 'pdf' => 403, 'status' => 403, 'products' => 403, 'context' => 403, 'collect' => 403, 'draft' => 403, 'qr' => 403]],
]);

it('sends guests to sign in', function () {
    $this->get(route('bills.create'))->assertRedirect(route('login'));
    $this->get(route('bills.index'))->assertRedirect(route('login'));
});

it('lets only Owner/Admin cancel or take items back over HTTP', function () {
    foreach (['sales', 'finance', 'technician'] as $role) {
        $this->actingAs(staff($role))->post(route('bills.cancel', $this->bill), ['reason' => 'Duplicate'])->assertForbidden();
        $this->actingAs(staff($role))->post(route('bills.return', $this->bill), ['qty' => [$this->bill->items[0]->id => 1], 'reason' => 'x'])->assertForbidden();
    }
    expect($this->bill->fresh()->status)->toBe('paid');

    $this->actingAs(staff('admin'))->post(route('bills.cancel', $this->bill), ['reason' => 'Duplicate', 'note' => 'typed twice'])
        ->assertRedirect(route('bills.show', $this->bill));
    expect($this->bill->fresh())->status->toBe('cancelled')->cancel_reason->toBe('Duplicate: typed twice');

    $this->actingAs(staff('owner'))->get(route('bills.show', $this->bill))->assertOk()->assertSee('CANCELLED');
});

it('does not register the dev login route outside local', function () {
    $this->get('/dev/login-as/owner')->assertNotFound();
});

// ------------------------------------------------------------------ list visibility

it('shows Sales only their own bills, and everyone with reports.all_staff all bills', function () {
    $mine = staff('sales');
    $own = makeBill($mine, [[$this->product]], ['payments' => payFull([[$this->product]])]);

    $this->actingAs($mine)->get(route('bills.index'))->assertOk()
        ->assertSee($own->number)->assertDontSee($this->bill->number);
    $this->actingAs(staff('finance'))->get(route('bills.index'))->assertOk()
        ->assertSee($own->number)->assertSee($this->bill->number);
});

it('filters the list and totals it', function () {
    $owner = staff('owner');
    $due = makeBill($owner, [[$this->product]], ['customer_id' => $this->customer->id, 'payments' => [['mode' => 'upi', 'amount_paise' => 99900]]]);

    $this->actingAs($owner)->get(route('bills.index', ['unpaid' => 1]))->assertOk()
        ->assertSee($due->number)->assertDontSee($this->bill->number)->assertSee('₹4,000');
    $this->actingAs($owner)->get(route('bills.index', ['mode' => 'cash']))->assertOk()
        ->assertSee($this->bill->number)->assertDontSee($due->number);
    $this->actingAs($owner)->get(route('bills.index', ['q' => '04512']))->assertOk()->assertSee($this->bill->number);
    $this->actingAs($owner)->get(route('bills.index', ['range' => 'yesterday']))->assertOk()->assertSee('No bills in this period');
});

// ------------------------------------------------------------------ the save endpoint

it('saves a bill over JSON and returns the Bill done link, once per draft key', function () {
    $sales = staff('sales');
    $body = [
        'draft_key' => 'abc-1',
        'customer' => ['mobile' => '+91 97654 32109', 'name' => 'Vikram Shinde', 'source' => 'walk_in', 'car' => ['make' => 'Tata', 'model' => 'Nexon', 'reg_no' => 'MH12RD8890']],
        'items' => [['product_id' => $this->product->id, 'qty' => 2]],
        'payments' => [['mode' => 'cash', 'amount_paise' => 500000], ['mode' => 'upi', 'amount_paise' => 499800]],
        'send_whatsapp' => true,
    ];
    $r = $this->actingAs($sales)->postJson(route('bills.store'), $body)->assertCreated();
    $id = $r->json('id');
    expect($r->json('redirect'))->toBe(route('bills.done', $id));

    $this->actingAs($sales)->postJson(route('bills.store'), $body)->assertOk()->assertJsonPath('id', $id);
    expect(Invoice::count())->toBe(2);

    $this->actingAs($sales)->get(route('bills.done', $id))->assertOk()
        ->assertSee('Bill saved')->assertSee('wa.me/919765432109', false)->assertSee('Total savings');
});

it('answers bad bills with plain-words 422 errors', function () {
    $this->actingAs(staff('sales'))->postJson(route('bills.store'), ['items' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('items');
    $this->actingAs(staff('sales'))->postJson(route('bills.store'), [
        'items' => [['product_id' => $this->product->id, 'qty' => 1]],
        'payments' => [['mode' => 'cash', 'amount_paise' => 100]],
    ])->assertUnprocessable()->assertJsonValidationErrors('payments');
});

// ------------------------------------------------------------------ product index

it('serves the product index with short keys, no cost price, and an ETag', function () {
    billProduct(['name' => 'Hidden', 'is_active' => false]);
    $r = $this->actingAs(staff('sales'))->getJson(route('bills.product-index'))->assertOk();

    $rows = $r->json('products');
    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray(['i' => $this->product->id, 'n' => 'DHC Gleam 2.0 LED', 'p' => 499900, 'm' => 650000, 'g' => 18, 'h' => 9, 'k' => 1, 'c' => 1, 't' => 'p'])
        ->and(array_keys($rows[0]))->not->toContain('cost_paise')
        ->and(json_encode($rows))->not->toContain('300000');

    $etag = $r->headers->get('ETag');
    expect($etag)->not->toBeEmpty()->and($r->headers->get('Last-Modified'))->not->toBeEmpty();

    $this->actingAs(staff('sales'))->getJson(route('bills.product-index'), ['If-None-Match' => $etag])->assertStatus(304);

    // A sale changes stock, so the ETag changes.
    makeBill(staff('sales'), [[$this->product]], ['payments' => payFull([[$this->product]])]);
    $this->actingAs(staff('sales'))->getJson(route('bills.product-index'), ['If-None-Match' => $etag])->assertOk();
});

// ------------------------------------------------------------------ prefill

it('pre-fills New bill from a quotation (bill_prefill) and forgets it', function () {
    $sales = staff('sales');
    $car = $this->customer->cars->first();
    $this->actingAs($sales)->withSession(['bill_prefill' => [
        'customer_id' => $this->customer->id, 'customer_car_id' => $car->id, 'quotation_id' => 77,
        'items' => [['product_id' => $this->product->id, 'qty' => 2, 'unit_price_paise' => 480000], ['product_id' => 99999, 'qty' => 1, 'unit_price_paise' => 1]],
    ]])->get(route('bills.create'))
        ->assertOk()
        ->assertSessionMissing('bill_prefill')
        ->assertViewHas('config', fn ($c) => $c['prefill']['quotation_id'] === 77
            && $c['prefill']['customer']['mobile'] === '+919823104512'
            && $c['prefill']['customer_car_id'] === $car->id
            && $c['prefill']['items'] === [['product_id' => $this->product->id, 'qty' => 2, 'unit_price_paise' => 480000]]);

    // …and the saved bill keeps the quotation id. (Quotation 77 doesn't exist, so Sales can't
    // use its price: audit M8 accepts only the list price or a price on a quote they can see.)
    $price = $this->product->price_paise;
    $r = $this->actingAs($sales)->postJson(route('bills.store'), [
        'quotation_id' => 77, 'customer_id' => $this->customer->id,
        'items' => [['product_id' => $this->product->id, 'qty' => 1, 'unit_price_paise' => $price]],
        'payments' => [['mode' => 'cash', 'amount_paise' => $price]],
    ])->assertCreated();
    expect(Invoice::find($r->json('id'))->quotation_id)->toBe(77);
});

it('pre-fills from ?customer=ID and ?product=ID', function () {
    $this->actingAs(staff('sales'))->get(route('bills.create', ['customer' => $this->customer->id, 'product' => $this->product->id]))
        ->assertOk()
        ->assertViewHas('config', fn ($c) => $c['prefill']['active'] && $c['prefill']['customer_id'] === $this->customer->id
            && $c['prefill']['items'][0]['product_id'] === $this->product->id);
});

// ------------------------------------------------------------------ customer context, drafts, status

it('gives the bill screen the customer’s due, cars and suggestions', function () {
    makeBill(staff('owner'), [[$this->product]], ['customer_id' => $this->customer->id, 'customer_car_id' => $this->customer->cars[0]->id]);
    $this->actingAs(staff('sales'))->getJson(route('bills.customer-context', ['mobile' => '98231 04512']))
        ->assertOk()
        ->assertJsonPath('customer.name', 'Rahul Pawar')
        ->assertJsonPath('customer.cars.0.reg_pretty', 'MH 12 KT 4521')
        ->assertJsonPath('due_paise', 499900)
        ->assertJsonPath('bills_count', 2)
        ->assertJsonPath('car_suggestions.'.$this->customer->cars[0]->id.'.0', $this->product->id);
    $this->actingAs(staff('sales'))->getJson(route('bills.customer-context', ['mobile' => '9000000000']))->assertNotFound();
});

it('keeps one server draft per staff member', function () {
    $u = staff('sales');
    $this->actingAs($u)->putJson(route('bills.draft.save'), ['draft_key' => 'k', 'payload' => ['items' => [['qty' => 1]]], 'item_count' => 1])->assertOk()->assertJsonPath('saved', true);
    $this->actingAs($u)->putJson(route('bills.draft.save'), ['draft_key' => 'k2', 'payload' => ['items' => []]])->assertOk();
    $this->actingAs($u)->getJson(route('bills.draft'))->assertJsonPath('draft.draft_key', 'k2');
    $this->actingAs($u)->deleteJson(route('bills.draft.destroy'))->assertOk();
    $this->actingAs($u)->getJson(route('bills.draft'))->assertJsonPath('draft', null);
});

it('reports the WhatsApp status with a manual link until the module is merged', function () {
    $this->actingAs(staff('owner'))->getJson(route('bills.status', $this->bill))
        ->assertOk()->assertJsonPath('whatsapp', null)
        ->assertJsonPath('manual_link', fn ($l) => str_starts_with($l, 'https://wa.me/919823104512?text='));
});

// ------------------------------------------------------------------ PDF, print, public link

it('renders the A4 PDF to storage and the savings line on it', function () {
    $path = InvoicePdf::render($this->bill);
    expect($path)->toBe(storage_path('app/private/invoices/'.$this->bill->financial_year.'/'.$this->bill->number.'.pdf'))
        ->and(File::exists($path))->toBeTrue()
        ->and(substr(File::get($path), 0, 4))->toBe('%PDF');

    $html = view('bills.pdf', InvoicePdf::data($this->bill))->render();
    expect($html)->toContain('TAX INVOICE')
        ->toContain('Total savings ₹1,501 (MRP ₹6,500, you pay ₹4,999)')
        ->toContain('Rupees Four Thousand Nine Hundred Ninety-Nine Only')
        ->toContain('27AAJFJ1234K1Z5')
        ->toContain('computer-generated invoice');
    File::delete($path);
});

it('typesets the PDF in Noto Sans with the ₹ sign and no DejaVu fallback', function () {
    $html = view('bills.pdf', InvoicePdf::data($this->bill))->render();
    expect($html)->toContain("font-family: 'Noto Sans'")
        ->toContain('NotoSans-Regular.ttf')->toContain('NotoSans-SemiBold.ttf')->toContain('NotoSans-Medium.ttf')
        ->toContain('₹4,999')
        ->not->toContain('DejaVu');
    expect(InvoicePdf::options())->toMatchArray(['defaultFont' => 'Noto Sans', 'fontDir' => storage_path('fonts'), 'fontCache' => storage_path('fonts')]);

    // The embedded font is Noto Sans, not dompdf's DejaVu default.
    $pdf = InvoicePdf::output($this->bill);
    expect($pdf)->toContain('NotoSans')->not->toContain('DejaVu');
});

it('prints Bill of supply with no tax columns when there is no GSTIN', function () {
    Setting::put('tax.gstin', null);
    $bill = makeBill(staff('owner'), [[$this->product]], ['payments' => payFull([[$this->product]])]);
    $html = view('bills.pdf', InvoicePdf::data($bill))->render();
    expect($bill->kind)->toBe('bill_of_supply')
        ->and($html)->toContain('BILL OF SUPPLY')->not->toContain('TAX INVOICE')->not->toContain('>Taxable</th>')->not->toContain('GSTIN');
});

it('renders a bill of supply PDF with a UPI QR when money is due', function () {
    Setting::put('tax.gstin', null);
    $bill = makeBill(staff('owner'), [[$this->product]], ['customer_id' => $this->customer->id]);
    $html = view('bills.pdf', InvoicePdf::data($bill))->render();
    expect($html)->toContain('BILL OF SUPPLY')->toContain('Scan to pay ₹4,999')->toContain('data:image/svg+xml;base64')->not->toContain('GST summary');
    expect(substr(InvoicePdf::output($bill), 0, 4))->toBe('%PDF');
});

it('prints the 80 mm thermal receipt', function () {
    $this->actingAs(staff('owner'))->get(route('bills.print', [$this->bill, 'format' => 'thermal']))
        ->assertOk()->assertSee('80mm auto', false)->assertSee($this->bill->number)->assertSee('You saved ₹1,501');
    $this->actingAs(staff('owner'))->get(route('bills.print', [$this->bill, 'format' => 'a4']))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('serves the PDF on a signed 7-day link and refuses tampered or expired links', function () {
    $other = makeBill(staff('owner'), [[$this->product]], ['payments' => payFull([[$this->product]])]);
    $url = BillShare::pdfUrl($this->bill);
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(str_replace('/b/'.$this->bill->id.'/', '/b/'.$other->id.'/', $url))->assertForbidden();
    $this->travel(8)->days();
    $this->get($url)->assertForbidden();
});

it('writes the WhatsApp message in English with savings and the PDF link', function () {
    $msg = BillShare::message($this->bill, 'https://x.test/pdf');
    expect($msg)->toContain('Hello Rahul')->toContain($this->bill->number)->toContain('You saved ₹1,501')->toContain('https://x.test/pdf');
});

// ------------------------------------------------------------------ collect payment screen

it('collects a due from the screen and shows the receipt', function () {
    $owner = staff('owner');
    $due = makeBill($owner, [[$this->product]], ['customer_id' => $this->customer->id]);

    $this->actingAs(staff('finance'))->get(route('payments.collect', ['customer' => $this->customer->id]))
        ->assertOk()->assertSee($due->number)->assertSee('₹4,999');

    $r = $this->actingAs(staff('finance'))->post(route('payments.collect.store'), [
        'customer_id' => $this->customer->id, 'amount' => '2,000', 'mode' => 'cash', 'invoices' => [$due->id],
    ]);
    $r->assertRedirect();
    expect($due->fresh()->due_paise)->toBe(299900);
    $this->actingAs(staff('finance'))->get($r->headers->get('Location'))->assertOk()->assertSee('Payment received')->assertSee('Balance due ₹2,999');

    $this->actingAs(staff('finance'))->post(route('payments.collect.store'), [
        'customer_id' => $this->customer->id, 'amount' => '9999', 'mode' => 'cash', 'invoices' => [$due->id],
    ])->assertSessionHasErrors('amount');
});

it('shows the bill detail with GST summary, payments and actions', function () {
    $this->actingAs(staff('owner'))->get(route('bills.show', $this->bill))->assertOk()
        ->assertSee('GST summary')->assertSee('8512')->assertSee('Cancel bill')->assertSee('Return items')
        ->assertSee('Total savings');
    $this->actingAs(staff('finance'))->get(route('bills.show', $this->bill))->assertOk()->assertDontSee('Cancel bill');
});
