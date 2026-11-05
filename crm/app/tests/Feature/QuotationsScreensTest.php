<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quotation;
use App\Services\Customers\CustomerService;
use App\Services\Quotations\FollowUpScheduler;
use App\Services\Quotations\LeadService;
use App\Services\Quotations\QuotationService;
use App\Support\Dashboard\FollowUps;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->seed(SettingsSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-11-05 10:00', 'Asia/Kolkata'));
    $this->product = tap((new Product)->forceFill(['name' => 'Ceramic coating 9H', 'price_paise' => 1499900, 'mrp_paise' => 1800000, 'gst_rate' => 18, 'type' => 'service', 'sold_count' => 5]), fn ($p) => $p->save());
});

afterEach(fn () => Carbon::setTestNow());

function scrQuote($by, string $mobile = '9823107676', string $name = 'Vikram Shinde'): Quotation
{
    $c = app(CustomerService::class)->findOrCreateByMobile($mobile, ['name' => $name], $by);

    return app(QuotationService::class)->create(['customer_id' => $c->id, 'items' => [['product_id' => Product::first()->id, 'qty' => 1]]], $by);
}

// ---------------------------------------------------------------- permissions per role

it('keeps technicians and finance out of quotations, follow-ups and leads', function (string $role) {
    $u = staff($role);
    $q = scrQuote(staff('sales'));
    $this->actingAs($u);
    $this->get(route('quotations.index'))->assertForbidden();
    $this->get(route('quotations.create'))->assertForbidden();
    $this->get(route('quotations.show', $q))->assertForbidden();
    $this->get(route('followups.index'))->assertForbidden();
    $this->get(route('leads.index'))->assertForbidden();
    $this->post(route('followups.log', $q->followUps()->first()), ['outcome' => 'no_answer'])->assertForbidden();
    expect(FollowUps::countForUser($u))->toBe(0)->and(FollowUps::rowsForUser($u))->toBe([]);
})->with(['technician', 'finance']);

it('shows Sales only their own quotations and follow-ups; Owner/Admin see all', function () {
    $rohit = staff('sales');
    $sagar = staff('sales');
    $mine = scrQuote($rohit);
    $his = scrQuote($sagar, '9823104512', 'Rahul Pawar');
    Carbon::setTestNow(Carbon::parse('2026-11-06 10:00', 'Asia/Kolkata')); // Day 1 due for both

    $this->actingAs($rohit)->get(route('quotations.index'))->assertOk()->assertSee($mine->number)->assertDontSee($his->number);
    $this->actingAs($rohit)->get(route('quotations.show', $his))->assertForbidden();
    $this->actingAs($rohit)->post(route('followups.log', $his->followUps()->first()), ['outcome' => 'no_answer'])->assertForbidden();
    expect(collect(FollowUps::rowsForUser($rohit))->pluck('subject_id')->all())->toBe([$mine->id])
        ->and(FollowUps::countForUser($rohit))->toBe(1);

    foreach (['owner', 'admin'] as $role) {
        $boss = staff($role);
        $this->actingAs($boss)->get(route('quotations.index'))->assertOk()->assertSee($mine->number)->assertSee($his->number);
        expect(FollowUps::countForUser($boss))->toBe(2);
    }
});

it('gives Sales no lead rows and no leads page; a marketing user gets both', function () {
    $sales = staff('sales');
    $marketing = staff('marketing');
    $lead = app(LeadService::class)->create(['mobile' => '9823011245', 'interest' => 'PPF for Creta', 'source' => 'whatsapp_ad'], $marketing);
    // Even a lead wrongly assigned to the counter person stays hidden from them.
    $stray = app(LeadService::class)->create(['mobile' => '9823011246', 'interest' => 'Mats', 'assigned_to' => $sales->id], $marketing);

    $this->actingAs($sales)->get(route('leads.index'))->assertForbidden();
    $this->actingAs($sales)->post(route('leads.store'), ['mobile' => '9823011247', 'interest' => 'x', 'source' => 'website'])->assertForbidden();
    $this->actingAs($sales)->post(route('quotations.asked-price'), ['mobile' => '9823011247', 'what' => 'LED'])->assertForbidden();
    $this->actingAs($sales)->get(route('quotations.index'))->assertOk()->assertDontSee('Asked price');
    expect(collect(FollowUps::rowsForUser($sales))->where('type', 'lead')->count())->toBe(0)
        ->and(FollowUps::countForUser($sales))->toBe(0);
    $this->actingAs($sales)->post(route('followups.log', $stray->followUps()->first()), ['outcome' => 'no_answer'])->assertForbidden();

    $this->actingAs($marketing)->get(route('leads.index'))->assertOk()->assertSee('PPF for Creta');
    $this->actingAs($marketing)->get(route('followups.index'))->assertOk()->assertSee('PPF for Creta');
    expect(collect(FollowUps::rowsForUser($marketing))->pluck('subject_id')->all())->toContain($lead->id);
    $this->actingAs($marketing)->post(route('followups.log', $lead->followUps()->orderBy('due_on')->first()), ['outcome' => 'no_answer'], ['HX-Request' => 'true'])->assertOk();

    // The owner can give leads to one counter person directly.
    $sales->givePermissionTo('leads.manage');
    $this->actingAs($sales->fresh())->get(route('leads.index'))->assertOk();
});

