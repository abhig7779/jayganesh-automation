<?php

use App\Models\Branch;
use App\Models\CashClose;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Setting;
use App\Services\Finance\CashCloseService;
use App\Support\Dashboard\FinanceAlerts;
use App\Support\Dashboard\FinanceHome;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BillingRows;
use Tests\Support\FinanceFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 18:00', 'Asia/Kolkata'));
});

it('works out expected cash as float + cash payments − cash expenses', function () {
    FinanceFixture::build($this->branch->id);

    // ₹2,000 float + ₹6,399 cash on bill A (the cash on cancelled bill D and yesterday's bills don't count)
    // − ₹300 tea paid in cash (the UPI transport and the cancelled ₹999 don't count).
    expect(app(CashCloseService::class)->expected('2026-11-05', $this->branch->id))->toBe([
        'opening_float_paise' => 200000,
        'cash_in_paise' => 639900,
        'cash_out_paise' => 30000,
        'expected_cash_paise' => 809900,
    ]);

    Setting::put('finance.opening_float_paise', 350000);
    expect(app(CashCloseService::class)->expected('2026-11-05', $this->branch->id)['expected_cash_paise'])->toBe(959900);
});

it('counts a payment at 00:30 IST on the right shop day', function () {

    BillingRows::payment('cash', 10000, '2026-11-05 00:30');   // 4 Nov 19:00 UTC
    BillingRows::payment('cash', 20000, '2026-11-04 23:50');

    $s = app(CashCloseService::class);
    expect($s->expected('2026-11-05')['cash_in_paise'])->toBe(10000)
        ->and($s->expected('2026-11-04')['cash_in_paise'])->toBe(20000);
});

it('saves a matching close without a note', function () {
    FinanceFixture::build($this->branch->id);
    $u = staff('finance');

    $this->actingAs($u)->post(route('finance.cash-close.store'), ['date' => '2026-11-05', 'counted' => '8,099'])
        ->assertRedirect(route('finance.cash-close', ['date' => '2026-11-05']));

    $c = CashClose::query()->sole();
    expect($c->expected_cash_paise)->toBe(809900)
        ->and($c->counted_cash_paise)->toBe(809900)
        ->and($c->difference_paise)->toBe(0)
        ->and($c->note)->toBeNull()
        ->and($c->closed_by)->toBe($u->id)
        ->and($c->summary['sales_paise'])->toBe(1189900);
});

it('needs a note when the cash is short or over', function () {
    FinanceFixture::build($this->branch->id);
    $u = staff('finance');

    $this->actingAs($u)->post(route('finance.cash-close.store'), ['date' => '2026-11-05', 'counted' => '7899'])
        ->assertSessionHasErrors(['note' => 'The cash is short by ₹200. Write a short note saying why.']);
    expect(CashClose::count())->toBe(0);

    $this->actingAs($u)->post(route('finance.cash-close.store'), ['date' => '2026-11-05', 'counted' => '7899', 'note' => 'Gave ₹200 for petrol'])
        ->assertRedirect();
    $c = CashClose::query()->sole();
    expect($c->difference_paise)->toBe(-20000)->and($c->note)->toBe('Gave ₹200 for petrol');

    // Redo the same day: still one row, now over by ₹50.50.
    $this->actingAs($u)->post(route('finance.cash-close.store'), ['date' => '2026-11-05', 'counted' => '8149.50', 'note' => 'Extra change'])
        ->assertRedirect();
    expect(CashClose::count())->toBe(1)->and(CashClose::first()->difference_paise)->toBe(5050);
});

it('builds the WhatsApp day-close summary for the owner', function () {
    FinanceFixture::build($this->branch->id);
    $u = staff('finance');
    $close = app(CashCloseService::class)->close($u, '2026-11-05', 789900, 'Short ₹200, petrol', $this->branch->id);
    $svc = app(CashCloseService::class);

    expect($svc->whatsappText($close))->toBe(
        "Day close 5 Nov: Sales ₹11,899 (2 bills) · Cash ₹6,399 · UPI ₹5,000 · Card ₹500 · Dues collected ₹500 · Expenses ₹750 · Cash difference −₹200\nNote: Short ₹200, petrol"
    );
    expect($svc->whatsappLink($close))->toStartWith('https://wa.me/919823107676?text=Day%20close%205%20Nov');

    Setting::put('finance.owner_whatsapp', '99813 97676');
    expect($svc->whatsappLink($close))->toStartWith('https://wa.me/919981397676?text=');

    $this->actingAs($u)->get(route('finance.cash-close', ['date' => '2026-11-05']))
        ->assertOk()->assertSee('Send to owner on WhatsApp')->assertSee('Cash difference −₹200');
});

