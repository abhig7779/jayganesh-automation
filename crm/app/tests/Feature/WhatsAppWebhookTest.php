<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\MessageLog;
use App\Services\WhatsApp\WebhookProcessor;
use App\Services\WhatsApp\WhatsApp;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Branch::create(['name' => 'Main', 'code' => 'JG']);
    config(['whatsapp.app_secret' => 'app-secret', 'whatsapp.verify_token' => 'verify-me']);
});

function waHookPost($test, array $payload, ?string $secret = 'app-secret', ?string $signature = null)
{
    $raw = json_encode($payload);
    $headers = ['CONTENT_TYPE' => 'application/json'];
    $sig = $signature ?? ($secret !== null ? 'sha256='.hash_hmac('sha256', $raw, $secret) : null);
    if ($sig !== null) {
        $headers['HTTP_X_HUB_SIGNATURE_256'] = $sig;
    }

    return $test->call('POST', '/webhooks/whatsapp', [], [], [], $headers, $raw);
}

function waHookBody(array $value): array
{
    return ['object' => 'whatsapp_business_account', 'entry' => [[
        'id' => '1834738223843088',
        'changes' => [['field' => 'messages', 'value' => array_merge([
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '919823107676', 'phone_number_id' => '111222333'],
        ], $value)]],
    ]]];
}

function waStatus(string $wamid, string $status, int $ts, array $extra = []): array
{
    return waHookBody(['statuses' => [array_merge(['id' => $wamid, 'status' => $status, 'timestamp' => (string) $ts, 'recipient_id' => '919823107676'], $extra)]]);
}

function waInbound(string $wamid, string $text, string $from = '919823107676'): array
{
    return waHookBody([
        'contacts' => [['profile' => ['name' => 'Rahul'], 'wa_id' => $from]],
        'messages' => [['from' => $from, 'id' => $wamid, 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => $text]]],
    ]);
}

function waSentLog(string $wamid = 'wamid.S1'): MessageLog
{
    return MessageLog::create([
        'branch_id' => Branch::first()->id, 'to' => '+919823107676', 'channel' => 'cloud_api',
        'template' => 'invoice_ebill', 'status' => 'sent', 'wa_message_id' => $wamid, 'sent_at' => now()->subMinute(),
    ]);
}

it('answers Meta\'s verify challenge only with our verify token', function () {
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')
        ->assertOk()->assertSeeText('12345');
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=12345')->assertForbidden();

    config(['whatsapp.verify_token' => '']);
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=&hub.challenge=12345')->assertForbidden();
});

it('rejects a webhook without a valid signature and changes nothing', function () {
    $log = waSentLog();
    $body = waStatus('wamid.S1', 'delivered', now()->timestamp);

    waHookPost($this, $body, secret: null)->assertForbidden();
    waHookPost($this, $body, secret: 'wrong-secret')->assertForbidden();
    waHookPost($this, $body, signature: 'sha256=deadbeef')->assertForbidden();

    config(['whatsapp.app_secret' => '']);
    waHookPost($this, $body, secret: '')->assertForbidden();

    expect($log->fresh()->status)->toBe('sent')->and(Cache::get(WebhookProcessor::LAST_WEBHOOK_KEY))->toBeNull();
});

it('accepts a signed webhook without a CSRF token and stamps the last webhook time', function () {
    $log = waSentLog();

    waHookPost($this, waStatus('wamid.S1', 'delivered', now()->timestamp))->assertOk();

    expect($log->fresh()->status)->toBe('delivered')
        ->and($log->fresh()->delivered_at)->not->toBeNull()
        ->and(Cache::get(WebhookProcessor::LAST_WEBHOOK_KEY))->not->toBeNull();
});

it('moves statuses forward in order', function () {
    $log = waSentLog();
    $t = now()->timestamp;

    waHookPost($this, waStatus('wamid.S1', 'sent', $t))->assertOk();
    waHookPost($this, waStatus('wamid.S1', 'delivered', $t + 3))->assertOk();
    waHookPost($this, waStatus('wamid.S1', 'read', $t + 60))->assertOk();

    $log->refresh();
    expect($log->status)->toBe('read')
        ->and($log->delivered_at->timestamp)->toBe($t + 3)
        ->and($log->read_at->timestamp)->toBe($t + 60);
});

it('never downgrades when read arrives before delivered (out of order)', function () {
    $log = waSentLog();
    $t = now()->timestamp;

    waHookPost($this, waStatus('wamid.S1', 'read', $t + 60))->assertOk();
    waHookPost($this, waStatus('wamid.S1', 'delivered', $t + 3))->assertOk();
    waHookPost($this, waStatus('wamid.S1', 'sent', $t))->assertOk();
    waHookPost($this, waStatus('wamid.S1', 'failed', $t + 90, ['errors' => [['code' => 131026, 'title' => 'x']]]))->assertOk();

    $log->refresh();
    expect($log->status)->toBe('read')
        ->and($log->delivered_at->timestamp)->toBe($t + 3) // still recorded
        ->and($log->read_at->timestamp)->toBe($t + 60)
        ->and($log->error_code)->toBeNull()
        ->and($log->failed_at)->toBeNull();
});

