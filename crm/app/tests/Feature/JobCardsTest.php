<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\JobCard;
use App\Models\JobCardPhoto;
use App\Models\Product;
use App\Models\User;
use App\Services\JobCards\JobCardService;
use App\Support\Dashboard\Workshop;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->service = app(JobCardService::class);
    $this->counter = staff('sales');
    $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Rahul Pawar', 'mobile' => '+919823104512']);
    $this->car = CustomerCar::factory()->create(['customer_id' => $this->customer->id, 'make' => 'Hyundai', 'model' => 'Creta', 'reg_no' => 'MH12KT4521']);
});

function jobCard(array $attrs = [], ?User $by = null): JobCard
{
    $t = test();

    return $t->service->create(array_merge([
        'customer_id' => $t->customer->id,
        'customer_car_id' => $t->car->id,
        'items' => [
            ['description' => 'Ceramic coating 9H', 'qty' => 1, 'price_paise' => 1_499_900],
            ['description' => 'Interior detailing', 'qty' => 1, 'price_paise' => 250_000],
        ],
    ], $attrs), $by ?? $t->counter);
}

// --- Numbering -------------------------------------------------------------------------------

it('numbers job cards per financial year without gaps', function () {
    Carbon::setTestNow('2026-11-05 10:00:00');
    expect(jobCard()->number)->toBe('JC-2627-0001')
        ->and(jobCard()->number)->toBe('JC-2627-0002');

    // A card that fails and rolls back gives its number back.
    DB::beginTransaction();
    $this->service->nextNumber($this->branch->id);
    DB::rollBack();
    expect(jobCard()->number)->toBe('JC-2627-0003');

    // A new financial year starts again at 0001.
    Carbon::setTestNow('2027-04-01 09:00:00');
    expect(jobCard()->number)->toBe('JC-2728-0001');
    Carbon::setTestNow();
});

it('works out the financial year in Indian time', function () {
    expect(JobCardService::financialYear(Carbon::parse('2026-03-31 17:00:00', 'UTC')))->toBe('2526')
        // 31 Mar 19:00 UTC is already 1 Apr in Pune.
        ->and(JobCardService::financialYear(Carbon::parse('2026-03-31 19:00:00', 'UTC')))->toBe('2627')
        ->and(JobCardService::financialYear(Carbon::parse('2026-11-05')))->toBe('2627');
});

// --- Status machine --------------------------------------------------------------------------

it('walks a car from waiting to delivered and stamps every step', function () {
    $card = jobCard();
    foreach (['working', 'qc', 'ready', 'delivered'] as $to) {
        $this->service->transition($card, $to, $this->counter);
    }
    $card->refresh();

    expect($card->status)->toBe('delivered')
        ->and($card->started_at)->not->toBeNull()
        ->and($card->qc_at)->not->toBeNull()
        ->and($card->ready_at)->not->toBeNull()
        ->and($card->delivered_at)->not->toBeNull()
        ->and($card->events()->where('type', 'status')->pluck('to_status')->all())
        ->toBe(['working', 'qc', 'ready', 'delivered']);
});

it('blocks moves the status machine does not allow', function (string $from, string $to) {
    $card = jobCard();
    $path = ['waiting' => [], 'working' => ['working'], 'qc' => ['working', 'qc'], 'delivered' => ['working', 'qc', 'ready', 'delivered']][$from];
    foreach ($path as $step) {
        $this->service->transition($card, $step, $this->counter);
    }

    expect(fn () => $this->service->transition($card, $to, $this->counter))->toThrow(ValidationException::class);
    expect($card->fresh()->status)->toBe($from);
})->with([
    'waiting → ready (skips work and QC)' => ['waiting', 'ready'],
    'waiting → delivered' => ['waiting', 'delivered'],
    'working → ready (skips QC)' => ['working', 'ready'],
    'qc → delivered' => ['qc', 'delivered'],
    'delivered → working' => ['delivered', 'working'],
    'delivered → cancelled' => ['delivered', 'cancelled'],
]);

