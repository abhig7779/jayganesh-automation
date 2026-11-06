<?php

use App\Models\Branch;
use App\Models\Setting;
use App\Services\WhatsApp\CloudApiClient;
use App\Services\WhatsApp\WhatsAppConnection;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

const WA_G = 'https://graph.facebook.com/v23.0';

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Branch::create(['name' => 'Main', 'code' => 'JG']);
    config([
        'whatsapp.token' => null, 'whatsapp.phone_number_id' => null,
        'whatsapp.app_secret' => 'app-secret', 'whatsapp.graph_version' => 'v23.0',
    ]);
});

function waFakeSignup(bool $onBizApp = true): void
{
    Http::fake([
        WA_G.'/oauth/access_token*' => Http::response(['access_token' => 'BISU-TOKEN-123', 'token_type' => 'bearer']),
        WA_G.'/1834738223843088/subscribed_apps' => Http::response(['success' => true]),
        WA_G.'/555666777?*' => Http::response([
            'display_phone_number' => '+91 98231 07676', 'verified_name' => 'Jay Ganesh Car Accessories',
            'is_on_biz_app' => $onBizApp, 'platform_type' => 'CLOUD_API', 'id' => '555666777',
        ]),
        WA_G.'/555666777/smb_app_data' => Http::sequence()
            ->push(['request_id' => 'contacts-req-1'])
            ->push(['request_id' => 'history-req-1']),
    ]);
}

function waSignupBody(array $event = []): array
{
    return ['code' => 'CODE-FROM-FB', 'event' => array_merge([
        'event' => 'FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING',
        'waba_id' => '1834738223843088', 'phone_number_id' => '555666777', 'business_id' => '1728410417676996',
    ], $event)];
}

it('shows the connect page to the owner only', function () {
    $this->actingAs(staff('owner'))->get(route('whatsapp.connect'))
        ->assertOk()->assertSee('Connect WhatsApp')->assertSee('4202556123367996')
        ->assertSee('whatsapp_business_app_onboarding')->assertDontSee('BISU-TOKEN');

    foreach (['admin', 'finance', 'sales', 'marketing', 'technician'] as $role) {
        $user = staff($role);
        $this->actingAs($user)->get(route('whatsapp.connect'))->assertForbidden();
        $this->actingAs($user)->postJson(route('whatsapp.connect.store'), waSignupBody())->assertForbidden();
        $this->actingAs($user)->post(route('whatsapp.ai'), ['mode' => 'all'])->assertForbidden();
    }
});

it('connects a WhatsApp Business app number (coexistence) end to end', function () {
    waFakeSignup();

    $this->actingAs(staff('owner'))->postJson(route('whatsapp.connect.store'), waSignupBody())
        ->assertOk()->assertJson(['ok' => true, 'number' => '+91 98231 07676', 'coexistence' => true])
        ->assertDontSee('BISU-TOKEN-123');

    $c = WhatsAppConnection::current();
    expect($c->waba_id)->toBe('1834738223843088')
        ->and($c->phone_number_id)->toBe('555666777')
        ->and($c->is_on_biz_app)->toBeTrue()
        ->and($c->token)->toBe('BISU-TOKEN-123')
        ->and($c->contacts_sync_request_id)->toBe('contacts-req-1')
        ->and($c->history_sync_request_id)->toBe('history-req-1')
        ->and(Setting::get('whatsapp.mode'))->toBe('cloud_api');

    // Stored encrypted, never in plain text.
    expect(DB::table('whatsapp_connections')->value('token'))->not->toContain('BISU-TOKEN-123');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/oauth/access_token')
        && $r['client_id'] === '2901906313477061' && $r['client_secret'] === 'app-secret' && $r['code'] === 'CODE-FROM-FB');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/1834738223843088/subscribed_apps')
        && $r->method() === 'POST' && $r->hasHeader('Authorization', 'Bearer BISU-TOKEN-123'));
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/smb_app_data') && $r['sync_type'] === 'smb_app_state_sync');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/smb_app_data') && $r['sync_type'] === 'history');
    // Never registers the number or asks for a code.
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/register') || str_contains($r->url(), 'request_code'));
});

it('does not sync contacts or history for a number that is not on the Business app', function () {
    waFakeSignup(onBizApp: false);

    $this->actingAs(staff('owner'))->postJson(route('whatsapp.connect.store'), waSignupBody())
        ->assertOk()->assertJson(['coexistence' => false]);

    Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/smb_app_data'));
});

it('lets the Cloud API client send with the connected number when .env is empty', function () {
    waFakeSignup();
    $this->actingAs(staff('owner'))->postJson(route('whatsapp.connect.store'), waSignupBody())->assertOk();

    Http::fake([WA_G.'/555666777/messages' => Http::response(['messages' => [['id' => 'wamid.X']]])]);
    $client = app(CloudApiClient::class);

    expect($client->isConfigured())->toBeTrue()
        ->and($client->sendText('+919800000001', 'Hello'))->toBe('wamid.X');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/555666777/messages')
        && $r->hasHeader('Authorization', 'Bearer BISU-TOKEN-123') && $r['to'] === '919800000001');
});

it('prefers .env values over the connected number', function () {
    config(['whatsapp.token' => 'ENV-TOKEN', 'whatsapp.phone_number_id' => '999']);
    Http::fake([WA_G.'/999/messages' => Http::response(['messages' => [['id' => 'wamid.E']]])]);

    expect(app(CloudApiClient::class)->sendText('+919800000001', 'Hi'))->toBe('wamid.E');
    Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer ENV-TOKEN'));
});

it('shows Meta\'s error and keeps nothing when the code exchange fails', function () {
    Http::fake([WA_G.'/oauth/access_token*' => Http::response(['error' => ['code' => 100, 'message' => 'This authorization code has expired.']], 400)]);

    $this->actingAs(staff('owner'))->postJson(route('whatsapp.connect.store'), waSignupBody())
        ->assertStatus(422)->assertJson(['ok' => false])->assertSee('expired');

    expect(WhatsAppConnection::count())->toBe(0)
        ->and(Setting::get('whatsapp.mode'))->toBe('manual');
});

it('rejects a sign-up without a WhatsApp account id', function () {
    Http::fake();
    $this->actingAs(staff('owner'))->postJson(route('whatsapp.connect.store'), waSignupBody(['waba_id' => 'abc']))
        ->assertStatus(422);
    Http::assertNothingSent();
});

it('saves the AI reply mode', function () {
    $this->actingAs(staff('owner'))->post(route('whatsapp.ai'), ['mode' => 'ads'])->assertRedirect();
    expect(Setting::get('whatsapp.ai_reply'))->toBe('ads');

    $this->actingAs(staff('owner'))->post(route('whatsapp.ai'), ['mode' => 'everything'])->assertSessionHasErrors('mode');
});

it('records signup events without storing anything token-like', function () {
    $this->actingAs(staff('owner'))->postJson(route('whatsapp.connect.event'), [
        'type' => 'WA_EMBEDDED_SIGNUP', 'event' => 'CANCEL', 'data' => ['current_step' => 'PHONE_NUMBER_SETUP', 'access_token' => 'nope'],
    ])->assertOk();
});
