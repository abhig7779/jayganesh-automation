<?php

use App\Models\Branch;
use App\Models\MessageLog;
use App\Models\Setting;
use App\Services\WhatsApp\AiChat;
use App\Services\WhatsApp\AiReplyAgent;
use App\Services\WhatsApp\Jobs\SendAiReply;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Branch::create(['name' => 'Main', 'code' => 'JG']);
    config([
        'whatsapp.app_secret' => 'app-secret',
        'whatsapp.token' => 'T', 'whatsapp.phone_number_id' => '111222333', 'whatsapp.graph_version' => 'v23.0',
        'whatsapp.ai.anthropic_key' => 'sk-ant-test', 'whatsapp.ai.openai_key' => null,
    ]);
});

function aiHook($test, array $value): void
{
    $raw = json_encode(['object' => 'whatsapp_business_account', 'entry' => [[
        'id' => '1834738223843088',
        'changes' => [['field' => isset($value['message_echoes']) ? 'smb_message_echoes' : 'messages', 'value' => array_merge([
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '919823107676', 'phone_number_id' => '111222333'],
        ], $value)]],
    ]]]);
    $test->call('POST', '/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, 'app-secret'),
    ], $raw)->assertOk();
}

function aiInbound(string $id, string $text, ?array $referral = null, string $from = '919800000001'): array
{
    return [
        'contacts' => [['profile' => ['name' => 'Rahul Patil'], 'wa_id' => $from]],
        'messages' => [array_filter([
            'from' => $from, 'id' => $id, 'timestamp' => (string) now()->timestamp, 'type' => 'text',
            'text' => ['body' => $text], 'referral' => $referral,
        ])],
    ];
}

function aiAd(): array
{
    return [
        'source_url' => 'https://fb.me/abc', 'source_id' => '120200000000001', 'source_type' => 'ad',
        'headline' => 'DHC Gleam 2.0 Diwali offer', 'body' => '40% off, Buy 1 Get 1', 'ctwa_clid' => 'clid-1',
    ];
}

function fakeClaude(string $reply, bool $handoff = false): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['reply' => $reply, 'handoff' => $handoff])]],
            'stop_reason' => 'end_turn',
        ]),
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.AI1']]]),
    ]);
}

it('stays off by default', function () {
    Queue::fake();
    aiHook($this, aiInbound('wamid.1', 'Hi, price?', aiAd()));
    Queue::assertNothingPushed();
});

it('queues a reply for an ad chat in "ads" mode and remembers the ad', function () {
    Queue::fake();
    Setting::put('whatsapp.ai_reply', 'ads');

    aiHook($this, aiInbound('wamid.1', 'Hi, I saw your ad', aiAd()));

    Queue::assertPushed(SendAiReply::class);
    $chat = AiChat::where('contact', '+919800000001')->first();
    expect($chat->isAdChat())->toBeTrue()->and($chat->ad_referral['headline'])->toBe('DHC Gleam 2.0 Diwali offer');
    expect(MessageLog::where('direction', 'in')->first()->body['referral']['ctwa_clid'])->toBe('clid-1');
});

it('does not answer non-ad chats in "ads" mode, but does in "all" mode', function () {
    Queue::fake();
    Setting::put('whatsapp.ai_reply', 'ads');
    aiHook($this, aiInbound('wamid.1', 'Hello'));
    Queue::assertNothingPushed();

    Setting::put('whatsapp.ai_reply', 'all');
    aiHook($this, aiInbound('wamid.2', 'Hello again'));
    Queue::assertPushed(SendAiReply::class, 1);
});

