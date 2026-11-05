<?php

namespace App\Listeners;

use App\Contracts\BillForWhatsApp;
use App\Events\InvoiceSaved;
use App\Models\MessageLog;
use App\Models\Setting;
use App\Services\WhatsApp\InvoiceMessage;
use App\Services\WhatsApp\WhatsApp;

/**
 * Instant e-bill (ARCHITECTURE.md §2). Registered by Laravel's listener discovery (typed handle()).
 *
 * Cloud API mode + "send automatically" on: writes a queued log and sends right after the response.
 * Manual mode: does nothing; the bill screen shows "Send on WhatsApp" (manualLink) instead.
 */
class SendInvoiceOnWhatsApp
{
    public function __construct(private WhatsApp $whatsApp) {}

    public function handle(InvoiceSaved $event): void
    {
        $this->send(InvoiceMessage::fromInvoice($event->invoice));
    }

    public function send(BillForWhatsApp $bill): ?MessageLog
    {
        if (! Setting::get('whatsapp.auto_send_invoice') || ! $this->whatsApp->isAutomatic()) {
            return null;
        }
        if ($bill->isCancelled() || ! ($to = $bill->to())) {
            return null;
        }

        $template = (string) Setting::get('whatsapp.template_invoice');
        $related = $bill->related();

        // InvoiceSaved can fire again when a bill is edited: one e-bill per bill unless resent by hand.
        if ($related && MessageLog::query()
            ->where('related_type', $related->getMorphClass())->where('related_id', $related->getKey())
            ->where('template', $template)->where('status', '!=', 'failed')->exists()) {
            return null;
        }

        return $this->whatsApp->queueTemplate(
            $to,
            $template,
            [$bill->customerName(), $bill->number(), $bill->totalFormatted(), $bill->statusText()],
            // With a real invoice the PDF is rendered inside the job, after the response. The
            // template has a document header, so with no PDF at all the job fails it as no_document.
            $related ? null : $bill->pdfPath(),
            $bill->pdfName(),
            $related,
            ['category' => 'utility', 'document_pending' => true],
        );
    }
}
