<?php

use App\Events\InvoiceSaved;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Setting;
use App\Services\Customers\CustomerService;
use App\Services\Quotations\FollowUpMessages;
use App\Services\Quotations\FollowUpScheduler;
use App\Services\Quotations\LeadService;
use App\Services\Quotations\LeakReport;
use App\Services\Quotations\QuotationService;
use App\Services\Quotations\QuoteTotals;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->seed(SettingsSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:00', 'Asia/Kolkata'));
});

afterEach(fn () => Carbon::setTestNow());

function qtProduct(string $name = 'DHC Gleam 2.0 LED headlight', int $price = 499900, ?int $mrp = 650000, array $extra = []): Product
{
    return tap((new Product)->forceFill(array_merge([
        'name' => $name, 'price_paise' => $price, 'mrp_paise' => $mrp, 'gst_rate' => 18, 'type' => 'product',
    ], $extra)), fn ($p) => $p->save());
}

function qtCustomer(string $mobile = '9823107676', string $name = 'Vikram Shinde'): Customer
{
    $svc = app(CustomerService::class);
    $c = $svc->findOrCreateByMobile($mobile, ['name' => $name], staff('sales'));
    if (! $c->cars()->exists()) {
        $svc->addCar($c, ['make' => 'Kia', 'model' => 'Seltos', 'reg_no' => 'MH12TR9021'], staff('sales'));
    }

    return $c->load('cars');
}

function qtQuote($by = null, ?Customer $customer = null, ?array $items = null): Quotation
{
    $by ??= staff('sales');
    $customer ??= qtCustomer();
    $items ??= [['product_id' => qtProduct()->id, 'qty' => 1]];

    return app(QuotationService::class)->create([
        'customer_id' => $customer->id,
        'customer_car_id' => $customer->cars->first()?->id,
        'items' => $items,
    ], $by);
}

// ---------------------------------------------------------------- numbering

it('numbers quotations gap-free per branch and financial year with the prefix from Settings', function () {
    $a = qtQuote();
    $b = qtQuote();
    expect($a->number)->toBe('JGQ-2627-0001')->and($b->number)->toBe('JGQ-2627-0002');

    Setting::put('billing.quotation_prefix', 'JGQT');
    expect(qtQuote()->number)->toBe('JGQT-2627-0003');

    // A new financial year starts again at 0001.
    Carbon::setTestNow(Carbon::parse('2027-04-01 10:00', 'Asia/Kolkata'));
    expect(qtQuote()->number)->toBe('JGQT-2728-0001');

    // Another branch has its own counter.
    $other = Branch::create(['name' => 'Second', 'code' => 'JG2']);
    $u = staff('sales', ['branch_id' => $other->id]);
    expect(qtQuote($u)->number)->toBe('JGQT-2728-0001')->and(Quotation::query()->where('branch_id', $other->id)->count())->toBe(1);
});

it('does not burn a number when saving fails', function () {
    qtQuote();
    try {
        app(QuotationService::class)->create(['customer_id' => qtCustomer()->id, 'items' => [['product_id' => 99999, 'qty' => 1]]], staff('sales'));
    } catch (ValidationException) {
    }
    expect(qtQuote()->number)->toBe('JGQ-2627-0002');
});

// ---------------------------------------------------------------- totals and savings

it('works out GST-inclusive totals in paise', function () {
    $t = QuoteTotals::calculate([
        ['qty' => 1, 'unit_price_paise' => 118000, 'gst_rate' => 18],
        ['qty' => 2, 'unit_price_paise' => 50000, 'gst_rate' => 18],
    ]);
    expect($t['total_paise'])->toBe(218000)
        ->and($t['taxable_paise'])->toBe(100000 + 84746)
        ->and($t['gst_paise'])->toBe(218000 - 184746)
        ->and(QuoteTotals::inclusive(100000, 18, false))->toBe(118000)
        ->and(QuoteTotals::inclusive(100000, 18, true))->toBe(100000);
});