it('lets a marketing user log an "asked price" from the quotations page', function () {
    $m = staff('marketing');
    $this->actingAs($m)->post(route('quotations.asked-price'), ['mobile' => '98230 11245', 'product_id' => $this->product->id])
        ->assertRedirect()->assertSessionHas('status');
    $lead = Lead::first();
    expect($lead->type)->toBe(Lead::TYPE_PRICE)
        ->and($lead->price_told_paise)->toBe(1499900)
        ->and($lead->mobile)->toBe('+919823011245')
        ->and($lead->followUps()->count())->toBe(5);
});

// ---------------------------------------------------------------- screens render

it('renders every quotations screen for the Owner, with the leak report', function () {
    $owner = staff('owner');
    $q = scrQuote(staff('sales'));
    app(LeadService::class)->create(['mobile' => '9823011245', 'interest' => 'PPF for Creta'], $owner);

    $this->actingAs($owner);
    $this->get(route('quotations.index'))->assertOk()->assertSee('Quote follow-up report')->assertSee($q->number);
    $this->get(route('quotations.index', ['status' => 'open', 'filter' => 'overdue']))->assertOk();
    $this->get(route('quotations.create'))->assertOk()->assertSee('Save &amp; send on WhatsApp', false);
    $this->get(route('quotations.create', ['from' => $q->id]))->assertOk()->assertSee('Revise');
    $this->get(route('quotations.create', ['lead' => Lead::first()->id]))->assertOk();
    $this->get(route('quotations.show', $q))->assertOk()->assertSee('Convert to bill')->assertSee('You save ₹3,001', false);
    $this->get(route('followups.index'))->assertOk();
    $this->get(route('leads.index'))->assertOk();
    $this->get(route('quotations.pdf', $q))->assertOk();
    $this->get(route('quotations.product-index'))->assertOk()->assertJsonPath('products.0.name', 'Ceramic coating 9H');
});

it('hides the leak report from Sales', function () {
    $this->actingAs(staff('sales'))->get(route('quotations.index'))->assertOk()->assertDontSee('Quote follow-up report');
});

it('saves a quotation from the builder (JSON) and returns the WhatsApp link', function () {
    $sales = staff('sales');
    $res = $this->actingAs($sales)->postJson(route('quotations.store'), [
        'mobile' => '98231 07676', 'name' => 'Vikram Shinde', 'car' => ['make' => 'Kia', 'model' => 'Seltos', 'reg_no' => 'MH12 TR 9021'],
        'items' => [['product_id' => $this->product->id, 'qty' => 1]], 'valid_days' => 7, 'send' => 1,
    ])->assertCreated();

    $q = Quotation::first();
    expect($res->json('whatsapp_url'))->toStartWith('https://wa.me/919823107676?text=')
        ->and($q->sent_at)->not->toBeNull()
        ->and($q->assigned_to)->toBe($sales->id)
        ->and($q->car->model)->toBe('Seltos')
        ->and(Customer::first()->name)->toBe('Vikram Shinde');

    $this->actingAs($sales)->postJson(route('quotations.store'), ['items' => []])->assertUnprocessable()->assertJsonValidationErrors(['mobile', 'items']);
});

