<?php

use App\Events\InvoiceCancelled;
use App\Events\InvoiceSaved;
use App\Models\BillDraft;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceSeries;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\StockMove;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\NumberSeries;
use App\Services\Billing\PaymentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/Billing/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    billingSetup($this);
    $this->service = app(InvoiceService::class);
});

afterEach(fn () => Carbon::setTestNow());

// ------------------------------------------------------------------ numbering

it('numbers bills JG-{fy}-0001 in order', function () {
    Carbon::setTestNow('2026-11-05 10:00');
    $sales = staff('sales');
    $p = billProduct();
    $a = makeBill($sales, [[$p]], ['payments' => payFull([[$p]])]);
    $b = makeBill($sales, [[$p]], ['payments' => payFull([[$p]])]);

    expect($a->number)->toBe('JG-2627-0001')
        ->and($b->number)->toBe('JG-2627-0002')
        ->and($a->financial_year)->toBe('2627');
});

it('restarts numbering in April (Indian financial year)', function () {
    $sales = staff('sales');
    $p = billProduct();
    Carbon::setTestNow('2027-03-31 18:00');
    expect(makeBill($sales, [[$p]], ['payments' => payFull([[$p]])])->number)->toBe('JG-2627-0001');
    expect(makeBill($sales, [[$p]], ['payments' => payFull([[$p]])])->number)->toBe('JG-2627-0002');
    Carbon::setTestNow('2027-04-01 09:00');
    expect(makeBill($sales, [[$p]], ['payments' => payFull([[$p]])])->number)->toBe('JG-2728-0001');
    expect(InvoiceSeries::count())->toBe(2);
});

it('leaves no gap when a bill fails after taking a number', function () {
    Carbon::setTestNow('2026-11-05 10:00');
    $series = app(NumberSeries::class);
    try {
        DB::transaction(function () use ($series) {
            $series->next($this->branch->id);
            throw new RuntimeException('printer on fire');
        });
    } catch (RuntimeException) {
    }
    DB::transaction(fn () => expect($series->next($this->branch->id)['number'])->toBe('JG-2627-0001'));

    // And through the service: a bill that fails validation mid-way takes no number either.
    $sales = staff('sales');
    $p = billProduct();
    expect(fn () => makeBill($sales, [[$p]], ['customer_car_id' => 999, 'customer_id' => billCustomer()->id, 'payments' => payFull([[$p]])]))
        ->toThrow(ValidationException::class);
    expect(makeBill($sales, [[$p]], ['payments' => payFull([[$p]])])->number)->toBe('JG-2627-0002');
});

it('uses the prefix from Settings and a separate credit note series', function () {
    Carbon::setTestNow('2026-11-05 10:00');
    Setting::put('billing.invoice_prefix', 'JGA');
    $s = app(NumberSeries::class);
    DB::transaction(function () use ($s) {
        expect($s->next($this->branch->id)['number'])->toBe('JGA-2627-0001')
            ->and($s->next($this->branch->id, NumberSeries::TYPE_CREDIT_NOTE)['number'])->toBe('JGACN-2627-0001');
    });
});

// ------------------------------------------------------------------ create