it('works out "you save" from MRP and never goes negative', function () {
    expect(QuoteTotals::lineSavings(1, 3250000, 3800000))->toBe(550000)
        ->and(QuoteTotals::lineSavings(2, 320000, 450000))->toBe(260000)
        ->and(QuoteTotals::lineSavings(1, 500000, null))->toBe(0)
        ->and(QuoteTotals::lineSavings(1, 500000, 400000))->toBe(0)
        ->and(QuoteTotals::lineSavings(1, 500000, 500000))->toBe(0);

    $t = QuoteTotals::calculate([
        ['qty' => 1, 'unit_price_paise' => 3250000, 'gst_rate' => 18, 'mrp_paise' => 3800000],
        ['qty' => 1, 'unit_price_paise' => 50000, 'gst_rate' => 18, 'mrp_paise' => null],
    ]);
    expect($t['savings_paise'])->toBe(550000)->and($t['mrp_total_paise'])->toBe(3800000 + 50000);
});

it('snapshots MRP on the quote lines and stores the saving', function () {
    $stereo = qtProduct('Blaupunkt Android stereo', 2150000, 2499000);
    $mats = qtProduct('7D floor mats', 350000, 450000);
    $q = qtQuote(items: [['product_id' => $stereo->id, 'qty' => 1], ['product_id' => $mats->id, 'qty' => 2]]);

    expect($q->items[0]->mrp_paise)->toBe(2499000)
        ->and($q->total_paise)->toBe(2150000 + 700000)
        ->and($q->savings_paise)->toBe(349000 + 200000);

    $stereo->forceFill(['mrp_paise' => 9999900])->save(); // later MRP changes don't touch old quotes
    expect($q->fresh()->items[0]->mrp_paise)->toBe(2499000);
});

it('uses the product price for Sales but lets Owner/Admin quote their own price', function () {
    $p = qtProduct();
    $sales = app(QuotationService::class)->create(['customer_id' => qtCustomer()->id, 'items' => [['product_id' => $p->id, 'qty' => 1, 'unit_price_paise' => 100]]], staff('sales'));
    $admin = app(QuotationService::class)->create(['customer_id' => qtCustomer()->id, 'items' => [['product_id' => $p->id, 'qty' => 1, 'unit_price_paise' => 450000]]], staff('admin'));

    expect($sales->total_paise)->toBe(499900)->and($admin->total_paise)->toBe(450000);
});

// ---------------------------------------------------------------- schedule

it('plans follow-ups on the days set in Settings', function () {
    $q = qtQuote();
    expect($q->followUps()->orderBy('due_on')->get()->map(fn ($f) => $f->due_on->toDateString().'/'.$f->step)->all())
        ->toBe(['2026-11-06/1', '2026-11-08/3', '2026-11-11/6', '2026-11-15/10']);

    Setting::put('followups.schedule_days', '2, 5');
    expect(qtQuote()->followUps()->orderBy('due_on')->pluck('step')->all())->toBe([2, 5])
        ->and(FollowUpScheduler::scheduleDays())->toBe([2, 5]);

    Setting::put('followups.schedule_days', [1, 4]); // a JSON array is accepted too
    expect(FollowUpScheduler::scheduleDays())->toBe([1, 4]);
});

it('gives a lead a same-day follow-up, then the schedule', function () {
    $lead = app(LeadService::class)->create(['mobile' => '98230 11245', 'interest' => 'PPF for Creta', 'source' => 'whatsapp_ad'], staff('admin'));
    expect($lead->followUps()->orderBy('due_on')->pluck('step')->all())->toBe([0, 1, 3, 6, 10])
        ->and($lead->followUps()->min('due_on'))->toStartWith('2026-11-05');
});

// ---------------------------------------------------------------- outcomes

