<?php

use App\Contracts\BillForWhatsApp;
use App\Events\InvoiceSaved;
use App\Jobs\SendWhatsAppTemplate;
use App\Listeners\SendInvoiceOnWhatsApp;
use App\Models\Branch;
use App\Models\MessageLog;
use App\Models\Setting;
use App\Services\WhatsApp\InvoiceMessage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Branch::create(['name' => 'Main', 'code' => 'JG']);
    config([
        'whatsapp.token' => 'test-token-xyz',
        'whatsapp.phone_number_id' => '111222333',
        'whatsapp.http.retry_sleep_ms' => 0,
    ]);
    Setting::put('whatsapp.mode', 'cloud_api');
});

/** A bill as the billing module will hand it over (through InvoiceMessage after the merge). */
function waBill(array $over = []): BillForWhatsApp
{
    return new class($over) implements BillForWhatsApp
    {
        public function __construct(private array $o) {}

        public function to(): ?string
        {
            return array_key_exists('to', $this->o) ? $this->o['to'] : '+919823107676';
        }

        public function customerName(): string
        {
            return 'Rahul Kale';
        }

        public function number(): string
        {
            return 'JG-2526-0142';
        }

        public function totalFormatted(): string
        {
            return '₹12,500';
        }

        public function statusText(): string
        {
            return 'Paid';
        }

        public function pdfPath(): ?string
        {
            if (! array_key_exists('pdf', $this->o)) {
                $path = storage_path('framework/testing/JG-2526-0142.pdf');
                @mkdir(dirname($path), 0777, true);
                file_put_contents($path, '%PDF-1.4 test');

                return $path;
            }

            return $this->o['pdf'];
        }

        public function pdfName(): string
        {
            return 'JG-2526-0142.pdf';
        }

        public function related(): ?Model
        {
            return null;
        }

        public function isCancelled(): bool
        {
            return $this->o['cancelled'] ?? false;
        }
    };
}

it('listens to InvoiceSaved', function () {
    Event::fake();
    Event::assertListening(InvoiceSaved::class, SendInvoiceOnWhatsApp::class);
});

it('queues the e-bill to send right after the response in Cloud API mode', function () {
    Bus::fake();

    $log = app(SendInvoiceOnWhatsApp::class)->send(waBill());

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('queued')
        ->and($log->channel)->toBe('cloud_api')
        ->and($log->template)->toBe('invoice_ebill')
        ->and($log->params())->toBe(['Rahul Kale', 'JG-2526-0142', '₹12,500', 'Paid'])
        ->and($log->document_name)->toBe('JG-2526-0142.pdf')
        ->and($log->bodyValue('document_path'))->toEndWith('JG-2526-0142.pdf');
    Bus::assertDispatchedAfterResponse(SendWhatsAppTemplate::class, fn ($job) => $job->messageLogId === $log->id);
});

it('sends the e-bill end to end after the response', function () {
    Http::fake([
        '*/media' => Http::response(['id' => 'MEDIA-1']),
        '*/messages' => Http::response(['messages' => [['id' => 'wamid.BILL']]]),
    ]);

    $log = app(SendInvoiceOnWhatsApp::class)->send(waBill());
    expect($log->fresh()->status)->toBe('queued');

    app()->terminate(); // what Laravel does after sending the response

    expect($log->fresh())->status->toBe('sent')->wa_message_id->toBe('wamid.BILL');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/messages') && $r['template']['components'][0]['type'] === 'header');
});

it('falls back to the database queue when the instant send fails', function () {
    Http::fake([
        '*/media' => Http::response(['id' => 'MEDIA-1']),
        '*/messages' => Http::response(['error' => ['code' => 131000, 'message' => 'Something went wrong']], 500),
    ]);

    $log = app(SendInvoiceOnWhatsApp::class)->send(waBill());
    app()->terminate();

    expect($log->fresh()->status)->toBe('queued')
        ->and($log->fresh()->error_code)->toBe('131000')
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->value('available_at'))->toBeGreaterThan(now()->timestamp + 30);
});

it('does nothing automatic in manual mode', function () {
    Bus::fake();
    Http::fake();
    Setting::put('whatsapp.mode', 'manual');

    expect(app(SendInvoiceOnWhatsApp::class)->send(waBill()))->toBeNull();

    Bus::assertNothingDispatched();
    Http::assertNothingSent();
    expect(MessageLog::count())->toBe(0);
});

