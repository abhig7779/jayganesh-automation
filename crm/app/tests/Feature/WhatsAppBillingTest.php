<?php

use App\Events\PaymentCollected;
use App\Listeners\SendPaymentReceiptOnWhatsApp;
use App\Models\MessageLog;
use App\Models\Setting;
use App\Services\Billing\BillShare;
use App\Services\Billing\PaymentService;
use App\Services\WhatsApp\InvoiceMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/Billing/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    billingSetup($this);
    config(['whatsapp.token' => 'test-token', 'whatsapp.phone_number_id' => '111222333', 'whatsapp.http.retry_sleep_ms' => 0]);
    Setting::put('whatsapp.mode', 'cloud_api');
    Setting::put('whatsapp.auto_send_invoice', true);
    $this->product = billProduct(['name' => 'DHC Gleam 2.0 LED', 'price_paise' => 499900, 'mrp_paise' => 650000], 10);
    $this->customer = billCustomer('9823107676', 'Sneha Kulkarni');
    Http::fake([
        '*/media' => Http::response(['id' => 'MEDIA-1']),
        '*/messages' => Http::response(['messages' => [['id' => 'wamid.X']]]),
    ]);
});

function waMake($t, array $extra = [])
{
    return makeBill(staff('owner'), [[$t->product]], array_merge(['customer_id' => $t->customer->id], $extra));
}

it('sends the real bill PDF as an e-bill in cloud API mode and the bill-done status follows', function () {
    $bill = waMake($this, ['payments' => payFull([[$this->product]])]);
    app()->terminate(); // what Laravel does after the response

    $log = MessageLog::sole();
    expect($log->to)->toBe('+919823107676')
        ->and($log->template)->toBe('invoice_ebill')
        ->and($log->status)->toBe('sent')
        ->and($log->related_id)->toBe($bill->id)
        ->and($log->params())->toBe(['Sneha Kulkarni', $bill->number, '₹4,999', 'Paid'])
        ->and($log->document_name)->toBe(str_replace('/', '-', $bill->number).'.pdf');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/media'));

    $this->actingAs(staff('owner'))->getJson(route('bills.status', $bill))
        ->assertOk()->assertJsonPath('whatsapp', 'sent')->assertJsonPath('automatic', true);
});

it('says the balance for a part-paid bill and shares the same text as the manual link', function () {
    $bill = waMake($this, ['payments' => [['mode' => 'upi', 'amount_paise' => 200000]]]);
    $msg = InvoiceMessage::fromInvoice($bill);

    expect($msg->statusText())->toBe('Balance due ₹2,999')
        ->and($msg->text())->toBe(BillShare::message($bill))
        ->and($msg->text())->toContain('You saved ₹')
        ->and(is_file($msg->pdfPath()))->toBeTrue();
});

it('does not send when the bill says no WhatsApp, for a walk-in, or for a cancelled bill', function () {
    waMake($this, ['payments' => payFull([[$this->product]]), 'send_whatsapp' => false]);
    makeBill(staff('sales'), [[$this->product]], ['payments' => payFull([[$this->product]])]); // walk-in, no mobile
    app()->terminate();

    expect(MessageLog::count())->toBe(0);
    Http::assertNothingSent();
});

it('makes no WhatsApp call in manual mode', function () {
    Setting::put('whatsapp.mode', 'manual');
    $bill = waMake($this, ['payments' => payFull([[$this->product]])]);
    app()->terminate();

    expect(MessageLog::count())->toBe(0);
    Http::assertNothingSent();
    $this->actingAs(staff('owner'))->getJson(route('bills.status', $bill))
        ->assertOk()->assertJsonPath('automatic', false)->assertJsonPath('whatsapp', null)
        ->assertJsonPath('manual_link', fn ($l) => str_starts_with($l, 'https://wa.me/919823107676?text='));
});

it('sends a payment receipt when a due is collected in cloud API mode', function () {
    $bill = waMake($this, ['payments' => [['mode' => 'cash', 'amount_paise' => 199900]]]); // due ₹3,000
    app()->terminate();
    MessageLog::query()->delete();

    $payment = app(PaymentService::class)->collect($this->customer, 100000, 'upi', null, [$bill->id], staff('sales'));

    $log = MessageLog::sole();
    expect($log->template)->toBe('payment_receipt')
        ->and($log->related_id)->toBe($payment->id)
        ->and($log->params())->toBe(['Sneha Kulkarni', '₹1,000', $bill->number, '₹2,000'])
        ->and($log->status)->toBe('sent');

    // The same event again does not double-send.
    expect(app(SendPaymentReceiptOnWhatsApp::class)->handle(new PaymentCollected($payment, 200000)))->toBeNull();
});

it('sends no receipt in manual mode and does not error', function () {
    Setting::put('whatsapp.mode', 'manual');
    $bill = waMake($this, ['payments' => [['mode' => 'cash', 'amount_paise' => 199900]]]);
    app()->terminate();
    Http::assertNothingSent();

    app(PaymentService::class)->collect($this->customer, 100000, 'cash', null, [$bill->id], staff('sales'));

    expect(MessageLog::count())->toBe(0);
    Http::assertNothingSent();
});