it('allows rework and needs a reason to cancel', function () {
    $card = jobCard();
    $this->service->transition($card, 'working', $this->counter);
    $this->service->transition($card, 'qc', $this->counter);
    $this->service->transition($card, 'working', $this->counter, 'Bubble on bonnet film');
    expect($card->fresh()->status)->toBe('working');

    expect(fn () => $this->service->transition($card, 'cancelled', $this->counter))->toThrow(ValidationException::class);
    $this->service->transition($card, 'cancelled', $this->counter, 'Customer changed mind');
    expect($card->fresh()->status)->toBe('cancelled')->and($card->fresh()->cancelled_at)->not->toBeNull();
});

it('records item ticks with who and when', function () {
    $card = jobCard();
    $tech = staff('technician');
    $item = $card->items->first();
    $this->service->setItemDone($item, true, $tech);

    expect($item->fresh()->done)->toBeTrue()
        ->and($item->fresh()->done_by)->toBe($tech->id)
        ->and($card->events()->where('type', 'item_done')->exists())->toBeTrue();
});

// --- Technician view -------------------------------------------------------------------------

it('shows a technician only their own cars and never any money', function () {
    $me = staff('technician', ['name' => 'Sunil Gaikwad']);
    $other = staff('technician', ['name' => 'Akash Pawar']);
    $mine = jobCard(['technician_id' => $me->id]);

    $otherCar = CustomerCar::factory()->create(['customer_id' => $this->customer->id, 'model' => 'Nexon', 'reg_no' => 'MH12RD8890']);
    $theirs = jobCard(['technician_id' => $other->id, 'customer_car_id' => $otherCar->id]);

    $home = $this->actingAs($me)->get(route('jobcards.mine'))->assertOk()
        ->assertSee('MH 12 KT 4521')->assertSee('Ceramic coating 9H')
        ->assertDontSee('MH 12 RD 8890');
    expect($home->getContent())->not->toContain('₹')->not->toContain('14,999');

    $detail = $this->actingAs($me)->get(route('jobcards.show', $mine))->assertOk()->assertSee('Ceramic coating 9H');
    expect($detail->getContent())->not->toContain('₹')->not->toContain('14,999');

    $this->actingAs($me)->get(route('jobcards.show', $theirs))->assertForbidden();
    $this->actingAs($me)->get(route('jobcards.photos.show', [$theirs, 1]))->assertNotFound();
    $this->actingAs($me)->get(route('jobcards.print', $theirs))->assertForbidden();

    // The board is the counter's: a technician lands on their own list.
    $this->actingAs($me)->get(route('jobcards.index'))->assertRedirect(route('jobcards.mine'));
});

it('lets a technician start and send to QC, but not pass QC or deliver', function () {
    $me = staff('technician');
    $card = jobCard(['technician_id' => $me->id]);

    $this->actingAs($me)->post(route('jobcards.status', $card), ['status' => 'working'])->assertRedirect();
    $this->actingAs($me)->post(route('jobcards.status', $card), ['status' => 'qc'])->assertRedirect();
    $this->actingAs($me)->postJson(route('jobcards.status', $card), ['status' => 'ready'])->assertForbidden();
    expect($card->fresh()->status)->toBe('qc');

    $this->actingAs($me)->postJson(route('jobcards.items.toggle', [$card, $card->items->first()]), ['done' => 1])
        ->assertOk()->assertJson(['done' => true, 'progress' => '1/2']);
});

// --- Photos ----------------------------------------------------------------------------------

it('stores photos privately, resized to 1600 px, and a retry does not duplicate them', function () {
    Storage::fake('local');
    $me = staff('technician');
    $card = jobCard(['technician_id' => $me->id]);
    $uuid = (string) Str::uuid();

    $send = fn () => $this->actingAs($me)->post(route('jobcards.photos.store', $card), [
        'stage' => 'before',
        'photos' => [UploadedFile::fake()->image('front.jpg', 3200, 2400)],
        'client_ids' => [$uuid],
    ], ['Accept' => 'application/json']);

    $send()->assertCreated()->assertJsonPath('count', 1);
    $send()->assertCreated()->assertJsonPath('count', 1); // same UUID again: still one photo

    $photo = JobCardPhoto::sole();
    Storage::disk('local')->assertExists([$photo->path, $photo->thumb_path]);
    [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($photo->path));
    expect(max($w, $h))->toBe(1600)->and($w)->toBe(1600)->and($h)->toBe(1200)
        ->and($photo->path)->toStartWith('jobcards/'.$card->id.'/')
        ->and($card->events()->where('type', 'photo')->count())->toBe(1);

    // Several files in one go.
    $this->actingAs($me)->post(route('jobcards.photos.store', $card), [
        'stage' => 'after',
        'photos' => [UploadedFile::fake()->image('a.png', 800, 600), UploadedFile::fake()->image('b.jpg', 600, 800)],
    ])->assertRedirect();
    expect($card->photos()->where('stage', 'after')->count())->toBe(2);
});

