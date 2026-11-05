<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\MessageLog;
use App\Models\Product;
use App\Models\ReminderRule;
use App\Models\ServiceReminder;
use App\Models\Setting;
use App\Services\Billing\InvoiceService;
use App\Services\Reminders\ReminderService;
use App\Services\WhatsApp\CloudApiClient;
use App\Services\WhatsApp\WhatsApp;
use App\Support\Dashboard\Reminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

require_once __DIR__.'/Billing/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    billingSetup($this);
    Setting::put('reviews.google_link', 'https://g.page/r/test-review');
    $this->owner = staff('owner');
    $this->ceramic = billProduct(['name' => 'Ceramic coating 9H (full car)', 'type' => 'service', 'track_stock' => false, 'price_paise' => 1499900], 0);
});

afterEach(fn () => Carbon::setTestNow());

function paidBill($by, array $items, ?Customer $c = null)
{
    $c ??= billCustomer();

    return makeBill($by, $items, ['customer_id' => $c->id, 'customer_car_id' => $c->cars->first()?->id, 'payments' => payFull($items)]);
}

// ------------------------------------------------------------------ rules

it('matches rules by name words, category and product, and keeps DIY products out of service-only rules', function () {
    $rule = ReminderRule::query()->where('name', 'Ceramic coating check-up')->firstOrFail();
    $diy = billProduct(['name' => '9H ceramic coating kit DIY (30 ml)']);
    $washer = billProduct(['name' => 'Dishwasher tablets']);
    $wash = ReminderRule::query()->where('name', 'Wash / detailing')->firstOrFail();

    $line = fn (Product $p) => (new InvoiceItem)->forceFill(['name' => $p->name, 'product_id' => $p->id]);

    expect($rule->matches($line($this->ceramic)->setRelation('product', $this->ceramic)))->toBeTrue()
        ->and($rule->matches($line($diy)->setRelation('product', $diy)))->toBeFalse()
        ->and($wash->nameMatches('Dishwasher tablets'))->toBeFalse()
        ->and($wash->nameMatches('Foam wash + vacuum'))->toBeTrue();

    $cat = Category::create(['name' => 'Wipers']);
    $wiper = billProduct(['name' => 'Bosch Aerotwin 24"', 'category_id' => $cat->id]);
    $byCategory = ReminderRule::create(['name' => 'Wipers', 'match_type' => 'category', 'category_id' => $cat->id, 'offsets_days' => [365], 'message' => 'x']);
    $byProduct = ReminderRule::create(['name' => 'One', 'match_type' => 'product', 'product_id' => $wiper->id, 'offsets_days' => [365], 'message' => 'x']);

    expect($byCategory->matches($line($wiper)->setRelation('product', $wiper->load('category'))))->toBeTrue()
        ->and($byProduct->matches($line($wiper)->setRelation('product', $wiper)))->toBeTrue()
        ->and($byProduct->matches($line($washer)->setRelation('product', $washer)))->toBeFalse();
});

it('seeds the default rules with their days', function () {
    expect(ReminderRule::query()->pluck('offsets_days', 'name')->all())->toMatchArray([
        'Ceramic coating check-up' => [180, 365],
        'PPF inspection' => [365],
        'Wash / detailing' => [30],
        'Wiper blades' => [365],
    ]);
});

// ------------------------------------------------------------------ listener

it('creates check-ups from a saved bill with the right due dates, once', function () {
    Carbon::setTestNow('2026-11-05 20:00:00'); // 01:30 on 6 Nov in Pune: the bill date is 6 Nov
    $bill = paidBill(staff('sales'), [[$this->ceramic], [billProduct(['name' => 'Car perfume'])]]);

    $service = ServiceReminder::query()->where('type', 'service')->orderBy('due_on')->get();
    expect($service)->toHaveCount(2)
        ->and($service->pluck('due_on')->map->toDateString()->all())->toBe(['2027-05-05', '2027-11-06'])
        ->and($service->first()->service_on->toDateString())->toBe('2026-11-06')
        ->and($service->first()->status)->toBe('pending')
        ->and($service->first()->invoice_item_id)->toBe($bill->items->first()->id)
        ->and($service->first()->customer_car_id)->toBe($bill->customer_car_id);

    // The event can fire again (bill edited): nothing doubles.
    app(ReminderService::class)->forInvoice($bill->fresh());
    expect(ServiceReminder::query()->where('type', 'service')->count())->toBe(2)
        ->and(ServiceReminder::query()->where('type', 'review')->count())->toBe(1);
});