it('does nothing when automatic sending is switched off', function () {
    Bus::fake();
    Setting::put('whatsapp.auto_send_invoice', false);

    expect(app(SendInvoiceOnWhatsApp::class)->send(waBill()))->toBeNull();
    Bus::assertNothingDispatched();
});

it('skips walk-in bills without a number and cancelled bills', function () {
    Bus::fake();

    expect(app(SendInvoiceOnWhatsApp::class)->send(waBill(['to' => null])))->toBeNull()
        ->and(app(SendInvoiceOnWhatsApp::class)->send(waBill(['cancelled' => true])))->toBeNull();
    Bus::assertNothingDispatched();
});

it('fails the e-bill clearly when no PDF can be made', function () {
    Http::fake();

    $log = app(SendInvoiceOnWhatsApp::class)->send(waBill(['pdf' => null]));
    app()->terminate();

    expect($log->fresh()->status)->toBe('failed')->and($log->fresh()->error_code)->toBe('no_document');
    Http::assertNothingSent();
});

it('handles the InvoiceSaved event with a billing-style invoice', function () {
    Bus::fake();

    event(new InvoiceSaved((object) [
        'number' => 'JG-2526-0007', 'customer_mobile' => '98231 07676', 'customer_name' => 'Sneha',
        'total_paise' => 1_250_000, 'balance_paise' => 200_000, 'status' => 'partial',
    ]));

    $log = MessageLog::sole();
    expect($log->params())->toBe(['Sneha', 'JG-2526-0007', '₹12,500', 'Balance due ₹2,000'])
        ->and($log->to)->toBe('+919823107676')
        ->and($log->document_name)->toBe('JG-2526-0007.pdf');
    Bus::assertDispatchedAfterResponse(SendWhatsAppTemplate::class);
});

it('adapts invoice fields for the message', function () {
    $paid = InvoiceMessage::fromInvoice((object) ['number' => 'JG/1', 'customer' => (object) ['name' => 'Amit', 'mobile' => '+919876543210'], 'total_paise' => 99_900, 'status' => 'paid']);

    expect($paid->to())->toBe('+919876543210')
        ->and($paid->customerName())->toBe('Amit')
        ->and($paid->statusText())->toBe('Paid')
        ->and($paid->pdfName())->toBe('JG-1.pdf')
        ->and($paid->pdfPath())->toBeNull() // InvoicePdf belongs to billing; absent on this branch
        ->and(InvoiceMessage::fromInvoice((object) ['number' => 'X', 'customer_mobile' => '12345'])->to())->toBeNull();
});

// --- screens ---

function waLogRow(array $attrs = []): MessageLog
{
    return MessageLog::create(array_merge([
        'branch_id' => Branch::first()->id, 'to' => '+919823107676', 'channel' => 'cloud_api',
        'template' => 'invoice_ebill', 'body' => ['params' => ['Rahul', 'JG-2526-0001', '₹1,500', 'Paid']],
        'status' => 'read', 'wa_message_id' => 'wamid.R',
    ], $attrs));
}

it('shows the WhatsApp log to the owner and admin only', function () {
    waLogRow();

    foreach (['owner', 'admin'] as $role) {
        $this->actingAs(staff($role))->get(route('whatsapp.messages.index'))->assertOk()->assertSee('WhatsApp log');
    }
    foreach (['sales', 'finance', 'technician'] as $role) {
        $this->actingAs(staff($role))->get(route('whatsapp.messages.index'))->assertForbidden();
    }
});

it('shows the setup card to the owner only, with yes/no and never the values', function () {
    $this->actingAs(staff('owner'))->get(route('whatsapp.messages.index'))
        ->assertOk()
        ->assertSee('WhatsApp setup')
        ->assertSee('WHATSAPP_TOKEN')
        ->assertSee('Last webhook from Meta')
        ->assertSee('/webhooks/whatsapp')
        ->assertDontSee('test-token-xyz')
        ->assertDontSee('111222333');

    $this->actingAs(staff('admin'))->get(route('whatsapp.messages.index'))->assertDontSee('WhatsApp setup');
});

