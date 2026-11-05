<?php

namespace App\Listeners;

use App\Events\PaymentCollected;
use App\Models\MessageLog;
use App\Models\Setting;
use App\Services\WhatsApp\WhatsApp;
use App\Support\Money;
use App\Support\Phone;

/**
 * Receipt for an old due that was collected ("Received ₹2,000 … Balance now ₹1,500").
 * Cloud API mode only, and only if the customer has a valid mobile; manual mode sends nothing.
 * Registered by Laravel's listener discovery (typed handle()).
 */
class SendPaymentReceiptOnWhatsApp
{
    public function __construct(private WhatsApp $whatsApp) {}

    public function handle(PaymentCollected $event): ?MessageLog
    {
        $payment = $event->payment;
        if (! Setting::get('whatsapp.auto_send_invoice') || ! $this->whatsApp->isAutomatic()) {
            return null;
        }

        $payment->loadMissing('customer', 'allocations.invoice');
        $to = Phone::normalize($payment->customer?->mobile);
        if (! Phone::isValidIndianMobile($to)) {
            return null;
        }

        $template = (string) (Setting::get('whatsapp.template_payment_receipt') ?: 'payment_receipt');
        if (MessageLog::query()->where('related_type', $payment->getMorphClass())->where('related_id', $payment->getKey())
            ->where('template', $template)->where('status', '!=', 'failed')->exists()) {
            return null;
        }

        $numbers = $payment->allocations->map(fn ($a) => $a->invoice?->number)->filter()->unique()->implode(', ');

        return $this->whatsApp->sendTemplate(
            $to,
            $template,
            [$payment->customer->name, Money::format($payment->amount_paise), $numbers ?: 'your account', Money::format($event->balancePaise)],
            null,
            null,
            $payment,
            ['category' => 'utility'],
        );
    }
}