it('writes the reply with Claude, sends it as text and logs it', function () {
    Setting::put('whatsapp.ai_reply', 'ads');
    fakeClaude('Namaste Rahul ji! Which car do you have?');
    Queue::fake();
    aiHook($this, aiInbound('wamid.1', 'Hi, I saw your ad', aiAd()));

    $log = app(AiReplyAgent::class)->reply(MessageLog::where('direction', 'in')->first());

    expect($log->body['text'])->toBe('Namaste Rahul ji! Which car do you have?')
        ->and($log->body['ai'])->toBeTrue()
        ->and($log->wa_message_id)->toBe('wamid.AI1');

    Http::assertSent(function (Request $r) {
        if (! str_contains($r->url(), 'api.anthropic.com')) {
            return false;
        }

        return $r->hasHeader('x-api-key', 'sk-ant-test')
            && str_contains($r['system'], 'DHC Gleam 2.0 Diwali offer')          // the ad it came from
            && str_contains($r['system'], 'automated assistant')                // first reply introduces itself
            && str_contains($r['system'], 'Never state a price')
            && $r['messages'][0]['role'] === 'user';
    });
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/111222333/messages')
        && ($r['type'] ?? null) === 'text' && $r['text']['body'] === 'Namaste Rahul ji! Which car do you have?');
});

it('only answers the newest of several quick messages', function () {
    Setting::put('whatsapp.ai_reply', 'all');
    fakeClaude('Sure!');
    Queue::fake();
    aiHook($this, aiInbound('wamid.1', 'Hi'));
    aiHook($this, aiInbound('wamid.2', 'Need seat covers'));

    $agent = app(AiReplyAgent::class);
    expect($agent->reply(MessageLog::where('wa_message_id', 'wamid.1')->first()))->toBeNull()
        ->and($agent->reply(MessageLog::where('wa_message_id', 'wamid.2')->first()))->not->toBeNull();
});

it('steps back when the owner replies from the WhatsApp Business app', function () {
    Setting::put('whatsapp.ai_reply', 'all');
    Queue::fake();

    aiHook($this, ['message_echoes' => [[
        'from' => '919823107676', 'to' => '919800000001', 'id' => 'wamid.ECHO1',
        'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => 'Hi Rahul, come tomorrow'],
    ]]]);

    $echo = MessageLog::where('wa_message_id', 'wamid.ECHO1')->first();
    expect($echo->direction)->toBe('out')->and($echo->channel)->toBe('business_app')
        ->and(AiChat::where('contact', '+919800000001')->first()->isPaused())->toBeTrue();

    aiHook($this, aiInbound('wamid.3', 'Ok what time?'));
    Queue::assertNothingPushed();
});

it('pauses itself after handing the chat to a person', function () {
    Setting::put('whatsapp.ai_reply', 'all');
    fakeClaude('Our team will share the exact price here shortly.', handoff: true);
    Queue::fake();
    aiHook($this, aiInbound('wamid.1', 'What is the price for Creta?'));

    app(AiReplyAgent::class)->reply(MessageLog::where('direction', 'in')->first());

    expect(AiChat::where('contact', '+919800000001')->first()->paused_reason)->toBe('handoff');
    aiHook($this, aiInbound('wamid.2', 'Hello?'));
    Queue::assertPushed(SendAiReply::class, 1);
});

it('never replies to STOP, to old history or past the daily limit', function () {
    Setting::put('whatsapp.ai_reply', 'all');
    config(['whatsapp.ai.max_replies_per_day' => 1]);
    Queue::fake();

    aiHook($this, aiInbound('wamid.1', 'STOP'));
    Queue::assertNothingPushed();

    $old = aiInbound('wamid.2', 'old message');
    $old['messages'][0]['timestamp'] = (string) now()->subHours(3)->timestamp;
    aiHook($this, $old);
    Queue::assertNothingPushed();

    AiChat::for('+919800000001')->countReply();
    aiHook($this, aiInbound('wamid.3', 'Hello'));
    Queue::assertNothingPushed();
});

it('sends nothing when the model gives no usable answer', function () {
    Setting::put('whatsapp.ai_reply', 'all');
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'overloaded']], 529)]);
    Queue::fake();
    aiHook($this, aiInbound('wamid.1', 'Hi'));

    expect(app(AiReplyAgent::class)->reply(MessageLog::where('direction', 'in')->first()))->toBeNull();
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com'));
});