it('books a visit with a reminder on that day for "coming on"', function () {
    $q = qtQuote();
    $f = $q->followUps()->orderBy('due_on')->first();
    app(FollowUpScheduler::class)->log($f, 'coming_on', staff('sales'), '2026-11-09');

    $q->refresh();
    expect($q->status)->toBe(Quotation::VISIT_BOOKED)
        ->and($q->visit_on->toDateString())->toBe('2026-11-09')
        ->and($q->followUps()->pending()->count())->toBe(1)
        ->and($q->followUps()->pending()->first()->kind)->toBe('visit')
        ->and($q->followUps()->pending()->first()->due_on->toDateString())->toBe('2026-11-09');
});

it('moves the next follow-up to the chosen date for "call later"', function () {
    $q = qtQuote();
    $f = $q->followUps()->orderBy('due_on')->first();
    app(FollowUpScheduler::class)->log($f, 'call_later', staff('sales'), '2026-11-09', 'Asking his brother');

    $pending = $q->followUps()->pending()->orderBy('due_on')->get();
    // Day 3 (8 Nov) is dropped; the call-back on 9 Nov, Day 6 and Day 10 stay.
    expect($pending->map(fn ($x) => $x->due_on->toDateString().'/'.$x->kind)->all())
        ->toBe(['2026-11-09/call_later', '2026-11-11/scheduled', '2026-11-15/scheduled'])
        ->and($f->fresh()->outcome_note)->toBe('Asking his brother')
        ->and($q->fresh()->status)->toBe(Quotation::OPEN);
});

it('notifies Owner and Admin when the customer wants a better price, and keeps it open', function () {
    $owner = staff('owner');
    $admin = staff('admin');
    $sales = staff('sales');
    $q = qtQuote($sales);
    app(FollowUpScheduler::class)->log($q->followUps()->orderBy('due_on')->first(), 'wants_better_price', $sales, null, 'Wants ₹500 off');

    expect($q->fresh()->status)->toBe(Quotation::OPEN)
        ->and($owner->notifications()->count())->toBe(1)
        ->and($admin->notifications()->count())->toBe(1)
        ->and($sales->notifications()->count())->toBe(0)
        ->and($owner->notifications()->first()->data['quotation_id'])->toBe($q->id)
        ->and($owner->notifications()->first()->data['note'])->toBe('Wants ₹500 off')
        ->and($q->followUps()->pending()->count())->toBe(3);
});

it('closes the quote as lost with the reason for bought elsewhere / not interested / wrong number', function (string $outcome) {
    $q = qtQuote();
    app(FollowUpScheduler::class)->log($q->followUps()->orderBy('due_on')->first(), $outcome, staff('sales'), null, 'note');
    $q->refresh();
    expect($q->status)->toBe(Quotation::LOST)
        ->and($q->lost_reason)->toBe($outcome)
        ->and($q->lost_at)->not->toBeNull()
        ->and($q->followUps()->pending()->count())->toBe(0);
})->with(['bought_elsewhere', 'not_interested', 'wrong_number']);

it('retries tomorrow on no answer', function () {
    $q = qtQuote();
    Carbon::setTestNow(Carbon::parse('2026-11-08 11:00', 'Asia/Kolkata')); // Day 3
    $day3 = $q->followUps()->where('step', 3)->first();
    app(FollowUpScheduler::class)->log($day3, 'no_answer', staff('sales'));

    $retry = $q->followUps()->pending()->orderBy('due_on')->first();
    expect($retry->kind)->toBe('retry')->and($retry->due_on->toDateString())->toBe('2026-11-09')->and($retry->step)->toBe(3);
});