it('saves a bill with totals, items, payments, stock moves, customer stats and an audit row', function () {
    $sales = staff('sales');
    $gleam = billProduct(['name' => 'DHC Gleam 2.0', 'price_paise' => 499900, 'mrp_paise' => 650000], 10);
    $fit = billProduct(['name' => 'Fitting', 'type' => 'service', 'hsn' => '998714', 'price_paise' => 50000]);
    $c = billCustomer();

    $inv = makeBill($sales, [[$gleam, 2], [$fit, 1]], [
        'customer_id' => $c->id,
        'customer_car_id' => $c->cars->first()->id,
        'payments' => [['mode' => 'cash', 'amount_paise' => 500000], ['mode' => 'upi', 'amount_paise' => 549800, 'reference' => '4123']],
    ]);

    expect($inv->total_paise)->toBe(1049800)
        ->and($inv->subtotal_paise + $inv->cgst_paise + $inv->sgst_paise)->toBe(1049800)
        ->and($inv->paid_paise)->toBe(1049800)
        ->and($inv->due_paise)->toBe(0)
        ->and($inv->status)->toBe('paid')
        ->and($inv->kind)->toBe('tax_invoice')
        ->and($inv->seller_gstin)->toBe('27AAJFJ1234K1Z5')
        ->and($inv->place_of_supply)->toBe('27')
        ->and($inv->customer_name)->toBe('Rahul Pawar')
        ->and($inv->car_label)->toBe('Hyundai Creta · MH 12 KT 4521')
        ->and($inv->items)->toHaveCount(2)
        ->and($inv->items[0]->mrp_paise)->toBe(650000)
        ->and($inv->mrp_savings_paise)->toBe(2 * 650000 - 2 * 499900);

    expect($gleam->fresh()->on_hand)->toBe(8)
        ->and($gleam->fresh()->sold_count)->toBe(2)
        ->and($fit->fresh()->sold_count)->toBe(1)
        ->and(StockMove::where('type', 'sale')->count())->toBe(1) // services move no stock
        ->and(StockMove::where('type', 'sale')->first()->qty)->toBe(-2)
        ->and(StockMove::where('type', 'sale')->first()->reference_id)->toBe($inv->id);

    expect(Payment::count())->toBe(2)
        ->and($inv->allocations()->sum('amount_paise'))->toBe(1049800);

    $c->refresh();
    expect($c->total_spent_paise)->toBe(1049800)->and($c->last_visit_at)->not->toBeNull();
    expect(DB::table('activity_log')->where('log_name', 'bills')->where('event', 'created')->count())->toBe(1);
});

it('lets stock go negative instead of blocking the sale', function () {
    $p = billProduct([], 1);
    makeBill(staff('sales'), [[$p, 3]], ['payments' => payFull([[$p, 3]])]);
    expect($p->fresh()->on_hand)->toBe(-2);
});

it('makes a walk-in bill with no customer', function () {
    $p = billProduct();
    $inv = makeBill(staff('sales'), [[$p]], ['payments' => payFull([[$p]])]);

    expect($inv->customer_id)->toBeNull()
        ->and($inv->customer_name)->toBe('Walk-in customer')
        ->and($inv->send_whatsapp)->toBeFalse();
});

it('creates a new customer and car from the bill screen', function () {
    $p = billProduct();
    $inv = makeBill(staff('sales'), [[$p]], [
        'customer' => ['mobile' => '97654 32109', 'name' => 'Vikram Shinde', 'source' => 'instagram', 'car' => ['make' => 'Tata', 'model' => 'Nexon', 'reg_no' => 'mh12 rd 8890']],
        'payments' => payFull([[$p]]),
    ]);

    $c = Customer::where('mobile', '+919765432109')->first();
    expect($c->name)->toBe('Vikram Shinde')
        ->and($c->source)->toBe('instagram')
        ->and($inv->customer_id)->toBe($c->id)
        ->and($inv->car->reg_no)->toBe('MH12RD8890')
        ->and($inv->car_label)->toContain('Nexon');
});