it('records a failed status with the plain-English reason', function () {
    $log = waSentLog();

    waHookPost($this, waStatus('wamid.S1', 'failed', now()->timestamp, ['errors' => [[
        'code' => 131026, 'title' => 'Message undeliverable', 'error_data' => ['details' => 'Message Undeliverable.'],
    ]]]))->assertOk();

    $log->refresh();
    expect($log->status)->toBe('failed')
        ->and($log->error_code)->toBe('131026')
        ->and($log->reason())->toContain('may not be on WhatsApp')
        ->and($log->failed_at)->not->toBeNull();
});

it('ignores statuses for messages we did not send', function () {
    waHookPost($this, waStatus('wamid.FROM-PHONE-APP', 'read', now()->timestamp))->assertOk();

    expect(MessageLog::count())->toBe(0);
});

it('logs inbound messages against the customer', function () {
    $c = Customer::create(['branch_id' => Branch::first()->id, 'name' => 'Rahul Kale', 'mobile' => '9823107676']);

    waHookPost($this, waInbound('wamid.IN1', 'Is 3M film in stock for Creta?'))->assertOk();

    $in = MessageLog::where('direction', 'in')->sole();
    expect($in->customer_id)->toBe($c->id)
        ->and($in->to)->toBe('+919823107676')
        ->and($in->category)->toBe('service')
        ->and($in->previewText())->toBe('Is 3M film in stock for Creta?')
        ->and($in->bodyValue('profile_name'))->toBe('Rahul');
});

it('opts a customer out on STOP and back in on START, sending nothing back', function (string $stop, string $start) {
    Http::fake();
    $c = Customer::create(['branch_id' => Branch::first()->id, 'name' => 'Rahul Kale', 'mobile' => '9823107676']);

    waHookPost($this, waInbound('wamid.A', $stop))->assertOk();
    expect($c->fresh()->whatsapp_opt_in)->toBeFalse()
        ->and($c->fresh()->opted_out_at)->not->toBeNull()
        ->and(app(WhatsApp::class)->isOptedOut('+919823107676'))->toBeTrue();

    waHookPost($this, waInbound('wamid.B', $start))->assertOk();
    expect($c->fresh()->whatsapp_opt_in)->toBeTrue()
        ->and($c->fresh()->opted_out_at)->toBeNull();

    Http::assertNothingSent();
    expect(MessageLog::where('direction', 'out')->count())->toBe(0);
})->with([
    'upper case' => ['STOP', 'START'],
    'lower case with spaces' => ['  stop ', 'start'],
    'unsubscribe' => ['Unsubscribe!', 'Start.'],
]);

it('does not treat a sentence containing stop as an opt-out', function () {
    $c = Customer::create(['branch_id' => Branch::first()->id, 'name' => 'Rahul', 'mobile' => '9823107676']);

    waHookPost($this, waInbound('wamid.C', 'Please stop by at 5, my car is ready?'))->assertOk();

    expect($c->fresh()->whatsapp_opt_in)->toBeTrue();
});

it('remembers STOP from a number that is not a customer yet', function () {
    waHookPost($this, waInbound('wamid.U1', 'STOP', '919000000001'))->assertOk();

    expect(app(WhatsApp::class)->isOptedOut('+919000000001'))->toBeTrue();

    waHookPost($this, waInbound('wamid.U2', 'START', '919000000001'))->assertOk();
    expect(app(WhatsApp::class)->isOptedOut('+919000000001'))->toBeFalse();
});

it('is idempotent when Meta delivers the same webhook again', function () {
    $c = Customer::create(['branch_id' => Branch::first()->id, 'name' => 'Rahul', 'mobile' => '9823107676']);
    $log = waSentLog();
    $t = now()->timestamp;

    $stop = waInbound('wamid.STOP1', 'STOP');
    $delivered = waStatus('wamid.S1', 'delivered', $t);

    foreach (range(1, 3) as $i) {
        waHookPost($this, $stop)->assertOk();
        waHookPost($this, $delivered)->assertOk();
    }
    expect(MessageLog::where('direction', 'in')->count())->toBe(1)
        ->and($log->fresh()->status)->toBe('delivered')
        ->and($log->fresh()->delivered_at->timestamp)->toBe($t);

    // START later, then Meta re-sends the old STOP: the customer stays opted in.
    waHookPost($this, waInbound('wamid.START1', 'START'))->assertOk();
    waHookPost($this, $stop)->assertOk();
    expect($c->fresh()->whatsapp_opt_in)->toBeTrue();
});