it('marks a lead contacted, visit booked or lost from the outcomes', function () {
    $svc = app(LeadService::class);
    $u = staff('admin');
    $a = $svc->create(['mobile' => '9823011245', 'interest' => 'PPF'], $u);
    $b = $svc->create(['mobile' => '9823011246', 'interest' => 'Mats'], $u);
    $c = $svc->create(['mobile' => '9823011247', 'interest' => 'LED'], $u);
    $s = app(FollowUpScheduler::class);
    $s->log($a->followUps()->orderBy('due_on')->first(), 'call_later', $u, '2026-11-07');
    $s->log($b->followUps()->orderBy('due_on')->first(), 'coming_on', $u, '2026-11-06');
    $s->log($c->followUps()->orderBy('due_on')->first(), 'wrong_number', $u);

    expect($a->fresh()->status)->toBe(Lead::CONTACTED)
        ->and($b->fresh()->status)->toBe(Lead::VISIT_BOOKED)
        ->and($c->fresh()->status)->toBe(Lead::LOST)
        ->and($c->fresh()->lost_reason)->toBe('wrong_number');
});

it('refuses a dated outcome without a date, or a date in the past', function () {
    $f = qtQuote()->followUps()->first();
    expect(fn () => app(FollowUpScheduler::class)->log($f, 'call_later', staff('sales')))->toThrow(ValidationException::class);
    expect(fn () => app(FollowUpScheduler::class)->log($f, 'coming_on', staff('sales'), '2026-11-01'))->toThrow(ValidationException::class);
});

it('undoes a logged result completely', function () {
    $q = qtQuote();
    $f = $q->followUps()->orderBy('due_on')->first();
    $before = $q->followUps()->pending()->pluck('id')->sort()->values()->all();
    app(FollowUpScheduler::class)->log($f, 'coming_on', staff('sales'), '2026-11-09');
    app(FollowUpScheduler::class)->undo($f);

    expect($q->fresh()->status)->toBe(Quotation::OPEN)
        ->and($q->fresh()->visit_on)->toBeNull()
        ->and($q->followUps()->pending()->pluck('id')->sort()->values()->all())->toBe($before)
        ->and($f->fresh()->outcome)->toBeNull();
});

// ---------------------------------------------------------------- overdue

it('counts a follow-up as overdue only when pending and due before today (shop time)', function () {
    $q = qtQuote();
    $day1 = $q->followUps()->where('step', 1)->first(); // due 6 Nov

    Carbon::setTestNow(Carbon::parse('2026-11-06 23:30', 'Asia/Kolkata'));
    expect($day1->fresh()->isOverdue())->toBeFalse()->and(FollowUp::overdue()->count())->toBe(0);

    // 00:30 IST on 7 Nov is still 6 Nov in UTC; the shop's "today" is the 7th.
    Carbon::setTestNow(Carbon::parse('2026-11-07 00:30', 'Asia/Kolkata'));
    expect($day1->fresh()->isOverdue())->toBeTrue()
        ->and(FollowUp::overdue()->pluck('id')->all())->toBe([$day1->id])
        ->and(Quotation::query()->overdue()->count())->toBe(1);

    app(FollowUpScheduler::class)->log($day1, 'no_answer', staff('sales'));
    expect($day1->fresh()->isOverdue())->toBeFalse()->and(FollowUp::overdue()->count())->toBe(0);
});

// ---------------------------------------------------------------- auto-win

it('marks the linked quotation won when a bill is saved', function () {
    $q = qtQuote();
    $other = qtQuote(customer: $q->customer);
    InvoiceSaved::dispatch((object) ['id' => 501, 'customer_id' => $q->customer_id, 'quotation_id' => $q->id, 'total_paise' => 499900]);

    expect($q->fresh()->status)->toBe(Quotation::WON)
        ->and($q->fresh()->won_invoice_id)->toBe(501)
        ->and($q->followUps()->pending()->count())->toBe(0)
        ->and($other->fresh()->status)->toBe(Quotation::OPEN);
});

