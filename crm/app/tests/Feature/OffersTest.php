<?php

use App\Jobs\SendOfferMessage;
use App\Models\AudienceExport;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\Offer;
use App\Models\Segment;
use App\Models\Setting;
use App\Services\Offers\AudienceCsv;
use App\Services\Offers\OfferService;
use App\Services\Offers\SegmentBuilder;
use App\Services\Offers\WhatsAppBridge;
use App\Support\Dashboard\Offers as OffersTile;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/** Stands in for App\Services\WhatsApp\WhatsApp (built on the whatsapp branch). */
class FakeOfferWhatsApp
{
    public static bool $automatic = true;

    public static array $sent = [];

    public function isAutomatic(): bool
    {
        return self::$automatic;
    }

    public function marketingTemplates(): array
    {
        return ['diwali_offer' => 'diwali_offer (Marketing, approved)'];
    }

    public function sendTemplate(string $to, string $template, array $params, ?string $doc = null, ?string $docName = null, $related = null): object
    {
        self::$sent[] = compact('to', 'template', 'params');

        return (object) ['id' => 1000 + count(self::$sent)];
    }
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->builder = app(SegmentBuilder::class);
    WhatsAppBridge::$class = 'App\\Services\\WhatsApp\\DoesNotExistInTests';
    FakeOfferWhatsApp::$automatic = true;
    FakeOfferWhatsApp::$sent = [];
});

afterEach(function () {
    WhatsAppBridge::$class = 'App\\Services\\WhatsApp\\WhatsApp';
});

/** A customer with exact, non-random attributes (and optionally one car). */
function offerCustomer(array $attrs = [], ?array $car = null): Customer
{
    $extra = array_intersect_key($attrs, array_flip(['last_visit_at', 'total_spent_paise', 'created_at', 'opted_out_at']));
    $c = Customer::factory()->create(array_merge([
        'area' => 'Hadapsar, Pune', 'source' => 'walk_in', 'tags' => null, 'whatsapp_opt_in' => true,
    ], array_diff_key($attrs, $extra)));
    if ($extra) {
        $c->forceFill($extra)->saveQuietly();
    }
    if ($car) {
        CustomerCar::factory()->create(array_merge(['customer_id' => $c->id], $car));
    }

    return $c->fresh();
}

function idsFor(array $filters): array
{
    return app(SegmentBuilder::class)->query(app(SegmentBuilder::class)->normalize($filters))->orderBy('id')->pluck('id')->all();
}

/** Test-only stand-in for the billing branch's tables. */
function makeBillingTables(): void
{
    if (! Schema::hasTable('invoices')) {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id');
            $t->string('status')->default('paid');
            $t->timestamps();
        });
    }
    if (! Schema::hasTable('invoice_items')) {
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id');
            $t->foreignId('product_id')->nullable();
            $t->timestamps();
        });
    }
}

