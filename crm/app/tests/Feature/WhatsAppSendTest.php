<?php

use App\Jobs\SendWhatsAppTemplate;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MessageLog;
use App\Models\Setting;
use App\Services\WhatsApp\ErrorMap;
use App\Services\WhatsApp\WhatsApp;
use App\Services\WhatsApp\WhatsAppException;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

const WA_GRAPH = 'https://graph.facebook.com/v23.0/111222333';

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    waCloudMode();
});

function waCloudMode(): void
{
    config([
        'whatsapp.token' => 'test-token-xyz',
        'whatsapp.phone_number_id' => '111222333',
        'whatsapp.app_secret' => 'app-secret',
        'whatsapp.verify_token' => 'verify-me',
        'whatsapp.http.retry_sleep_ms' => 0,
    ]);
    Setting::put('whatsapp.mode', 'cloud_api');
}

function waPdf(): string
{
    $path = storage_path('framework/testing/JG-2526-0001.pdf');
    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, "%PDF-1.4\n% test bill\n");

    return $path;
}

function waCustomer(array $attrs = []): Customer
{
    return Customer::create(array_merge([
        'branch_id' => Branch::first()->id,
        'name' => 'Rahul Kale',
        'mobile' => '9823107676',
    ], $attrs));
}

function waMetaError(int $code, int $status = 400, string $details = 'details from Meta'): array
{
    return ['error' => ['message' => '(#'.$code.') Something', 'type' => 'OAuthException', 'code' => $code, 'error_data' => ['details' => $details]]];
}

it('uploads the PDF and sends the template with a document header', function () {
    Http::fake([
        WA_GRAPH.'/media' => Http::response(['id' => 'MEDIA-1']),
        WA_GRAPH.'/messages' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.ABC']]]),
    ]);
    $customer = waCustomer();

    $log = app(WhatsApp::class)->sendTemplate(
        '+919823107676', 'invoice_ebill', ['Rahul Kale', 'JG-2526-0001', '₹1,500', 'Paid'], waPdf(), 'JG-2526-0001.pdf',
    );

    expect($log->status)->toBe('sent')
        ->and($log->wa_message_id)->toBe('wamid.ABC')
        ->and($log->channel)->toBe('cloud_api')
        ->and($log->category)->toBe('utility')
        ->and($log->customer_id)->toBe($customer->id)
        ->and($log->attempts)->toBe(1)
        ->and($log->sent_at)->not->toBeNull()
        ->and($log->bodyValue('media_id'))->toBe('MEDIA-1');

    Http::assertSentInOrder([
        fn (Request $r) => $r->url() === WA_GRAPH.'/media'
            && $r->isMultipart()
            && $r->hasHeader('Authorization', 'Bearer test-token-xyz')
            && collect($r->data())->contains(fn ($part) => $part['name'] === 'file' && $part['filename'] === 'JG-2526-0001.pdf')
            && collect($r->data())->contains(fn ($part) => $part['name'] === 'messaging_product' && $part['contents'] === 'whatsapp'),
        fn (Request $r) => $r->url() === WA_GRAPH.'/messages'
            && $r['to'] === '919823107676'
            && $r['type'] === 'template'
            && $r['template']['name'] === 'invoice_ebill'
            && $r['template']['language']['code'] === 'en'
            && $r['template']['components'][0] === ['type' => 'header', 'parameters' => [['type' => 'document', 'document' => ['id' => 'MEDIA-1', 'filename' => 'JG-2526-0001.pdf']]]]
            && $r['template']['components'][1]['parameters'][2] === ['type' => 'text', 'text' => '₹1,500'],
    ]);
});