it('closes a day with no bills, counting only float and cash expenses', function () {
    $u = staff('finance');
    Expense::create(['branch_id' => $this->branch->id, 'date' => '2026-11-05', 'expense_category_id' => ExpenseCategory::first()->id,
        'amount_paise' => 15000, 'mode' => 'cash', 'created_by' => $u->id]);

    $this->actingAs($u)->get(route('finance.cash-close'))->assertOk()->assertSee('₹1,850');
    $this->actingAs($u)->post(route('finance.cash-close.store'), ['date' => '2026-11-05', 'counted' => '1850'])->assertRedirect();
    expect(CashClose::first()->difference_paise)->toBe(0);
    expect(app(CashCloseService::class)->whatsappText(CashClose::first()))
        ->toBe('Day close 5 Nov: Sales ₹0 (0 bills) · Cash ₹0 · UPI ₹0 · Card ₹0 · Dues collected ₹0 · Expenses ₹150 · Cash difference ₹0');
});

it('refuses future dates and lets only finance and owner save', function () {
    $this->actingAs(staff('finance'))->post(route('finance.cash-close.store'), ['date' => '2026-11-06', 'counted' => '2000'])
        ->assertSessionHasErrors('date');
    $this->actingAs(staff('admin'))->post(route('finance.cash-close.store'), ['date' => '2026-11-05', 'counted' => '2000'])
        ->assertForbidden();
    $this->actingAs(staff('admin'))->get(route('finance.cash-close'))->assertOk()->assertDontSee('Save cash close');
    $this->actingAs(staff('owner'))->post(route('finance.cash-close.store'), ['date' => '2026-11-05', 'counted' => '2000'])
        ->assertRedirect();
    expect(CashClose::count())->toBe(1);
});

it('flags a missing close for yesterday only when the shop was open', function () {
    expect(FinanceAlerts::missingCashClose())->toBeNull(); // nothing happened yesterday

    FinanceFixture::build($this->branch->id); // bill C was made yesterday
    $alert = FinanceAlerts::missingCashClose($this->branch->id);
    expect($alert['date'])->toBe('2026-11-04')
        ->and($alert['label'])->toBe('Cash close not done for Wed, 4 Nov')
        ->and($alert['url'])->toBe(route('finance.cash-close', ['date' => '2026-11-04']));

    app(CashCloseService::class)->close(staff('finance'), '2026-11-04', 200000, null, $this->branch->id);
    expect(FinanceAlerts::missingCashClose($this->branch->id))->toBeNull();
});

it('flags a missing close from expenses alone when there are no bills', function () {
    Expense::create(['branch_id' => $this->branch->id, 'date' => '2026-11-04', 'expense_category_id' => ExpenseCategory::first()->id,
        'amount_paise' => 15000, 'mode' => 'cash']);
    expect(FinanceAlerts::missingCashClose()['date'])->toBe('2026-11-04');
    $this->actingAs(staff('finance'))->get(route('finance.index'))->assertSee('Cash close not done for Wed, 4 Nov');
});

it('gives the finance home its numbers', function () {
    FinanceFixture::build($this->branch->id);
    $h = FinanceHome::today($this->branch->id);

    expect($h['collections'])->toBe(['cash' => 639900, 'upi' => 500000, 'card' => 50000, 'bank' => 0, 'total' => 1189900])
        ->and($h['dues']['buckets'])->toBe(['0_15' => 50000, '16_30' => 0, '31_60' => 150000, '60_plus' => 0])
        ->and($h['dues']['total_paise'])->toBe(200000)
        ->and($h['dues']['customers'])->toBe(2)
        ->and($h['expenses'])->toBe(['total_paise' => 75000, 'cash_paise' => 30000, 'count' => 2])
        ->and($h['expected_cash_paise'])->toBe(809900)
        ->and($h['cash_closed'])->toBeFalse()
        ->and($h['missing_close']['date'])->toBe('2026-11-04');
});
