<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadIntake;
use App\Services\Leads\IntegrationUser;
use App\Support\Quotations\ShopClock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Branch::create(['name' => 'Main', 'code' => 'JG']);
    RateLimiter::clear('leads-api:ip:127.0.0.1');
});

function leadKey(array $abilities = ['leads:create']): string
{
    return IntegrationUser::get()->createToken('Website form', $abilities)->plainTextToken;
}

function leadPost($test, array $body, ?string $key)
{
    app('auth')->forgetGuards();
    $headers = ['Accept' => 'application/json'];
    if ($key !== null) {
        $headers['Authorization'] = 'Bearer '.$key;
    }

    return $test->postJson('/api/v1/leads', $body, $headers);
}

function metaHook($test, array $payload, string $secret = 'meta-secret')
{
    $raw = json_encode($payload);

    return $test->call('POST', '/webhooks/meta-leads', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, $secret),
    ], $raw);
}

function metaLeadgen(string $id): array
{
    return ['object' => 'page', 'entry' => [['id' => '111', 'time' => 1762400000, 'changes' => [[
        'field' => 'leadgen',
        'value' => ['leadgen_id' => $id, 'page_id' => '111', 'form_id' => '222', 'created_time' => 1762400000],
    ]]]]];
}

it('needs a key with the leads:create ability', function () {
    leadPost($this, ['mobile' => '9823107676'], null)->assertUnauthorized();
    leadPost($this, ['mobile' => '9823107676'], 'garbage|token')->assertUnauthorized();
    leadPost($this, ['mobile' => '9823107676'], leadKey(['customers:read']))->assertForbidden();

    expect(Lead::count())->toBe(0);
});