it('replaces the older check-up when the car comes back, and restores it if the new bill is cancelled', function () {
    $wash = billProduct(['name' => 'Full car detailing', 'type' => 'service', 'track_stock' => false], 0);
    $c = billCustomer();
    Carbon::setTestNow('2026-10-01 10:00');
    paidBill($this->owner, [[$wash]], $c);
    Carbon::setTestNow('2026-10-20 10:00');
    $second = paidBill($this->owner, [[$wash]], $c);

    expect(ServiceReminder::query()->where('type', 'service')->where('status', 'skipped')->count())->toBe(1)
        ->and(ServiceReminder::query()->where('type', 'service')->whereIn('status', ['pending', 'due'])->sole()->due_on->toDateString())->toBe('2026-11-19');

    app(InvoiceService::class)->cancel($second, 'Entered twice', $this->owner);
    expect(ServiceReminder::query()->where('type', 'service')->where('status', 'pending')->sole()->due_on->toDateString())->toBe('2026-10-31');
});

it('cancels the reminders of a cancelled bill', function () {
    $bill = paidBill($this->owner, [[$this->ceramic]]);
    expect(ServiceReminder::query()->where('invoice_id', $bill->id)->count())->toBe(3);

    app(InvoiceService::class)->cancel($bill, 'Wrong customer', $this->owner);

    expect(ServiceReminder::query()->where('invoice_id', $bill->id)->pluck('status')->unique()->all())->toBe(['cancelled']);
});

// ------------------------------------------------------------------ reviews

it('asks for a review the day after a paid bill, at most once per customer in 90 days', function () {
    $c = billCustomer();
    $p = billProduct(['name' => 'Car perfume']);
    Carbon::setTestNow('2026-11-06 11:00');
    paidBill($this->owner, [[$p]], $c);
    $review = ServiceReminder::query()->where('type', 'review')->sole();
    expect($review->due_on->toDateString())->toBe('2026-11-07');

    Carbon::setTestNow('2027-01-20 11:00'); // 75 days later: still capped
    paidBill($this->owner, [[$p]], $c);
    expect(ServiceReminder::query()->where('type', 'review')->count())->toBe(1);

    Carbon::setTestNow('2027-02-10 11:00'); // 96 days later: asks again
    paidBill($this->owner, [[$p]], $c);
    expect(ServiceReminder::query()->where('type', 'review')->count())->toBe(2);

    // A bill on credit gets no review request; nor does anyone when reviews are switched off.
    $other = billCustomer('9823107788', 'Sneha Kulkarni');
    makeBill($this->owner, [[$p]], ['customer_id' => $other->id, 'payments' => []]);
    Setting::put('reviews.enabled', false);
    paidBill($this->owner, [[$p]], billCustomer('9823011245', 'Amit Jadhav'));
    expect(ServiceReminder::query()->where('type', 'review')->count())->toBe(2);
});

it('writes a polite English review message with the link, as a wa.me link', function () {
    Carbon::setTestNow('2026-11-06 11:00');
    paidBill($this->owner, [[billProduct(['name' => 'Car perfume'])]]);
    $r = ServiceReminder::query()->where('type', 'review')->sole();

    $text = app(ReminderService::class)->message($r);
    expect($text)->toStartWith('Hello Rahul Pawar, thank you for choosing Jay Ganesh Car Accessories for your Hyundai Creta.')
        ->toContain('https://g.page/r/test-review')
        ->not->toContain('{');

    expect(app(ReminderService::class)->manualLink($r))
        ->toStartWith('https://wa.me/919823104512?text=Hello%20Rahul%20Pawar')
        ->toContain(rawurlencode('https://g.page/r/test-review'));
});

it('fills the check-up message from the rule', function () {
    Carbon::setTestNow('2026-05-10 11:00');
    paidBill($this->owner, [[$this->ceramic]]);
    $r = ServiceReminder::query()->where('type', 'service')->orderBy('due_on')->first();

    expect(app(ReminderService::class)->message($r))
        ->toContain('Hello Rahul Pawar')
        ->toContain('Ceramic coating 9H (full car) on your Hyundai Creta (10 May 2026)');
});

// ------------------------------------------------------------------ daily command

it('marks reminders due on their day with reminders:due', function () {
    Carbon::setTestNow('2026-10-01 10:00');
    $wash = billProduct(['name' => 'Foam wash', 'type' => 'service', 'track_stock' => false], 0);
    paidBill($this->owner, [[$wash]]);
    $r = ServiceReminder::query()->where('type', 'service')->sole();

    Carbon::setTestNow('2026-10-30 10:00');
    $this->artisan('reminders:due')->expectsOutputToContain('1 reminder(s) marked due')->assertSuccessful(); // the review (2 Oct)
    expect($r->fresh()->status)->toBe('pending');

    Carbon::setTestNow('2026-10-31 02:00'); // 07:30 in Pune
    $this->artisan('reminders:due')->assertSuccessful();
    expect($r->fresh()->status)->toBe('due')
        ->and(Reminders::countForUser($this->owner))->toBe(2);
});

// ------------------------------------------------------------------ screen + permissions