it('opens WhatsApp for a follow-up, records the contact and accepts an edited text', function () {
    $sales = staff('sales');
    $f = scrQuote($sales)->followUps()->orderBy('due_on')->first();
    $this->actingAs($sales)->get(route('followups.whatsapp', $f))->assertRedirectContains('https://wa.me/919823107676?text=Hello%20Vikram');
    expect($f->fresh()->contacted_at)->not->toBeNull()->and($f->fresh()->channel)->toBe('whatsapp');

    $this->actingAs($sales)->get(route('followups.whatsapp', ['followUp' => $f, 'text' => 'Custom hi']))->assertRedirect('https://wa.me/919823107676?text=Custom%20hi');
});

// ---------------------------------------------------------------- log result (htmx)

it('logs a result over htmx and answers with the trigger for the row and the Undo toast', function () {
    $sales = staff('sales');
    $q = scrQuote($sales);
    $f = $q->followUps()->orderBy('due_on')->first();

    $res = $this->actingAs($sales)->post(route('followups.log', $f), ['outcome' => 'coming_on', 'date' => '2026-11-08', 'note' => 'After office'], ['HX-Request' => 'true'])
        ->assertOk();
    $trigger = json_decode($res->headers->get('HX-Trigger'), true)['followup-logged'];
    expect($trigger['id'])->toBe($f->id)
        ->and($trigger['message'])->toBe('Visit booked: Vikram on Sun 8 Nov')
        ->and($trigger['undo_url'])->toBe(route('followups.undo', $f))
        ->and($q->fresh()->status)->toBe(Quotation::VISIT_BOOKED)
        ->and($f->fresh()->done_by)->toBe($sales->id);

    // Undo puts it back.
    $this->actingAs($sales)->post(route('followups.undo', $f), [], ['HX-Request' => 'true'])->assertOk()->assertHeader('HX-Trigger');
    expect($f->fresh()->status)->toBe(FollowUp::PENDING)->and($q->fresh()->status)->toBe(Quotation::OPEN);
});

it('rejects a log without an outcome, or a dated outcome without a date', function () {
    $sales = staff('sales');
    $f = scrQuote($sales)->followUps()->first();
    $this->actingAs($sales)->postJson(route('followups.log', $f), [])->assertUnprocessable()->assertJsonValidationErrors('outcome');
    $this->actingAs($sales)->postJson(route('followups.log', $f), ['outcome' => 'call_later'])->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->actingAs($sales)->postJson(route('followups.log', $f), ['outcome' => 'teleported'])->assertUnprocessable();
});

it('refuses to log the same follow-up twice', function () {
    $sales = staff('sales');
    $f = scrQuote($sales)->followUps()->first();
    $this->actingAs($sales)->postJson(route('followups.log', $f), ['outcome' => 'no_answer'])->assertOk();
    $this->actingAs($sales)->postJson(route('followups.log', $f), ['outcome' => 'no_answer'])->assertUnprocessable();
});

it('shows one row per customer even when a retry and a planned step are both due', function () {
    $sales = staff('sales');
    $q = scrQuote($sales);
    Carbon::setTestNow(Carbon::parse('2026-11-06 10:00', 'Asia/Kolkata'));
    app(FollowUpScheduler::class)->log($q->followUps()->where('step', 1)->first(), 'no_answer', $sales);
    Carbon::setTestNow(Carbon::parse('2026-11-09 10:00', 'Asia/Kolkata')); // retry (7th) and Day 3 (8th) both overdue

    $rows = FollowUps::rowsForUser($sales);
    expect($rows)->toHaveCount(1)->and($rows[0]['overdue'])->toBeTrue()->and($rows[0]['due_label'])->toBe('2 days late');
});

it('lets the Owner reassign a quotation and its pending follow-ups; Sales cannot', function () {
    $owner = staff('owner');
    $rohit = staff('sales');
    $sagar = staff('sales');
    $q = scrQuote($rohit);

    $this->actingAs($rohit)->post(route('quotations.reassign', $q), ['user_id' => $sagar->id])->assertForbidden();
    $this->actingAs($owner)->post(route('quotations.reassign', $q), ['user_id' => $sagar->id])->assertRedirect();
    expect($q->fresh()->assigned_to)->toBe($sagar->id)
        ->and($q->followUps()->pending()->pluck('assigned_to')->unique()->all())->toBe([$sagar->id]);
});

it('only exposes the dev login route in local', function () {
    expect(Route::has('dev.login-as'))->toBeFalse();
});