it('filters the log and shows ticks with the read blue only on the tick', function () {
    waLogRow(['status' => 'read']);
    waLogRow(['status' => 'failed', 'error_code' => '131026', 'to' => '+919800000002', 'wa_message_id' => null]);
    waLogRow(['status' => 'delivered', 'template' => 'payment_receipt', 'to' => '+919800000003']);

    $owner = staff('owner');
    $this->actingAs($owner)->get(route('whatsapp.messages.index'))
        ->assertSee('<span aria-hidden="true" style="color:#34B7F1">✓✓</span> Read', false)
        ->assertSee('may not be on WhatsApp')
        ->assertSee('Resend');

    $this->actingAs($owner)->get(route('whatsapp.messages.index', ['status' => 'failed']))
        ->assertSee('+91 98000 00002')->assertDontSee('+91 98000 00003');
    $this->actingAs($owner)->get(route('whatsapp.messages.index', ['template' => 'payment_receipt']))
        ->assertSee('+91 98000 00003')->assertDontSee('+91 98000 00002');
    $this->actingAs($owner)->get(route('whatsapp.messages.index', ['from' => now()->addDay()->toDateString()]))
        ->assertSee('No WhatsApp messages match these filters');
});

it('resends a failed message as a new log row', function () {
    Bus::fake();
    $failed = waLogRow(['status' => 'failed', 'error_code' => '131026']);

    $this->actingAs(staff('sales'))->post(route('whatsapp.resend', $failed))->assertRedirect();

    $new = MessageLog::latest('id')->first();
    expect($new->id)->not->toBe($failed->id)
        ->and($new->status)->toBe('queued')
        ->and($new->params())->toBe($failed->params())
        ->and($new->bodyValue('resend_of'))->toBe($failed->id)
        ->and($failed->fresh()->status)->toBe('failed');
    Bus::assertDispatchedAfterResponse(SendWhatsAppTemplate::class, fn ($j) => $j->messageLogId === $new->id);
});

it('opens WhatsApp with the message in manual mode', function () {
    Setting::put('whatsapp.mode', 'manual');
    $log = waLogRow(['channel' => 'manual', 'status' => 'queued', 'wa_message_id' => null]);

    $this->actingAs(staff('sales'))->post(route('whatsapp.manual', $log))
        ->assertRedirect()
        ->assertRedirectContains('https://wa.me/919823107676?text=Hi%20Rahul');

    expect($log->fresh()->status)->toBe('manual_opened');
});

it('blocks resend and manual open for roles that cannot bill', function () {
    $log = waLogRow(['status' => 'failed']);

    $this->actingAs(staff('technician'))->post(route('whatsapp.resend', $log))->assertForbidden();
    $this->actingAs(staff('technician'))->post(route('whatsapp.manual', $log))->assertForbidden();
});

it('lets only the owner send a test template', function () {
    Http::fake(['*/messages' => Http::response(['messages' => [['id' => 'wamid.TEST']]])]);

    $this->actingAs(staff('admin'))->post(route('whatsapp.test'), ['to' => '9823107676', 'template' => 'hello_world'])->assertForbidden();

    $this->actingAs(staff('owner'))
        ->post(route('whatsapp.test'), ['to' => '9823107676', 'template' => 'hello_world', 'language' => 'en_US'])
        ->assertRedirect()->assertSessionHas('status');

    expect(MessageLog::sole())->status->toBe('sent')->template->toBe('hello_world');
    Http::assertSent(fn (Request $r) => $r['template']['language']['code'] === 'en_US');
});

it('refuses the test send while in manual mode', function () {
    Http::fake();
    Setting::put('whatsapp.mode', 'manual');

    $this->actingAs(staff('owner'))
        ->post(route('whatsapp.test'), ['to' => '9823107676', 'template' => 'hello_world'])
        ->assertSessionHas('error');
    Http::assertNothingSent();
});

it('renders the status component for every state', function () {
    $this->actingAs(staff('sales'));

    expect((string) $this->blade('<x-whatsapp.status :log="$log" />', ['log' => null]))->toContain('Not sent on WhatsApp');
    expect((string) $this->blade('<x-whatsapp.status :log="$log" />', ['log' => waLogRow(['status' => 'sent'])]))->toContain('✓</span> Sent');
    expect((string) $this->blade('<x-whatsapp.status :log="$log" />', ['log' => waLogRow(['status' => 'delivered'])]))->toContain('✓✓</span> Delivered')->not->toContain('#34B7F1');
    expect((string) $this->blade('<x-whatsapp.status :log="$log" />', ['log' => waLogRow(['status' => 'failed', 'error_code' => '190'])]))->toContain('access token')->toContain('Send manually');
    expect((string) $this->blade('<x-whatsapp.status :log="$log" />', ['log' => waLogRow(['status' => 'queued', 'channel' => 'manual'])]))->toContain('Send on WhatsApp');
});