it('creates a lead with a Day-0 follow-up and keeps the UTM tags', function () {
    $customer = Customer::create(['branch_id' => Branch::first()->id, 'name' => 'Rahul Pawar', 'mobile' => '9823107676']);

    leadPost($this, [
        'name' => 'Rahul', 'mobile' => '+91 98231 07676', 'interest' => 'PPF', 'car' => 'Hyundai Creta',
        'message' => 'Full body price?', 'source' => 'website',
        'utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'ppf-pune', 'page_url' => 'https://jayganeshcaraccessories.com/ppf',
    ], leadKey())->assertCreated()->assertJson(['ok' => true, 'merged' => false]);

    $lead = Lead::firstOrFail();
    expect($lead->mobile)->toBe('+919823107676')
        ->and($lead->source)->toBe('website')
        ->and($lead->status)->toBe(Lead::NEW)
        ->and($lead->customer_id)->toBe($customer->id)
        ->and($lead->interest)->toBe('PPF · Car: Hyundai Creta · Full body price?')
        ->and($lead->created_by)->toBe(IntegrationUser::get()->id)
        ->and($lead->assigned_to)->toBeNull();

    $day0 = FollowUp::query()->where('subject_type', $lead->getMorphClass())->where('subject_id', $lead->id)->orderBy('due_on')->first();
    expect($day0->step)->toBe(0)
        ->and($day0->due_on->toDateString())->toBe(ShopClock::today())
        ->and($day0->status)->toBe(FollowUp::PENDING);

    $intake = LeadIntake::firstOrFail();
    expect($intake->only(['utm_source', 'utm_medium', 'utm_campaign', 'channel', 'merged']))
        ->toBe(['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'ppf-pune', 'channel' => 'api', 'merged' => false]);
});

it('validates the mobile number and the source', function () {
    $key = leadKey();
    leadPost($this, ['mobile' => '12345'], $key)->assertUnprocessable()->assertJsonValidationErrors('mobile');
    leadPost($this, ['mobile' => '9823107676', 'source' => 'tiktok'], $key)->assertUnprocessable()->assertJsonValidationErrors('source');
});

it('merges the same mobile within 7 days into one lead and starts a new one after', function () {
    $key = leadKey();
    leadPost($this, ['mobile' => '9823107676', 'interest' => 'Seat covers'], $key)->assertCreated();
    leadPost($this, ['mobile' => '09823107676', 'interest' => 'Mats too', 'source' => 'instagram'], $key)
        ->assertOk()->assertJson(['merged' => true]);

    expect(Lead::count())->toBe(1)
        ->and(Lead::first()->interest)->toStartWith("Seat covers\n+ ")
        ->and(Lead::first()->interest)->toContain('(Instagram): Mats too')
        ->and(FollowUp::query()->where('step', 0)->count())->toBe(1)
        ->and(LeadIntake::where('merged', true)->count())->toBe(1);

    $this->travel(8)->days();
    leadPost($this, ['mobile' => '9823107676', 'interest' => 'PPF'], $key)->assertCreated();
    expect(Lead::count())->toBe(2);

    // A lost lead isn't reopened.
    Lead::query()->latest('id')->first()->update(['status' => Lead::LOST]);
    leadPost($this, ['mobile' => '9823107676', 'interest' => 'LED'], $key)->assertCreated();
    expect(Lead::count())->toBe(3);
});

it('rate-limits each caller', function () {
    config(['leads.api_per_minute_per_ip' => 3]);
    $key = leadKey();
    foreach (range(1, 3) as $i) {
        leadPost($this, ['mobile' => '98231076'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)], $key)->assertCreated();
    }
    leadPost($this, ['mobile' => '9823107699'], $key)->assertStatus(429)->assertHeader('Retry-After');
    expect(Lead::count())->toBe(3);
});

it('lets only the owner make and revoke keys, shown once', function () {
    $owner = staff('owner');
    $this->actingAs(staff('admin'))->get(route('api-tokens.index'))->assertForbidden();

    $this->actingAs($owner)->get(route('api-tokens.index'))->assertOk()->assertSee('No keys yet');
    $this->actingAs($owner)->post(route('api-tokens.store'), ['name' => 'Website form'])->assertRedirect(route('api-tokens.index'));
    $plain = session('api_token_plain');
    expect($plain)->toBeString();

    $this->actingAs($owner)->get(route('api-tokens.index'))->assertSee('Website form')->assertSee('Not used yet');
    $token = IntegrationUser::get()->tokens()->first();
    expect($token->abilities)->toBe(['leads:create']);

    // The key works for leads and nothing else.
    leadPost($this, ['mobile' => '9823107676'], $plain)->assertCreated();
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/customers', ['Authorization' => 'Bearer '.$plain])->assertForbidden();

    app('auth')->forgetGuards();
    $this->actingAs($owner)->delete(route('api-tokens.destroy', $token->id))->assertRedirect(route('api-tokens.index'));
    expect(IntegrationUser::get()->tokens()->count())->toBe(0);
    leadPost($this, ['mobile' => '9823107677'], $plain)->assertUnauthorized();
});

// ------------------------------------------------------------------ Meta lead ads

it('is switched off until META_LEADS_ENABLED', function () {
    config(['leads.meta.enabled' => false]);
    $this->get('/webhooks/meta-leads?hub_mode=subscribe&hub_verify_token=x&hub_challenge=1')->assertNotFound();
});

it('answers the verify challenge and checks the signature', function () {
    config(['leads.meta' => ['enabled' => true, 'app_secret' => 'meta-secret', 'verify_token' => 'verify-me', 'page_token' => 'page-token', 'graph_version' => 'v23.0']]);
    Http::fake();

    $this->get('/webhooks/meta-leads?hub_mode=subscribe&hub_verify_token=verify-me&hub_challenge=12345')->assertOk()->assertSee('12345');
    $this->get('/webhooks/meta-leads?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=12345')->assertForbidden();

    metaHook($this, metaLeadgen('L1'), 'wrong-secret')->assertForbidden();
    Http::assertNothingSent();
    expect(Lead::count())->toBe(0);
});

it('fetches the lead from the Graph API and creates the lead once', function () {
    config(['leads.meta' => ['enabled' => true, 'app_secret' => 'meta-secret', 'verify_token' => 'verify-me', 'page_token' => 'page-token', 'graph_version' => 'v23.0']]);
    Http::fake([
        'graph.facebook.com/v23.0/L1*' => Http::response([
            'id' => 'L1',
            'created_time' => '2026-11-06T10:00:00+0000',
            'campaign_name' => 'PPF Nov',
            'ad_name' => 'PPF reel 1',
            'adset_name' => 'Pune 25km',
            'platform' => 'ig',
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Sneha Kulkarni']],
                ['name' => 'phone_number', 'values' => ['+919981397676']],
                ['name' => 'which_car_do_you_drive?', 'values' => ['Tata Nexon']],
                ['name' => 'what_service_are_you_looking_for?', 'values' => ['Ceramic coating']],
                ['name' => 'city', 'values' => ['Wakad']],
            ],
        ]),
    ]);

    metaHook($this, metaLeadgen('L1'))->assertOk();
    metaHook($this, metaLeadgen('L1'))->assertOk(); // Meta re-sends: no second lead

    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_contains($r->url(), '/v23.0/L1') && $r->hasHeader('Authorization', 'Bearer page-token'));

    $lead = Lead::sole();
    expect($lead->source)->toBe('meta_lead_ad')
        ->and($lead->name)->toBe('Sneha Kulkarni')
        ->and($lead->mobile)->toBe('+919981397676')
        ->and($lead->interest)->toBe('Ceramic coating · Car: Tata Nexon · Area: Wakad')
        ->and($lead->followUps()->where('step', 0)->exists())->toBeTrue();

    expect(LeadIntake::sole()->only(['channel', 'external_id', 'utm_source', 'utm_campaign', 'utm_content']))
        ->toBe(['channel' => 'meta_webhook', 'external_id' => 'meta:L1', 'utm_source' => 'instagram', 'utm_campaign' => 'PPF Nov', 'utm_content' => 'PPF reel 1']);
});

it('asks Meta to retry when the Graph API fails', function () {
    config(['leads.meta' => ['enabled' => true, 'app_secret' => 'meta-secret', 'verify_token' => 'v', 'page_token' => 'page-token', 'graph_version' => 'v23.0']]);
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 400)]);

    metaHook($this, metaLeadgen('L2'))->assertStatus(500);
    expect(Lead::count())->toBe(0)->and(LeadIntake::count())->toBe(0);
});