function billFor(Customer $c, int $productId, string $status = 'paid'): void
{
    $inv = DB::table('invoices')->insertGetId(['customer_id' => $c->id, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('invoice_items')->insert(['invoice_id' => $inv, 'product_id' => $productId, 'created_at' => now(), 'updated_at' => now()]);
}

// ---------------------------------------------------------------- segment filters

it('filters by car make and by model', function () {
    $creta = offerCustomer([], ['make' => 'Hyundai', 'model' => 'Creta']);
    $venue = offerCustomer([], ['make' => 'Hyundai', 'model' => 'Venue']);
    $seltos = offerCustomer([], ['make' => 'Kia', 'model' => 'Seltos']);
    offerCustomer([], ['make' => 'Tata', 'model' => 'Nexon']);

    expect(idsFor(['makes' => ['Hyundai']]))->toBe([$creta->id, $venue->id])
        ->and(idsFor(['models' => ['Creta', 'Seltos']]))->toBe([$creta->id, $seltos->id])
        ->and(idsFor(['makes' => ['Hyundai'], 'models' => ['Venue']]))->toBe([$venue->id]);
});

it('filters by area, source and tags', function () {
    $a = offerCustomer(['area' => 'Wagholi, Pune', 'source' => 'instagram', 'tags' => ['ppf', 'vip']]);
    $b = offerCustomer(['area' => 'Hadapsar, Pune', 'source' => 'google', 'tags' => ['ceramic']]);
    $c = offerCustomer(['area' => 'Wagholi, Pune', 'source' => 'walk_in', 'tags' => ['ppf-old']]);

    expect(idsFor(['areas' => ['Wagholi, Pune']]))->toBe([$a->id, $c->id])
        ->and(idsFor(['sources' => ['instagram', 'google']]))->toBe([$a->id, $b->id])
        ->and(idsFor(['tags' => ['ppf']]))->toBe([$a->id])            // "ppf" must not match "ppf-old"
        ->and(idsFor(['tags' => ['ceramic', 'vip']]))->toBe([$a->id, $b->id])
        ->and(idsFor(['areas' => ['Wagholi, Pune'], 'sources' => ['walk_in']]))->toBe([$c->id]);
});

it('filters by last visit: before, after, not in N days and never', function () {
    $recent = offerCustomer(['last_visit_at' => now()->subDays(10)]);
    $old = offerCustomer(['last_visit_at' => now()->subDays(200)]);
    $never = offerCustomer(['last_visit_at' => null, 'created_at' => now()->subDays(300)]);
    $newNever = offerCustomer(['last_visit_at' => null, 'created_at' => now()->subDays(5)]);

    $cut = now('Asia/Kolkata')->subDays(30)->toDateString();
    expect(idsFor(['visit' => 'before', 'visit_date' => $cut]))->toBe([$old->id])
        ->and(idsFor(['visit' => 'after', 'visit_date' => $cut]))->toBe([$recent->id])
        // Not visited in 90 days includes old customers with no visit, but not people added last week.
        ->and(idsFor(['visit' => 'not_in_days', 'visit_days' => 90]))->toBe([$old->id, $never->id])
        ->and(idsFor(['visit' => 'never']))->toBe([$never->id, $newNever->id])
        // A mode without its value is ignored rather than matching nobody.
        ->and(idsFor(['visit' => 'before']))->toHaveCount(4);
});

it('filters by total spent and by date added', function () {
    $big = offerCustomer(['total_spent_paise' => 2_500_000, 'created_at' => now()->subDays(40)]);
    $small = offerCustomer(['total_spent_paise' => 99_900, 'created_at' => now()->subDays(5)]);
    $exact = offerCustomer(['total_spent_paise' => 1_000_000, 'created_at' => now()->subDays(100)]);

    expect(idsFor(['min_spent' => '10,000']))->toBe([$big->id, $exact->id])
        ->and(idsFor([
            'added_from' => now('Asia/Kolkata')->subDays(60)->toDateString(),
            'added_to' => now('Asia/Kolkata')->subDays(20)->toDateString(),
        ]))->toBe([$big->id])
        ->and(idsFor(['added_from' => now('Asia/Kolkata')->subDays(10)->toDateString()]))->toBe([$small->id]);
});

it('always leaves out opted-out and archived customers and says how many', function () {
    $in = offerCustomer([], ['make' => 'Hyundai', 'model' => 'Creta']);
    offerCustomer(['whatsapp_opt_in' => false], ['make' => 'Hyundai', 'model' => 'Creta']);
    $stamped = offerCustomer([], ['make' => 'Hyundai', 'model' => 'Creta']);
    $stamped->forceFill(['opted_out_at' => now()])->saveQuietly();
    Customer::factory()->archived()->create(['whatsapp_opt_in' => true]);

    $count = $this->builder->count(['models' => ['Creta']]);
    expect($count)->toBe(['eligible' => 1, 'opted_out' => 2])
        ->and(SegmentBuilder::countLine($count))->toBe('1 customer · 2 opted out, excluded')
        ->and(SegmentBuilder::countLine(['eligible' => 142, 'opted_out' => 9]))->toBe('142 customers · 9 opted out, excluded')
        ->and(idsFor(['models' => ['Creta']]))->toBe([$in->id]);
});

it('updates the live count through the htmx endpoint', function () {
    offerCustomer([], ['make' => 'Kia', 'model' => 'Seltos']);
    offerCustomer([], ['make' => 'Kia', 'model' => 'Sonet']);
    offerCustomer(['whatsapp_opt_in' => false], ['make' => 'Kia', 'model' => 'Seltos']);

    $this->actingAs(staff('owner'))
        ->get(route('offers.count', ['filters' => ['models' => ['Seltos']]]), ['HX-Request' => 'true'])
        ->assertOk()
        ->assertSeeText('1 customer · 1 opted out, excluded')
        ->assertSee('id="cost-estimate"', false);
});

it('filters by product or category bought, only when billing tables exist', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->get(route('offers.create'))->assertOk()->assertDontSee('Bought a product');
    expect(SegmentBuilder::supportsPurchases())->toBeFalse();

    makeBillingTables();
    $cat = DB::table('categories')->insertGetId(['name' => 'PPF', 'created_at' => now(), 'updated_at' => now()]);
    $ppf = DB::table('products')->insertGetId(['name' => 'Garware PPF full body', 'category_id' => $cat, 'price_paise' => 6_500_000, 'created_at' => now(), 'updated_at' => now()]);
    $led = DB::table('products')->insertGetId(['name' => 'DHC Gleam 2.0 LED', 'price_paise' => 499_900, 'created_at' => now(), 'updated_at' => now()]);

    $a = offerCustomer();
    $b = offerCustomer();
    $cancelled = offerCustomer();
    billFor($a, $ppf);
    billFor($b, $led);
    billFor($cancelled, $ppf, 'cancelled');

    expect(SegmentBuilder::supportsPurchases())->toBeTrue()
        ->and(idsFor(['bought_products' => [$led]]))->toBe([$b->id])
        ->and(idsFor(['bought_categories' => [$cat]]))->toBe([$a->id])
        ->and(idsFor(['bought_products' => [$led], 'bought_categories' => [$cat]]))->toBe([$a->id, $b->id]);

    $this->get(route('offers.create'))->assertSee('Bought a product')->assertSee('Garware PPF full body');
});

it('saves a segment and reuses it on a new offer', function () {
    offerCustomer([], ['make' => 'Hyundai', 'model' => 'Creta']);
    $this->actingAs(staff('owner'))
        ->post(route('segments.store'), ['segment_name' => 'Creta owners', 'filters' => ['models' => ['Creta'], 'visit' => 'any']])
        ->assertRedirect();

    $s = Segment::sole();
    expect($s->filters)->toBe(['models' => ['Creta']]);
    $this->get(route('offers.create', ['segment' => $s->id]))->assertOk()->assertSeeText('1 customer · 0 opted out, excluded');
    $this->get(route('offers.index'))->assertSee('Creta owners');
});

// ---------------------------------------------------------------- offers, free methods

it('creates a broadcast offer and exports a CSV of names and numbers', function () {
    $owner = staff('owner');
    offerCustomer(['name' => 'Rahul Pawar', 'mobile' => '9823104512'], ['make' => 'Hyundai', 'model' => 'Creta']);
    offerCustomer(['name' => 'Sneha Kulkarni', 'mobile' => '9823107788'], ['make' => 'Kia', 'model' => 'Seltos']);
    offerCustomer(['name' => 'Opted Out', 'mobile' => '9823100000', 'whatsapp_opt_in' => false]);

    $this->actingAs($owner)->post(route('offers.store'), [
        'title' => 'Diwali LED offer',
        'message' => 'Hi {name}, LED for your {car}!',
        'filters' => [],
        'method' => 'broadcast',
    ])->assertRedirect();

    $offer = Offer::sole();
    expect($offer->send_method)->toBe('broadcast')
        ->and($offer->status)->toBe('draft')
        ->and($offer->recipient_count)->toBe(2)
        ->and($offer->opted_out_excluded)->toBe(1);

    $this->get(route('offers.show', $offer))->assertOk()
        ->assertSeeText('Hi there, LED for your car!') // copy-message text for a broadcast
        ->assertSee('Download list (CSV)');

    $csv = $this->get(route('offers.broadcast', $offer))->assertOk()->streamedContent();
    $lines = array_map('str_getcsv', preg_split('/\R/', trim(substr($csv, 3))));
    expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($lines[0])->toBe(['Name', 'Phone 1 - Value', 'Notes'])
        ->and($lines[1][0])->toBe('Rahul Pawar')->and($lines[1][1])->toBe('+919823104512')
        ->and($lines[2][1])->toBe('+919823107788')
        ->and($lines)->toHaveCount(3)
        ->and($offer->fresh()->status)->toBe('exported')
        ->and($offer->recipients()->where('status', 'exported')->count())->toBe(2);
});

it('splits a broadcast list into parts of 256', function () {
    $owner = staff('owner');
    Customer::factory()->count(300)->create(['whatsapp_opt_in' => true]);
    $svc = app(OfferService::class);
    $offer = $svc->launch($svc->createDraft(['title' => 'Big', 'message' => 'Hi'], $owner), 'broadcast', $owner);

    $this->actingAs($owner);
    $p1 = substr_count(trim($this->get(route('offers.broadcast', [$offer, 'part' => 1]))->streamedContent()), "\n");
    $p2 = substr_count(trim($this->get(route('offers.broadcast', [$offer, 'part' => 2]))->streamedContent()), "\n");
    expect($p1)->toBe(256)->and($p2)->toBe(44);
    $this->get(route('offers.show', $offer))->assertSee('part 2 of 2');
});

it('saves a draft and starts it later', function () {
    $owner = staff('owner');
    offerCustomer();
    $this->actingAs($owner)->post(route('offers.store'), [
        'title' => 'Ceramic follow-up', 'message' => 'Hi {name}', 'save_draft' => '1', 'method' => 'assisted',
    ])->assertRedirect();
    $offer = Offer::sole();
    expect($offer->status)->toBe('draft')->and($offer->send_method)->toBeNull()->and($offer->recipients()->count())->toBe(0);

    $this->get(route('offers.show', $offer))->assertSee('How to send');
    $this->post(route('offers.start', $offer), ['method' => 'assisted'])->assertRedirect(route('offers.assist', $offer));
    expect($offer->fresh()->status)->toBe('sending')->and($offer->recipients()->count())->toBe(1);
});

it('fills {name} and {car} with a real customer in the preview', function () {
    offerCustomer(['name' => 'vikram shinde'], ['make' => 'Mahindra', 'model' => 'Scorpio-N']);
    $this->actingAs(staff('owner'))
        ->post(route('offers.preview'), ['message' => 'Hi {name}, your {car} deserves PPF', 'filters' => []])
        ->assertOk()
        ->assertSeeText('Hi Vikram, your Scorpio-N deserves PPF')
        ->assertSeeText('34 / 1,000 characters');
});

it('walks the one-by-one sender and saves progress', function () {
    $owner = staff('owner');
    $a = offerCustomer(['name' => 'Amit Jadhav', 'mobile' => '9823011245'], ['make' => 'Tata', 'model' => 'Nexon']);
    $b = offerCustomer(['name' => 'Priya Deshpande', 'mobile' => '9970123456']);
    $c = offerCustomer(['name' => 'Ganesh Kale', 'mobile' => '9822045671']);

    $this->actingAs($owner)->post(route('offers.store'), [
        'title' => 'Seat covers', 'message' => 'Hi {name}, new covers for your {car}', 'method' => 'assisted',
    ])->assertRedirect();
    $offer = Offer::sole();

    $this->get(route('offers.assist', $offer))->assertOk()
        ->assertSeeText('Amit Jadhav')
        ->assertSee('https://wa.me/919823011245?text='.rawurlencode('Hi Amit, new covers for your Nexon'), false)
        ->assertSeeText('0 of 3 done');

    $ra = $offer->recipients()->where('customer_id', $a->id)->sole();
    $this->post(route('offers.recipients.mark', [$offer, $ra]), ['status' => 'sent'], ['HX-Request' => 'true'])
        ->assertOk()->assertSeeText('1 of 3 done')->assertSeeText('Priya Deshpande');

    // Progress survives a reload (e.g. the next day).
    $this->get(route('offers.assist', $offer))->assertSeeText('Priya Deshpande')->assertSeeText('1 of 3 done');

    $rb = $offer->recipients()->where('customer_id', $b->id)->sole();
    $rc = $offer->recipients()->where('customer_id', $c->id)->sole();
    $this->post(route('offers.recipients.mark', [$offer, $rb]), ['status' => 'skipped'])->assertRedirect(route('offers.assist', $offer));
    $this->post(route('offers.recipients.mark', [$offer, $rc]), ['status' => 'sent']);

    $offer->refresh();
    expect($offer->status)->toBe('sent')
        ->and($offer->sent_count)->toBe(2)
        ->and($offer->skipped_count)->toBe(1)
        ->and($ra->fresh()->done_by)->toBe($owner->id);

    // Undo reopens the offer.
    $this->post(route('offers.recipients.mark', [$offer, $rb]), ['status' => 'pending']);
    expect($offer->fresh()->status)->toBe('sending');
    $this->get(route('offers.assist', $offer))->assertSeeText('Priya Deshpande');
});

it('refuses to mark a recipient from another offer', function () {
    $owner = staff('owner');
    offerCustomer();
    $svc = app(OfferService::class);
    $one = $svc->launch($svc->createDraft(['title' => 'One', 'message' => 'Hi'], $owner), 'assisted', $owner);
    $two = $svc->launch($svc->createDraft(['title' => 'Two', 'message' => 'Hi'], $owner), 'assisted', $owner);

    $this->actingAs($owner)
        ->post(route('offers.recipients.mark', [$one, $two->recipients()->first()]), ['status' => 'sent'])
        ->assertNotFound();
});

// ---------------------------------------------------------------- paid mode

it('hides paid sending without the WhatsApp module and refuses it on the server', function () {
    $owner = staff('owner');
    offerCustomer();
    $this->actingAs($owner)->get(route('offers.create'))->assertOk()
        ->assertDontSee('Automatic (paid)')
        ->assertSee('Broadcast list');

    $this->post(route('offers.store'), [
        'title' => 'Paid', 'message' => 'Hi', 'method' => 'automatic', 'template_name' => 'x', 'confirm_cost' => '1',
    ])->assertRedirect();
    expect(Offer::sole()->status)->toBe('draft')->and(Offer::sole()->recipients()->count())->toBe(0);
});

it('hides paid sending when the module is in manual mode', function () {
    WhatsAppBridge::$class = FakeOfferWhatsApp::class;
    FakeOfferWhatsApp::$automatic = false;
    $this->actingAs(staff('owner'))->get(route('offers.create'))->assertDontSee('Automatic (paid)');
});

it('shows paid sending with the cost estimate and queues the sends', function () {
    WhatsAppBridge::$class = FakeOfferWhatsApp::class;
    Setting::put('offers.marketing_rate_paise', 88);
    Queue::fake();
    $owner = staff('owner');
    foreach (range(1, 3) as $i) {
        offerCustomer([], ['make' => 'Hyundai', 'model' => 'Creta']);
    }
    offerCustomer(['whatsapp_opt_in' => false], ['make' => 'Hyundai', 'model' => 'Creta']);

    expect(WhatsAppBridge::estimateCostPaise(142))->toBe(12_496); // 142 × ₹0.88 = ₹124.96

    $this->actingAs($owner)->get(route('offers.create'))->assertOk()
        ->assertSee('Automatic (paid)')
        ->assertSee('diwali_offer (Marketing, approved)')
        ->assertSeeText('3 × ₹0.88 = ₹2.64');

    // No confirmation tick → stays a draft.
    $this->post(route('offers.store'), ['title' => 'Paid', 'message' => 'Hi {name}', 'method' => 'automatic', 'template_name' => 'diwali_offer'])
        ->assertSessionHasErrors('confirm_cost');

    Setting::put('offers.marketing_rate_paise', 100);
    $draft = Offer::sole();
    $this->post(route('offers.start', $draft), ['method' => 'automatic', 'template_name' => 'diwali_offer', 'confirm_cost' => '1'])
        ->assertRedirect(route('offers.show', $draft));

    $draft->refresh();
    expect($draft->status)->toBe('sending')
        ->and($draft->recipient_count)->toBe(3)
        ->and($draft->estimated_cost_paise)->toBe(300);
    Queue::assertPushed(SendOfferMessage::class, 3);
});

it('sends queued messages through the WhatsApp module and closes the offer', function () {
    WhatsAppBridge::$class = FakeOfferWhatsApp::class;
    $owner = staff('owner');
    offerCustomer(['name' => 'Rahul Pawar', 'mobile' => '9823104512'], ['make' => 'Hyundai', 'model' => 'Creta']);
    $late = offerCustomer(['name' => 'Late Optout']);

    Queue::fake();
    $svc = app(OfferService::class);
    $offer = $svc->createDraft(['title' => 'Paid', 'message' => "Hi {name}!\nCreta offer for your {car}."], $owner);
    $offer = $svc->launch($offer, 'automatic', $owner, ['template_name' => 'diwali_offer', 'confirm_cost' => true]);

    $late->update(['whatsapp_opt_in' => false]); // opts out after queueing
    foreach ($offer->recipients as $r) {
        (new SendOfferMessage($r->id))->handle(app(WhatsAppBridge::class));
    }

    $offer->refresh();
    expect(FakeOfferWhatsApp::$sent)->toHaveCount(1)
        ->and(FakeOfferWhatsApp::$sent[0]['to'])->toBe('+919823104512')
        ->and(FakeOfferWhatsApp::$sent[0]['template'])->toBe('diwali_offer')
        ->and(FakeOfferWhatsApp::$sent[0]['params'])->toBe(['Rahul', 'Hi Rahul! · Creta offer for your Creta.'])
        ->and($offer->status)->toBe('sent')
        ->and($offer->sent_count)->toBe(1)
        ->and($offer->skipped_count)->toBe(1)
        ->and($offer->recipients()->where('status', 'sent')->value('message_log_id'))->toBe(1001);
});

// ---------------------------------------------------------------- Meta audience export

it('normalises and hashes exactly as Meta specifies', function () {
    expect(AudienceCsv::metaPhone('+91 98231 07676'))->toBe('919823107676')
        ->and(AudienceCsv::hash('phone', '+919823107676'))->toBe('e0e2a8ea453816d3a8a5b652272236054a7b032862cd419dd074ffb45fddb304')
        ->and(AudienceCsv::hash('fn', '  Rahul '))->toBe('af4a400a2ceb7963a424d9cf3779175c8d258facf8a31ee8bc1949dc060e10e6')
        ->and(AudienceCsv::hash('ct', 'Pune'))->toBe('1114c6826958d2a68e23243c9ce4be4ec82ecc330aa915fe34eed793a69a4042')
        ->and(AudienceCsv::hash('ln', ''))->toBe('')
        ->and(AudienceCsv::splitName('Rahul D. Kale'))->toBe(['Rahul', 'Kale'])
        ->and(AudienceCsv::splitName('Pravin'))->toBe(['Pravin', '']);
});

it('downloads a plain Meta customer list and logs it', function () {
    $owner = staff('owner');
    offerCustomer(['name' => 'Rahul Kale', 'mobile' => '9823107676'], ['make' => 'Hyundai', 'model' => 'Creta']);
    offerCustomer(['name' => 'No Ads', 'mobile' => '9823100001', 'whatsapp_opt_in' => false]);
    $seg = Segment::create(['name' => 'Creta owners', 'filters' => ['models' => ['Creta']]]);

    $csv = $this->actingAs($owner)->post(route('audiences.download'), [
        'segment_id' => $seg->id, 'format' => 'plain', 'fields' => ['fn', 'ln', 'ct', 'st', 'country'],
    ])->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();

    expect(trim($csv))->toBe("phone,fn,ln,ct,st,country\n919823107676,Rahul,Kale,Pune,Maharashtra,in");

    $log = AudienceExport::sole();
    expect($log->user_id)->toBe($owner->id)
        ->and($log->segment_id)->toBe($seg->id)
        ->and($log->segment_name)->toBe('Creta owners')
        ->and($log->format)->toBe('plain')
        ->and($log->row_count)->toBe(1)
        ->and($log->fields)->toBe(['phone', 'fn', 'ln', 'ct', 'st', 'country'])
        ->and($log->created_at)->not->toBeNull();

    $this->get(route('audiences.export'))->assertOk()->assertSee('Creta owners')->assertSeeText('1 rows');
});

it('downloads a SHA-256 hashed Meta list for all customers', function () {
    $owner = staff('owner');
    offerCustomer(['name' => 'Rahul', 'mobile' => '9823107676']);
    offerCustomer(['name' => 'Opted Out', 'whatsapp_opt_in' => false]);

    $csv = $this->actingAs($owner)->post(route('audiences.download'), [
        'segment_id' => 0, 'format' => 'sha256', 'fields' => ['fn', 'ct'],
    ])->assertOk()->streamedContent();

    expect(trim($csv))->toBe("phone,fn,ct\n"
        .'e0e2a8ea453816d3a8a5b652272236054a7b032862cd419dd074ffb45fddb304,'
        .'af4a400a2ceb7963a424d9cf3779175c8d258facf8a31ee8bc1949dc060e10e6,'
        .'1114c6826958d2a68e23243c9ce4be4ec82ecc330aa915fe34eed793a69a4042');

    expect(AudienceExport::sole())->format->toBe('sha256')->segment_name->toBe('All customers')->row_count->toBe(1);
});

// ---------------------------------------------------------------- permissions & home tile

it('lets only people with offers.manage use offers and audience export', function (string $role, bool $allowed) {
    $this->actingAs(staff($role));
    $status = $allowed ? 200 : 403;
    $this->get(route('offers.index'))->assertStatus($status);
    $this->get(route('offers.create'))->assertStatus($status);
    $this->get(route('audiences.export'))->assertStatus($status);
    $this->post(route('audiences.download'), ['segment_id' => 0, 'format' => 'plain'])->assertStatus($status);
    expect(AudienceExport::count())->toBe($allowed ? 1 : 0);
})->with([
    'owner' => ['owner', true],
    'admin' => ['admin', true],
    'marketing' => ['marketing', true],
    'sales' => ['sales', false],
    'finance' => ['finance', false],
    'technician' => ['technician', false],
]);

it('works for one counter person given offers.manage directly', function () {
    $sales = staff('sales');
    $sales->givePermissionTo('offers.manage');
    $this->actingAs($sales)->get(route('offers.index'))->assertOk();
    $this->actingAs(staff('sales'))->get(route('offers.index'))->assertForbidden();
});

it('gives the owner home tile the latest offers', function () {
    $owner = staff('owner');
    $svc = app(OfferService::class);
    $svc->createDraft(['title' => 'First', 'message' => 'Hi'], $owner);
    $svc->createDraft(['title' => 'Second', 'message' => 'Hi'], $owner);

    $recent = OffersTile::recent(1);
    expect($recent)->toHaveCount(1)
        ->and($recent[0]['title'])->toBe('Second')
        ->and($recent[0]['status_label'])->toBe('Draft')
        ->and($recent[0]['url'])->toBe(route('offers.show', Offer::where('title', 'Second')->sole()));
});