it('fires InvoiceSaved only after the bill commits', function () {
    $seen = [];
    Event::listen(InvoiceSaved::class, function (InvoiceSaved $e) use (&$seen) {
        $seen[] = [$e->invoice->number, Invoice::whereKey($e->invoice->id)->exists(), $e->invoice->items()->count()];
    });
    $p = billProduct();
    $inv = makeBill(staff('sales'), [[$p, 2]], ['payments' => payFull([[$p, 2]])]);

    expect($seen)->toBe([[$inv->number, true, 1]]);

    // Inside an outer transaction that rolls back, the event never fires.
    $seen = [];
    try {
        DB::transaction(function () use ($p) {
            makeBill(staff('sales'), [[$p]], ['payments' => payFull([[$p]])]);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    expect($seen)->toBe([]);
});

it('never makes two bills for the same draft key (double tap / retry)', function () {
    $p = billProduct([], 10);
    $by = staff('sales');
    $data = ['draft_key' => 'draft-123', 'payments' => payFull([[$p]])];
    $a = makeBill($by, [[$p]], $data);
    $b = makeBill($by, [[$p]], $data);

    expect($b->id)->toBe($a->id)
        ->and(Invoice::count())->toBe(1)
        ->and($p->fresh()->on_hand)->toBe(9)
        ->and(Payment::count())->toBe(1);
});

it('clears the staff member’s server draft once the bill is saved', function () {
    $by = staff('sales');
    BillDraft::create(['user_id' => $by->id, 'draft_key' => 'k1', 'payload' => ['items' => []]]);
    $p = billProduct();
    makeBill($by, [[$p]], ['draft_key' => 'k1', 'payments' => payFull([[$p]])]);
    expect(BillDraft::count())->toBe(0);
});

it('rejects payments above the total and empty bills', function () {
    $p = billProduct();
    expect(fn () => makeBill(staff('sales'), [[$p]], ['payments' => [['mode' => 'cash', 'amount_paise' => 999999]]]))
        ->toThrow(ValidationException::class);
    expect(fn () => makeBill(staff('sales'), []))->toThrow(ValidationException::class);
    expect(Invoice::count())->toBe(0);
});

it('issues a bill of supply with no tax when the shop has no GSTIN', function () {
    Setting::put('tax.gstin', null);
    $p = billProduct();
    $inv = makeBill(staff('sales'), [[$p]], ['payments' => payFull([[$p]])]);

    expect($inv->kind)->toBe('bill_of_supply')
        ->and($inv->cgst_paise + $inv->sgst_paise + $inv->igst_paise)->toBe(0)
        ->and($inv->subtotal_paise)->toBe(499900)
        ->and($inv->seller_gstin)->toBeNull();
});

it('only lets Owner/Admin add an item that is not in the product list', function () {
    $custom = ['items' => [['name' => 'Special wrap job', 'unit_price_paise' => 1200000, 'gst_rate' => 18, 'qty' => 1]], 'payments' => [['mode' => 'cash', 'amount_paise' => 1200000]]];
    expect(fn () => $this->service->create($custom, staff('sales')))->toThrow(ValidationException::class);
    $inv = $this->service->create($custom, staff('admin'));
    expect($inv->items[0]->product_id)->toBeNull()->and($inv->total_paise)->toBe(1200000);
});

// ------------------------------------------------------------------ discount cap & dues

it('caps the Sales discount at billing.max_discount_percent', function () {
    $p = billProduct(['price_paise' => 1000000]);
    $sales = staff('sales');

    // 10% is fine.
    $ok = makeBill($sales, [[$p]], ['bill_discount_paise' => 100000, 'payments' => [['mode' => 'cash', 'amount_paise' => 900000]]]);
    expect($ok->discount_paise)->toBe(100000);

    // 15% is not, for Sales…
    expect(fn () => makeBill($sales, [[$p]], ['bill_discount_paise' => 150000, 'payments' => [['mode' => 'cash', 'amount_paise' => 850000]]]))
        ->toThrow(ValidationException::class, 'needs the Owner');
    // …nor a sneaky lower unit price.
    expect(fn () => $this->service->create(['items' => [['product_id' => $p->id, 'qty' => 1, 'unit_price_paise' => 800000]], 'payments' => []], $sales))
        ->toThrow(ValidationException::class, 'needs the Owner');

    // The Owner has prices.manage.
    $big = makeBill(staff('owner'), [[$p]], ['bill_discount_paise' => 300000, 'payments' => [['mode' => 'cash', 'amount_paise' => 700000]]]);
    expect($big->total_paise)->toBe(700000);

    // The limit comes from Settings.
    Setting::put('billing.max_discount_percent', 20);
    expect(makeBill($sales, [[$p]], ['bill_discount_paise' => 150000, 'payments' => [['mode' => 'cash', 'amount_paise' => 850000]]])->total_paise)->toBe(850000);
});

it('lets Sales leave a small due but needs Admin above the limit', function () {
    $c = billCustomer();
    $cheap = billProduct(['price_paise' => 150000]);
    $dear = billProduct(['price_paise' => 900000]);
    $sales = staff('sales');

    $small = makeBill($sales, [[$cheap]], ['customer_id' => $c->id]);
    expect($small->due_paise)->toBe(150000)->and($small->status)->toBe('due');

    expect(fn () => makeBill($sales, [[$dear]], ['customer_id' => $c->id, 'payments' => [['mode' => 'cash', 'amount_paise' => 100000]]]))
        ->toThrow(ValidationException::class, 'needs an Admin');

    $admin = makeBill(staff('admin'), [[$dear]], ['customer_id' => $c->id, 'payments' => [['mode' => 'cash', 'amount_paise' => 100000]]]);
    expect($admin->status)->toBe('partial')->and($admin->due_paise)->toBe(800000);

    Setting::put('billing.due_limit_paise', 1000000);
    expect(makeBill($sales, [[$dear]], ['customer_id' => $c->id])->due_paise)->toBe(900000);
});

it('never leaves a due on a walk-in bill', function () {
    $p = billProduct(['price_paise' => 50000]);
    makeBill(staff('owner'), [[$p]]);
})->throws(ValidationException::class, 'mobile number');

// ------------------------------------------------------------------ savings

it('works out savings from MRP with and without an extra discount', function () {
    $sales = staff('sales');
    $gleam = billProduct(['price_paise' => 499900, 'mrp_paise' => 650000]);
    $noMrp = billProduct(['price_paise' => 50000, 'mrp_paise' => null]);
    $mrpBelow = billProduct(['price_paise' => 50000, 'mrp_paise' => 40000]);

    $a = makeBill($sales, [[$gleam, 2], [$noMrp]], ['payments' => payFull([[$gleam, 2], [$noMrp]])]);
    expect($a->mrp_savings_paise)->toBe(300200)
        ->and($a->totalSavingsPaise())->toBe(300200)
        ->and($a->savingsLine())->toBe('Total savings ₹3,002 (MRP ₹13,500 → you pay ₹10,498)');

    $b = makeBill($sales, [[$gleam]], ['bill_discount_paise' => 39900, 'payments' => payFull([[$gleam]], 'cash', 39900)]);
    expect($b->mrp_savings_paise)->toBe(150100)
        ->and($b->discount_paise)->toBe(39900)
        ->and($b->totalSavingsPaise())->toBe(190000)
        ->and($b->total_paise)->toBe(460000)
        ->and($b->savingsLine())->toBe('Total savings ₹1,900 (MRP ₹6,500 → you pay ₹4,600)');

    $c = makeBill($sales, [[$noMrp], [$mrpBelow]], ['payments' => payFull([[$noMrp], [$mrpBelow]])]);
    expect($c->totalSavingsPaise())->toBe(0)->and($c->savingsLine())->toBeNull();

    // GST is on our price, never on the MRP.
    expect($a->subtotal_paise + $a->cgst_paise + $a->sgst_paise)->toBe(2 * 499900 + 50000);
});

// ------------------------------------------------------------------ cancel & return

it('cancels a bill: stock back, refund recorded, number kept, event fired', function () {
    Event::fake([InvoiceCancelled::class]);
    $p = billProduct([], 10);
    $c = billCustomer();
    $inv = makeBill(staff('sales'), [[$p, 2]], ['customer_id' => $c->id, 'payments' => payFull([[$p, 2]], 'upi')]);
    $number = $inv->number;

    $this->service->cancel($inv, 'Duplicate', staff('admin'));
    $inv->refresh();

    expect($inv->status)->toBe('cancelled')
        ->and($inv->number)->toBe($number)
        ->and($inv->cancel_reason)->toBe('Duplicate')
        ->and($inv->cancelled_at)->not->toBeNull()
        ->and($inv->due_paise)->toBe(0)
        ->and($p->fresh()->on_hand)->toBe(10)
        ->and($p->fresh()->sold_count)->toBe(0)
        ->and(StockMove::where('type', 'sale_cancel')->sum('qty'))->toBe(2)
        ->and(Payment::where('kind', 'refund')->sum('amount_paise'))->toBe(-999800)
        ->and(Payment::where('kind', 'refund')->first()->mode)->toBe('upi')
        ->and($c->fresh()->total_spent_paise)->toBe(0)
        ->and(Invoice::count())->toBe(1);
    Event::assertDispatched(InvoiceCancelled::class);

    // The next bill does not reuse the number.
    expect(makeBill(staff('sales'), [[$p]], ['payments' => payFull([[$p]])])->number)->not->toBe($number);
});

it('only lets Owner/Admin cancel, and needs a reason', function () {
    $p = billProduct();
    $inv = makeBill(staff('sales'), [[$p]], ['payments' => payFull([[$p]])]);

    expect(fn () => $this->service->cancel($inv, 'Oops', staff('sales')))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->cancel($inv, 'Oops', staff('finance')))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->cancel($inv, '  ', staff('owner')))->toThrow(ValidationException::class);
    $this->service->cancel($inv, 'Wrong item', staff('owner'));
    expect(fn () => $this->service->cancel($inv, 'Again', staff('owner')))->toThrow(ValidationException::class);
});

it('takes back part of a bill as a credit note', function () {
    $p = billProduct(['price_paise' => 100000], 10);
    $q = billProduct(['price_paise' => 50000], 10);
    $c = billCustomer();
    $inv = makeBill(staff('sales'), [[$p, 3], [$q, 1]], ['customer_id' => $c->id, 'payments' => payFull([[$p, 3], [$q, 1]])]);
    $line = $inv->items->firstWhere('product_id', $p->id);

    $note = $this->service->returnItems($inv, [$line->id => 1], 'Customer returned', staff('admin'));

    expect($note->number)->toStartWith('JGCN-')
        ->and($note->total_paise)->toBe(100000)
        ->and($note->subtotal_paise + $note->cgst_paise + $note->sgst_paise)->toBe(100000)
        ->and($note->refund_paise)->toBe(100000)
        ->and($p->fresh()->on_hand)->toBe(8)
        ->and($line->fresh()->returned_qty)->toBe(1)
        ->and($inv->fresh()->credited_paise)->toBe(100000)
        ->and($inv->fresh()->status)->toBe('paid');

    // Returning the rest of the line credits exactly what is left of it.
    $note2 = $this->service->returnItems($inv, [$line->id => 2], 'Rest back', staff('admin'));
    expect($note->total_paise + $note2->total_paise)->toBe($line->line_total_paise);
    expect(fn () => $this->service->returnItems($inv, [$line->id => 1], 'Too many', staff('admin')))->toThrow(ValidationException::class);
    expect(fn () => $this->service->returnItems($inv, [$inv->items[1]->id => 1], 'x', staff('sales')))->toThrow(AuthorizationException::class);
});

it('uses a return to clear a due before refunding', function () {
    $p = billProduct(['price_paise' => 100000], 10);
    $c = billCustomer();
    $inv = makeBill(staff('admin'), [[$p, 2]], ['customer_id' => $c->id, 'payments' => [['mode' => 'cash', 'amount_paise' => 150000]]]);
    $note = $this->service->returnItems($inv, [$inv->items[0]->id => 1], 'One back', staff('admin'));

    expect($note->refund_paise)->toBe(50000)
        ->and($inv->fresh()->due_paise)->toBe(0)
        ->and($inv->fresh()->status)->toBe('paid');
});

// ------------------------------------------------------------------ collect payment

it('collects a due oldest-first across bills and leaves the rest', function () {
    $c = billCustomer();
    $owner = staff('owner');
    $p = billProduct(['price_paise' => 150000]);
    $q = billProduct(['price_paise' => 200000]);
    Carbon::setTestNow(now()->subDays(10));
    $old = makeBill($owner, [[$p]], ['customer_id' => $c->id]);
    Carbon::setTestNow(now()->addDays(5));
    $new = makeBill($owner, [[$q]], ['customer_id' => $c->id]);
    Carbon::setTestNow();

    $pay = app(PaymentService::class)->collect($c, 250000, 'upi', 'UTR1', null, staff('finance'));

    expect($old->fresh()->due_paise)->toBe(0)->and($old->fresh()->status)->toBe('paid')
        ->and($new->fresh()->due_paise)->toBe(100000)->and($new->fresh()->status)->toBe('partial')
        ->and($pay->allocations->pluck('amount_paise', 'invoice_id')->all())->toBe([$old->id => 150000, $new->id => 100000])
        ->and($pay->invoice_id)->toBeNull()
        ->and(PaymentService::customerDuePaise($c->id))->toBe(100000);
});

it('skips bills staff unticked and refuses more than the due', function () {
    $c = billCustomer();
    $owner = staff('owner');
    $p = billProduct(['price_paise' => 150000]);
    $a = makeBill($owner, [[$p]], ['customer_id' => $c->id]);
    $b = makeBill($owner, [[$p]], ['customer_id' => $c->id]);

    $pay = app(PaymentService::class)->collect($c, 100000, 'cash', null, [$b->id], $owner);
    expect($a->fresh()->due_paise)->toBe(150000)
        ->and($b->fresh()->due_paise)->toBe(50000)
        ->and($pay->invoice_id)->toBe($b->id);

    expect(fn () => app(PaymentService::class)->collect($c, 999999, 'cash', null, null, $owner))->toThrow(ValidationException::class);
});