it('marks the same customer’s quotes and leads from the last 30 days won', function () {
    $c = qtCustomer();
    Carbon::setTestNow(Carbon::parse('2026-09-20 10:00', 'Asia/Kolkata'));
    $old = qtQuote(customer: $c);
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'Asia/Kolkata'));
    $recent = qtQuote(customer: $c);
    $lead = app(LeadService::class)->create(['mobile' => $c->mobile, 'interest' => 'Mats'], staff('admin'));
    $stranger = qtQuote(customer: qtCustomer('9823104512', 'Rahul Pawar'));
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:00', 'Asia/Kolkata'));
    app(QuotationService::class)->expireDue();
    expect($recent->fresh()->status)->toBe(Quotation::EXPIRED);

    event(new InvoiceSaved((object) ['id' => 777, 'customer_id' => $c->id, 'quotation_id' => null, 'total_paise' => 100]));

    expect($recent->fresh()->status)->toBe(Quotation::WON)   // expired but within 30 days: still a win
        ->and($old->fresh()->status)->toBe(Quotation::EXPIRED)
        ->and($lead->fresh()->status)->toBe(Lead::WON)
        ->and($lead->fresh()->won_invoice_id)->toBe(777)
        ->and($stranger->fresh()->status)->not->toBe(Quotation::WON)
        ->and(FollowUp::query()->where('subject_id', $recent->id)->where('subject_type', Quotation::class)->pending()->count())->toBe(0);
});

// ---------------------------------------------------------------- expiry

it('expires quotes past valid-until but keeps the last follow-up', function () {
    $q = qtQuote(); // valid until 12 Nov (7 days)
    $won = qtQuote();
    app(QuotationService::class)->autoWin(1, null, $won->id);

    Carbon::setTestNow(Carbon::parse('2026-11-12 23:00', 'Asia/Kolkata'));
    $this->artisan('quotations:expire')->assertSuccessful();
    expect($q->fresh()->status)->toBe(Quotation::OPEN);

    Carbon::setTestNow(Carbon::parse('2026-11-13 09:00', 'Asia/Kolkata'));
    $this->artisan('quotations:expire')->expectsOutputToContain('1 quotation(s) marked expired')->assertSuccessful();

    $q->refresh();
    expect($q->status)->toBe(Quotation::EXPIRED)
        ->and($q->followUps()->pending()->count())->toBe(1)
        ->and($q->followUps()->pending()->first()->step)->toBe(10)
        ->and($won->fresh()->status)->toBe(Quotation::WON);
});

// ---------------------------------------------------------------- bill prefill

it('hands the quote to billing in the exact bill_prefill shape', function () {
    $sales = staff('sales');
    $p = qtProduct();
    $q = qtQuote($sales, items: [['product_id' => $p->id, 'qty' => 2]]);

    $res = $this->actingAs($sales)->post(route('quotations.convert', $q));

    expect(session('bill_prefill'))->toBe([
        'customer_id' => $q->customer_id,
        'customer_car_id' => $q->customer_car_id,
        'quotation_id' => $q->id,
        'items' => [['product_id' => $p->id, 'qty' => 2, 'unit_price_paise' => 499900]],
    ]);
    // bills.create isn't on this branch yet, so it says so instead of a dead link.
    $res->assertRedirect(route('quotations.show', $q))->assertSessionHas('status', fn ($s) => str_contains($s, 'Billing coming soon'));
});

// ---------------------------------------------------------------- messages