it('cleans template values that Meta would reject', function () {
    Http::fake([WA_GRAPH.'/messages' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

    app(WhatsApp::class)->sendTemplate('9823107676', 'payment_receipt', ["Rahul\nKale", '', 'a     b']);

    Http::assertSent(fn (Request $r) => collect($r['template']['components'][0]['parameters'])->pluck('text')->all() === ['Rahul Kale', '-', 'a   b']);
});

it('maps Meta error codes to plain English for staff', function (int $code, string $expected) {
    Http::fake([WA_GRAPH.'/messages' => Http::response(waMetaError($code), 400)]);

    $log = app(WhatsApp::class)->sendTemplate('+919823107676', 'payment_receipt', ['Rahul']);

    expect($log->status)->toBe('failed')
        ->and($log->error_code)->toBe((string) $code)
        ->and($log->reason())->toContain($expected)
        ->and($log->reason())->not->toContain('OAuthException')
        ->and($log->failed_at)->not->toBeNull();
})->with([
    'not on WhatsApp' => [131026, 'may not be on WhatsApp'],
    '24h window' => [131047, 'More than 24 hours'],
    'template missing' => [132001, 'template is missing or not approved'],
    'token' => [190, 'access token has expired'],
    'rate limit' => [131056, 'Too many messages'],
]);

it('keeps unknown Meta codes readable and knows which codes are worth retrying', function () {
    expect(ErrorMap::message(999999, 'Weird thing'))->toContain('999999')->toContain('Weird thing')
        ->and(ErrorMap::retryable(131056))->toBeTrue()
        ->and(ErrorMap::retryable('network'))->toBeTrue()
        ->and(ErrorMap::retryable(131026))->toBeFalse()
        ->and(ErrorMap::retryable(132001))->toBeFalse()
        ->and(ErrorMap::retryable(190))->toBeFalse();
});

it('retries a 5xx inside the HTTP client', function () {
    Http::fake([WA_GRAPH.'/messages' => Http::sequence()
        ->push(waMetaError(131000), 500)
        ->push(['messages' => [['id' => 'wamid.OK']]])]);

    $log = app(WhatsApp::class)->sendTemplate('+919823107676', 'payment_receipt', ['Rahul']);

    expect($log->status)->toBe('sent');
    Http::assertSentCount(2);
});

it('never retries a 4xx inside the HTTP client', function () {
    Http::fake([WA_GRAPH.'/messages' => Http::response(waMetaError(131026), 400)]);

    app(WhatsApp::class)->sendTemplate('+919823107676', 'payment_receipt', ['Rahul']);

    Http::assertSentCount(1);
});

it('fails with a clear reason when the Cloud API is switched on but not configured', function () {
    config(['whatsapp.token' => null]);
    Http::fake();

    $log = app(WhatsApp::class)->sendTemplate('+919823107676', 'payment_receipt', ['Rahul']);

    expect($log->status)->toBe('failed')->and($log->error_code)->toBe('not_configured');
    Http::assertNothingSent();
});

it('never sends marketing to a customer who opted out', function () {
    Http::fake();
    waCustomer(['whatsapp_opt_in' => false]);

    $log = app(WhatsApp::class)->sendTemplate('+919823107676', 'review_request', ['Rahul', 'Creta', 'https://g.page/r/x']);

    expect($log->status)->toBe('failed')
        ->and($log->category)->toBe('marketing')
        ->and($log->error_code)->toBe('opted_out')
        ->and($log->reason())->toContain('opted out');
    Http::assertNothingSent();
});

it('sends utility to an opted-out customer only when the settings toggle allows it', function () {
    Http::fake([WA_GRAPH.'/messages' => Http::response(['messages' => [['id' => 'wamid.U']]])]);
    waCustomer(['whatsapp_opt_in' => false]);
    $wa = app(WhatsApp::class);

    // Default: utility respects the opt-out too.
    expect($wa->sendTemplate('+919823107676', 'payment_receipt', ['Rahul'])->error_code)->toBe('opted_out');
    Http::assertNothingSent();

    Setting::put('whatsapp.utility_respects_opt_out', false);
    expect($wa->sendTemplate('+919823107676', 'payment_receipt', ['Rahul'])->status)->toBe('sent')
        ->and($wa->sendTemplate('+919823107676', 'review_request', ['Rahul'])->error_code)->toBe('opted_out');
});

it('sends marketing to a customer who is opted in, and treats unknown templates as marketing', function () {
    Http::fake([WA_GRAPH.'/messages' => Http::response(['messages' => [['id' => 'wamid.M']]])]);
    waCustomer();

    $wa = app(WhatsApp::class);
    expect($wa->sendTemplate('+919823107676', 'review_request', ['Rahul'])->status)->toBe('sent')
        ->and($wa->categoryFor('diwali_offer_2026'))->toBe('marketing')
        ->and($wa->categoryFor('invoice_ebill'))->toBe('utility');

    Setting::put('whatsapp.template_invoice', 'bill_v2');
    expect($wa->categoryFor('bill_v2'))->toBe('utility');
});

it('makes no HTTP call in manual mode and gives a correct wa.me link', function () {
    Http::fake();
    Setting::put('whatsapp.mode', 'manual');
    $wa = app(WhatsApp::class);

    $log = $wa->sendTemplate('+919823107676', 'invoice_ebill', ['Rahul & Sons', 'JG-2526-0001', '₹1,500', 'Paid'], waPdf(), 'JG-2526-0001.pdf');

    expect($wa->isAutomatic())->toBeFalse()
        ->and($log->channel)->toBe('manual')
        ->and($log->status)->toBe('queued')
        ->and($log->previewText())->toContain('Hi Rahul & Sons,')->toContain('invoice JG-2526-0001 for ₹1,500');
    Http::assertNothingSent();

    expect($wa->manualLink('+919823107676', "Hi Rahul & Co,\nTotal ₹1,500 #1?"))
        ->toBe('https://wa.me/919823107676?text=Hi%20Rahul%20%26%20Co%2C%0ATotal%20%E2%82%B91%2C500%20%231%3F')
        ->and($wa->manualLink('98231 07676', ''))->toBe('https://wa.me/919823107676');
});

it('sends free text inside the service window', function () {
    Http::fake([WA_GRAPH.'/messages' => Http::response(['messages' => [['id' => 'wamid.T']]])]);

    $log = app(WhatsApp::class)->sendText('+919823107676', 'Your film is in stock.');

    expect($log->status)->toBe('sent')->and($log->category)->toBe('service')->and($log->template)->toBeNull();
    Http::assertSent(fn (Request $r) => $r['type'] === 'text' && $r['text']['body'] === 'Your film is in stock.');
});

// --- the queue job ---

function waQueuedLog(array $attrs = []): MessageLog
{
    return MessageLog::create(array_merge([
        'branch_id' => Branch::first()->id,
        'to' => '+919823107676',
        'channel' => 'cloud_api',
        'template' => 'payment_receipt',
        'category' => 'utility',
        'body' => ['params' => ['Rahul'], 'language' => 'en'],
        'status' => 'queued',
    ], $attrs));
}

/** Pretend the job runs on the database queue, on try number $attempt. */
function waOnQueue(SendWhatsAppTemplate $job, int $attempt): SendWhatsAppTemplate
{
    $queueJob = Mockery::mock(QueueJob::class);
    $queueJob->shouldReceive('getConnectionName')->andReturn('database');
    $queueJob->shouldReceive('attempts')->andReturn($attempt);
    $job->setJob($queueJob);

    return $job;
}

it('retries at 1, 5 and 15 minutes on the database queue', function () {
    $job = new SendWhatsAppTemplate(1);

    expect($job->backoff())->toBe([60, 300, 900])
        ->and($job->tries)->toBe(4)
        ->and($job->connection)->toBe('database')
        ->and($job)->toBeInstanceOf(ShouldQueue::class);

    $handOver = new SendWhatsAppTemplate(1, firstTryDone: true);
    expect($handOver->backoff())->toBe([300, 900])->and($handOver->tries)->toBe(3);
});

it('hands a failed after-response try to the queue, one minute later', function () {
    Queue::fake();
    Http::fake([WA_GRAPH.'/messages' => Http::response(waMetaError(131056), 400)]);
    $log = waQueuedLog();

    (new SendWhatsAppTemplate($log->id))->handle(app(WhatsApp::class));

    Queue::assertPushed(SendWhatsAppTemplate::class, fn ($job) => $job->messageLogId === $log->id
        && $job->firstTryDone === true
        && $job->delay !== null);
    expect($log->fresh()->status)->toBe('queued')
        ->and($log->fresh()->error_code)->toBe('131056')
        ->and($log->fresh()->attempts)->toBe(1);
});

it('rethrows on the queue until the last try, then marks the log failed', function () {
    Http::fake([WA_GRAPH.'/messages' => Http::response(waMetaError(130429), 400)]);
    $log = waQueuedLog();

    expect(fn () => waOnQueue(new SendWhatsAppTemplate($log->id), 2)->handle(app(WhatsApp::class)))
        ->toThrow(WhatsAppException::class);
    expect($log->fresh()->status)->toBe('queued');

    waOnQueue(new SendWhatsAppTemplate($log->id), 4)->handle(app(WhatsApp::class));
    expect($log->fresh())
        ->status->toBe('failed')
        ->attempts->toBe(2)
        ->error_code->toBe('130429');
});

it('marks a non-retryable failure failed at once, without queueing a retry', function () {
    Queue::fake();
    Http::fake([WA_GRAPH.'/messages' => Http::response(waMetaError(131026), 400)]);
    $log = waQueuedLog();

    (new SendWhatsAppTemplate($log->id))->handle(app(WhatsApp::class));

    Queue::assertNothingPushed();
    expect($log->fresh()->status)->toBe('failed')->and($log->fresh()->reason())->toContain('not be on WhatsApp');
});

it('records the error when the queue gives up (failed())', function () {
    $log = waQueuedLog();

    (new SendWhatsAppTemplate($log->id))->failed(new WhatsAppException('network'));
    expect($log->fresh())->status->toBe('failed')->error_code->toBe('network');

    $other = waQueuedLog();
    (new SendWhatsAppTemplate($other->id))->failed(new RuntimeException('secret stack detail'));
    expect($other->fresh()->status)->toBe('failed')
        ->and($other->fresh()->error_text)->not->toContain('secret stack detail');
});

it('does nothing for a log that is already sent or manual', function () {
    Http::fake();
    $sent = waQueuedLog(['status' => 'sent', 'wa_message_id' => 'wamid.X']);
    $manual = waQueuedLog(['channel' => 'manual']);

    (new SendWhatsAppTemplate($sent->id))->handle(app(WhatsApp::class));
    (new SendWhatsAppTemplate($manual->id))->handle(app(WhatsApp::class));

    Http::assertNothingSent();
});

it('reuses the uploaded media id on a retry instead of uploading again', function () {
    Http::fake([
        WA_GRAPH.'/media' => Http::response(['id' => 'MEDIA-9']),
        WA_GRAPH.'/messages' => Http::sequence()->push(waMetaError(131056), 400)->push(['messages' => [['id' => 'wamid.R']]]),
    ]);
    $log = waQueuedLog(['template' => 'invoice_ebill', 'document_name' => 'JG.pdf', 'body' => ['params' => ['R'], 'document_path' => waPdf()]]);
    $wa = app(WhatsApp::class);

    expect(fn () => $wa->deliver($log))->toThrow(WhatsAppException::class);
    $wa->deliver($log->fresh());

    expect($log->fresh()->status)->toBe('sent');
    expect(collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/media')))->toHaveCount(1);
});

it('never puts the token in the log row', function () {
    Http::fake([WA_GRAPH.'/messages' => Http::response(waMetaError(190), 401)]);

    $log = app(WhatsApp::class)->sendTemplate('+919823107676', 'payment_receipt', ['Rahul']);

    expect(json_encode($log->fresh()->toArray()))->not->toContain('test-token-xyz');
});