it('lets owner, admin and marketing work the list, and keeps sales and technicians out', function () {
    Carbon::setTestNow('2026-11-06 11:00');
    paidBill($this->owner, [[billProduct(['name' => 'Car perfume'])]]);
    Carbon::setTestNow('2026-11-08 11:00');

    foreach (['owner', 'admin', 'marketing'] as $role) {
        $u = staff($role);
        $this->actingAs($u)->get(route('reminders.index'))->assertOk()
            ->assertSee('Reminders today')->assertSee('Rahul Pawar')->assertSee('Google review request')
            ->assertSee('1 day late')->assertSee('Mark done');
        expect(Reminders::countForUser($u))->toBe(1);
    }

    $r = ServiceReminder::query()->sole();
    foreach (['sales', 'technician', 'finance'] as $role) {
        $u = staff($role);
        $this->actingAs($u)->get(route('reminders.index'))->assertForbidden();
        $this->actingAs($u)->post(route('reminders.done', $r))->assertForbidden();
        expect(Reminders::countForUser($u))->toBe(0);
    }

    // Rules are the owner's.
    $this->actingAs(staff('admin'))->get(route('reminders.rules.index'))->assertForbidden();
    $this->actingAs($this->owner)->get(route('reminders.rules.index'))->assertOk()->assertSee('Ceramic coating check-up');
});

it('opens WhatsApp, marks done and skips from the list', function () {
    Carbon::setTestNow('2026-11-06 11:00');
    paidBill($this->owner, [[billProduct(['name' => 'Car perfume'])]]);
    Carbon::setTestNow('2026-11-07 11:00');
    $r = ServiceReminder::query()->sole();
    $admin = staff('admin');

    $this->actingAs($admin)->get(route('reminders.whatsapp', $r))
        ->assertRedirect(app(ReminderService::class)->manualLink($r));
    expect($r->fresh())->status->toBe('sent')->channel->toBe('whatsapp')->sent_by->toBe($admin->id);

    $this->actingAs($admin)->get(route('reminders.index', ['show' => 'sent']))->assertOk()->assertSee('Sent 7 Nov');
    $this->actingAs($admin)->post(route('reminders.done', $r))->assertRedirect();
    expect($r->fresh())->status->toBe('done')->closed_by->toBe($admin->id);

    $wash = billProduct(['name' => 'Foam wash', 'type' => 'service', 'track_stock' => false], 0);
    paidBill($this->owner, [[$wash]], billCustomer('9823107788', 'Sneha Kulkarni'));
    $s = ServiceReminder::query()->where('type', 'service')->sole();
    $this->actingAs($admin)->post(route('reminders.skip', $s), ['note' => 'Sold the car'])->assertRedirect();
    expect($s->fresh())->status->toBe('skipped')->note->toBe('Sold the car');
});

it('sends the approved template automatically in Cloud API mode', function () {
    Carbon::setTestNow('2026-11-06 11:00');
    paidBill($this->owner, [[billProduct(['name' => 'Car perfume'])]]); // e-bill stays manual
    $r = ServiceReminder::query()->sole();

    Setting::put('whatsapp.mode', 'cloud_api');
    $client = Mockery::mock(CloudApiClient::class);
    $client->shouldReceive('isConfigured')->andReturn(true);
    $client->shouldReceive('sendMessage')->once()->withArgs(function (array $payload) {
        return $payload['template']['name'] === 'review_request'
            && $payload['template']['components'][0]['parameters'][1]['text'] === 'https://g.page/r/test-review';
    })->andReturn('wamid.TEST');
    app()->instance(CloudApiClient::class, $client);
    app()->forgetInstance(WhatsApp::class);

    $this->actingAs($this->owner)->get(route('reminders.index'))->assertOk();
    $this->actingAs($this->owner)->post(route('reminders.send', $r))->assertRedirect()->assertSessionHas('status');

    expect($r->fresh())->status->toBe('sent')->channel->toBe('whatsapp_auto')
        ->and(MessageLog::query()->where('template', 'review_request')->sole()->wa_message_id)->toBe('wamid.TEST');
});

it('lets the owner add a rule that new bills then use', function () {
    $this->actingAs($this->owner)->post(route('reminders.rules.store'), [
        'name' => 'Sun film check', 'match_type' => 'name', 'keywords' => 'sun film', 'offsets' => '90, 30',
        'message' => 'Hello {name}, time to check the film on your {car}.', 'is_active' => '1',
    ])->assertRedirect(route('reminders.rules.index'));

    $rule = ReminderRule::query()->where('name', 'Sun film check')->sole();
    expect($rule->offsets())->toBe([30, 90]);

    paidBill($this->owner, [[billProduct(['name' => 'Garware sun film (full car)'])]]);
    expect(ServiceReminder::query()->where('reminder_rule_id', $rule->id)->count())->toBe(2);

    $this->actingAs($this->owner)->post(route('reminders.rules.store'), ['name' => 'Bad', 'match_type' => 'name', 'offsets' => 'soon', 'message' => 'x'])
        ->assertSessionHasErrors(['keywords', 'offsets']);
});