it('renders the WhatsApp message for each step with name, car, items, amount, saving and PDF link', function () {
    $q = qtQuote(items: [['product_id' => qtProduct('Blaupunkt Android stereo', 3250000, 3800000)->id, 'qty' => 1]]);
    $msgs = app(FollowUpMessages::class);

    $day1 = $msgs->forFollowUp($q->followUps()->where('step', 1)->first());
    expect($day1)->toStartWith('Hello Vikram, thank you for visiting Jay Ganesh Car Accessories')
        ->toContain('your Seltos: Blaupunkt Android stereo, total ₹32,500 (incl. GST)')
        ->toContain('MRP ₹38,000 → our price ₹32,500, you save ₹5,500 on this quote.')
        ->toContain('valid until 12 Nov 2026')
        ->toContain('/q/'.$q->id.'.pdf?expires=');

    expect($msgs->forFollowUp($q->followUps()->where('step', 6)->first()))->toContain('a gentle reminder');
    expect($msgs->forFollowUp($q->followUps()->where('step', 10)->first()))->toContain('this is our last check');

    Setting::put('followups.msg_day3', 'Hi {name}, {car} {amount} {unknown}');
    expect($msgs->forFollowUp($q->followUps()->where('step', 3)->first()))->toBe('Hi Vikram, Seltos ₹32,500 {unknown}');

    $wa = FollowUpMessages::waLink('98231 07676', 'Hello & welcome');
    expect($wa)->toBe('https://wa.me/919823107676?text=Hello%20%26%20welcome');
});

it('serves the quotation PDF on a signed link only', function () {
    $q = qtQuote();
    $this->get(FollowUpMessages::pdfLink($q))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get('/q/'.$q->id.'.pdf')->assertForbidden();
});

// ---------------------------------------------------------------- leak report

it('adds up the owner’s leak report for the month', function () {
    $rohit = staff('sales', ['name' => 'Rohit Shinde']);
    $sagar = staff('sales', ['name' => 'Sagar More']);
    Carbon::setTestNow(Carbon::parse('2026-11-02 10:00', 'Asia/Kolkata'));
    $won = qtQuote($rohit);
    $lost = qtQuote($rohit);
    $open = qtQuote($sagar);
    $s = app(FollowUpScheduler::class);

    // 3 Nov (Day 1): Rohit logs both on time; Sagar misses his.
    Carbon::setTestNow(Carbon::parse('2026-11-03 12:00', 'Asia/Kolkata'));
    $s->log($won->followUps()->where('step', 1)->first(), 'coming_on', $rohit, '2026-11-04');
    $s->log($lost->followUps()->where('step', 1)->first(), 'bought_elsewhere', $rohit);
    app(QuotationService::class)->autoWin(10, null, $won->id);

    Carbon::setTestNow(Carbon::parse('2026-11-05 10:00', 'Asia/Kolkata'));
    $r = app(LeakReport::class)->forMonth();

    expect($r['quotes'])->toBe(3)
        ->and($r['won'])->toBe(1)
        ->and($r['converted_pct'])->toBe(33)
        ->and($r['won_paise'])->toBe(499900)
        ->and($r['lost_paise'])->toBe(499900)
        ->and($r['followups_due'])->toBe(3)          // three Day-1s due on 3 Nov
        ->and($r['followups_on_time'])->toBe(2)
        ->and($r['followups_on_time_pct'])->toBe(67)
        ->and($r['overdue_now'])->toBe(1)
        ->and($r['lost_reasons'][0])->toMatchArray(['reason' => 'bought_elsewhere', 'count' => 1]);

    $byName = collect($r['staff'])->keyBy('name');
    expect($byName['Rohit Shinde'])->toMatchArray(['quotes' => 2, 'followups_done' => 2, 'overdue' => 0, 'conversion_pct' => 50])
        ->and($byName['Sagar More'])->toMatchArray(['quotes' => 1, 'followups_done' => 0, 'overdue' => 1, 'conversion_pct' => 0]);
});

it('counts a revised quote once, as its latest version', function () {
    $q = qtQuote(staff('admin'));
    $v2 = app(QuotationService::class)->create(['customer_id' => $q->customer_id, 'revised_from_id' => $q->id, 'items' => [['product_id' => $q->items[0]->product_id, 'qty' => 1, 'unit_price_paise' => 450000]]], staff('admin'));

    expect($v2->version)->toBe(2)
        ->and($q->fresh()->revised_to_id)->toBe($v2->id)
        ->and($q->followUps()->pending()->count())->toBe(0)
        ->and(app(LeakReport::class)->forMonth()['quotes'])->toBe(1);
});