it('rejects files that are not photos', function () {
    Storage::fake('local');
    $me = staff('technician');
    $card = jobCard(['technician_id' => $me->id]);

    $this->actingAs($me)->postJson(route('jobcards.photos.store', $card), [
        'stage' => 'before', 'photos' => [UploadedFile::fake()->create('bill.pdf', 50, 'application/pdf')],
    ])->assertUnprocessable()->assertJsonValidationErrors('photos.0');

    $this->actingAs($me)->postJson(route('jobcards.photos.store', $card), ['stage' => 'before', 'photos' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('photos');

    $this->actingAs($me)->postJson(route('jobcards.photos.store', $card), [
        'stage' => 'sideways', 'photos' => [UploadedFile::fake()->image('a.jpg')],
    ])->assertUnprocessable()->assertJsonValidationErrors('stage');

    // A fake ".jpg" that is really text is caught when we try to open it.
    $this->actingAs($me)->postJson(route('jobcards.photos.store', $card), [
        'stage' => 'before', 'photos' => [UploadedFile::fake()->createWithContent('x.jpg', 'not really a picture')],
    ])->assertUnprocessable();

    expect(JobCardPhoto::count())->toBe(0);
});

it('serves photos only to people who can see the job card', function () {
    Storage::fake('local');
    $me = staff('technician');
    $card = jobCard(['technician_id' => $me->id]);
    [$photo] = $this->service->addPhotos($card, [UploadedFile::fake()->image('a.jpg', 400, 300)], 'before', $me);

    $this->actingAs($me)->get($photo->url())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs($me)->get($photo->url(true))->assertOk();
    $this->actingAs(staff('sales'))->get($photo->url())->assertOk();
    $this->actingAs(staff('technician'))->get($photo->url())->assertForbidden();
    $this->actingAs(staff('finance'))->get($photo->url())->assertForbidden();
    auth()->logout();
    $this->get($photo->url())->assertRedirect(route('login'));

    // A photo id under the wrong job card is not found.
    $second = jobCard();
    $this->actingAs(staff('owner'))->get(route('jobcards.photos.show', [$second, $photo]))->assertNotFound();
});

// --- Board drag endpoint ---------------------------------------------------------------------

it('moves a card when it is dropped on another column', function () {
    $card = jobCard();
    $this->actingAs($this->counter)->postJson(route('jobcards.status', $card), ['status' => 'working'])
        ->assertOk()->assertJson(['ok' => true, 'status' => 'working', 'label' => 'Working', 'whatsapp_url' => null]);
    expect($card->fresh()->status)->toBe('working')->and($card->fresh()->started_at)->not->toBeNull();

    // A drop the machine does not allow comes back as 422 and changes nothing.
    $this->actingAs($this->counter)->postJson(route('jobcards.status', $card), ['status' => 'delivered'])
        ->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->actingAs($this->counter)->postJson(route('jobcards.status', $card), ['status' => 'parked'])
        ->assertUnprocessable();
    expect($card->fresh()->status)->toBe('working');

    // Dropping on Ready offers the WhatsApp message.
    $this->service->transition($card, 'qc', $this->counter);
    $this->actingAs($this->counter)->postJson(route('jobcards.status', $card), ['status' => 'ready'])
        ->assertOk()->assertJsonPath('whatsapp_url', route('jobcards.notify-ready', $card));

    // The same drop twice (double request) is harmless.
    $this->actingAs($this->counter)->postJson(route('jobcards.status', $card), ['status' => 'ready'])->assertOk();
    expect($card->events()->where('type', 'status')->where('to_status', 'ready')->count())->toBe(1);
});

it('shows the board with cards in their columns', function () {
    $tech = staff('technician', ['name' => 'Sunil Gaikwad']);
    $card = jobCard(['technician_id' => $tech->id, 'promised_at' => now()->subMinutes(30)]);
    $this->service->transition($card, 'working', $this->counter);

    $this->actingAs($this->counter)->get(route('jobcards.index'))->assertOk()
        ->assertSee('Job board')->assertSee('MH 12 KT 4521')->assertSee('Creta')->assertSee('Rahul Pawar')
        ->assertSee('SG')->assertSee('Late 30 min')->assertSee('data-column="working"', false);

    $this->actingAs($this->counter)->get(route('jobcards.index', ['technician' => $tech->id + 999]))
        ->assertOk()->assertDontSee('MH 12 KT 4521');
});

// --- Make bill -------------------------------------------------------------------------------

it('puts the job card into the bill prefill shape and opens New bill', function () {
    Route::get('/bills/create', fn () => 'new bill')->name('bills.create');
    app('router')->getRoutes()->refreshNameLookups();

    $product = Product::create(['name' => 'DHC Gleam 2.0 LED headlight', 'price_paise' => 499_900]);
    $card = jobCard(['items' => [
        ['product_id' => $product->id, 'qty' => 2],
        ['description' => 'Fitting charge', 'price_paise' => 50_000],
    ]]);
    $owner = staff('owner');

    $this->actingAs($owner)->post(route('jobcards.bill', $card))->assertRedirect(route('bills.create'));

    expect(session('bill_prefill'))->toBe([
        'customer_id' => $this->customer->id,
        'customer_car_id' => $this->car->id,
        'quotation_id' => null,
        'job_card_id' => $card->id,
        'items' => [['product_id' => $product->id, 'qty' => 2, 'unit_price_paise' => 499_900]],
    ])->and(session('jobcards.billing_job_card_id'))->toBe($card->id);
});

it('says billing is coming soon until billing is merged', function () {
    $card = jobCard();
    foreach (['working', 'qc', 'ready', 'delivered'] as $to) {
        $this->service->transition($card, $to, $this->counter);
    }
    $owner = staff('owner');

    $this->actingAs($owner)->get(route('jobcards.show', $card))->assertOk()->assertSee('Billing coming soon');
    $this->actingAs($owner)->from(route('jobcards.show', $card))->post(route('jobcards.bill', $card))
        ->assertRedirect(route('jobcards.show', $card))->assertSessionHas('status');
    expect(session('bill_prefill'))->toBeNull();

    // Technicians can't make bills.
    $this->actingAs(staff('technician'))->post(route('jobcards.bill', $card))->assertForbidden();
});

// --- Car in ----------------------------------------------------------------------------------

it('opens a job card from the Car in form for a new customer and car', function () {
    $tech = staff('technician');
    $this->actingAs($this->counter)->post(route('jobcards.store'), [
        'mobile' => '98231 07788',
        'name' => 'Sneha Kulkarni',
        'car_model' => 'Tata Nexon',
        'car_reg_no' => 'mh 12 rd 8890',
        'odometer' => '42,150',
        'fuel_level' => '1/2',
        'promised_at' => '2026-11-05T18:00',
        'technician_id' => $tech->id,
        'items' => [
            ['description' => 'Garware PPF full body', 'qty' => 1, 'price' => '65,000'],
            ['description' => '', 'qty' => 1], // empty row ignored
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $card = JobCard::query()->latest('id')->first();
    expect($card->customer->name)->toBe('Sneha Kulkarni')
        ->and($card->customer->mobile)->toBe('+919823107788')
        ->and($card->car->make)->toBe('Tata')->and($card->car->model)->toBe('Nexon')
        ->and($card->car->reg_no)->toBe('MH12RD8890')
        ->and($card->odometer)->toBe(42150)
        ->and($card->promised_at->toDateTimeString())->toBe('2026-11-05 12:30:00') // 6 pm IST in UTC
        ->and($card->technician_id)->toBe($tech->id)
        ->and($card->items)->toHaveCount(1)
        ->and($card->items->first()->price_paise)->toBe(6_500_000)
        ->and($card->events()->pluck('type')->all())->toContain('created', 'assigned');
});

it('asks for the missing pieces on Car in', function () {
    $this->actingAs($this->counter)->post(route('jobcards.store'), ['mobile' => '98231', 'items' => []])
        ->assertSessionHasErrors(['items', 'name', 'mobile', 'customer_car_id']);

    $other = Customer::factory()->create(['branch_id' => $this->branch->id]);
    $this->actingAs($this->counter)->post(route('jobcards.store'), [
        'customer_id' => $other->id, 'customer_car_id' => $this->car->id,
        'items' => [['description' => 'Wash']],
    ])->assertSessionHasErrors('customer_car_id');
});

// --- Roles -----------------------------------------------------------------------------------

it('applies the role matrix to job card screens', function (string $role, int $board, int $create, int $mine) {
    $u = staff($role);
    $this->actingAs($u)->get(route('jobcards.index'))->assertStatus($board);
    $this->actingAs($u)->get(route('jobcards.create'))->assertStatus($create);
    $this->actingAs($u)->get(route('jobcards.mine'))->assertStatus($mine);
})->with([
    'owner' => ['owner', 200, 200, 200],
    'admin' => ['admin', 200, 200, 200],
    'sales' => ['sales', 200, 200, 200],
    'technician' => ['technician', 302, 403, 200],
    'finance' => ['finance', 403, 403, 403],
]);

it('only lets the counter and admins assign, add work and open cards', function () {
    $card = jobCard();
    $tech = staff('technician');
    $this->actingAs($tech)->post(route('jobcards.store'), [])->assertForbidden();
    $this->actingAs($tech)->post(route('jobcards.assign', $card), ['technician_id' => $tech->id])->assertForbidden();
    $this->actingAs($tech)->post(route('jobcards.items.store', $card), ['description' => 'Wash'])->assertForbidden();
    $this->actingAs(staff('finance'))->post(route('jobcards.status', $card), ['status' => 'working'])->assertForbidden();

    $this->actingAs(staff('admin'))->post(route('jobcards.assign', $card), ['technician_id' => $tech->id, 'promised_at' => '2026-11-06T11:00'])->assertRedirect();
    expect($card->fresh()->technician_id)->toBe($tech->id);
    $this->actingAs($this->counter)->post(route('jobcards.items.store', $card), ['description' => 'Wash', 'price' => '300'])->assertSessionHasNoErrors()->assertRedirect();
    expect($card->items()->count())->toBe(3);
    $this->actingAs($this->counter)->getJson(route('jobcards.products', ['q' => 'x']))->assertOk();
    $this->actingAs($tech)->getJson(route('jobcards.products', ['q' => 'x']))->assertForbidden();
});

// --- WhatsApp + Home helpers -----------------------------------------------------------------

it('builds the English ready message as a wa.me link and logs it', function () {
    $card = jobCard();
    $link = $this->service->readyWhatsAppLink($card);
    expect($link)->toStartWith('https://wa.me/919823104512?text=')
        ->and(rawurldecode(Str::after($link, 'text=')))
        ->toBe('Hi Rahul, your Creta (MH 12 KT 4521) is ready for pickup at Jay Ganesh Car Accessories.');

    foreach (['working', 'qc', 'ready'] as $to) {
        $this->service->transition($card, $to, $this->counter);
    }
    $this->actingAs($this->counter)->post(route('jobcards.notify-ready', $card))->assertRedirect($link);
    expect($card->events()->where('type', 'whatsapp')->exists())->toBeTrue();
});

it('gives the Home page workshop counts, the late list and a technician list', function () {
    $tech = staff('technician');
    $a = jobCard(['technician_id' => $tech->id, 'promised_at' => now()->subHour()]);
    $b = jobCard();
    $this->service->transition($b, 'working', $this->counter);
    $c = jobCard();
    foreach (['working', 'qc', 'ready', 'delivered'] as $to) {
        $this->service->transition($c, $to, $this->counter);
    }

    $s = Workshop::summary();
    expect($s['counts'])->toBe(['waiting' => 1, 'working' => 1, 'qc' => 0, 'ready' => 0, 'delivered_today' => 1])
        ->and($s['in_shop'])->toBe(2)
        ->and($s['late']->pluck('id')->all())->toBe([$a->id])
        ->and(Workshop::forTechnician($tech)->pluck('id')->all())->toBe([$a->id]);
});
