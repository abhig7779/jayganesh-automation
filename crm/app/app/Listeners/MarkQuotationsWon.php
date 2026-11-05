<?php

namespace App\Listeners;

use App\Events\InvoiceSaved;
use App\Services\Quotations\QuotationService;

/**
 * Auto-win (UX-FLOWS §3.2 step 6): when a bill is saved, the linked quotation, or else the
 * customer's open quotes and leads from the last 30 days, are marked Won and stop following
 * up. Found by Laravel's listener discovery (typed handle()).
 */
class MarkQuotationsWon
{
    public function __construct(private readonly QuotationService $quotations) {}

    public function handle(InvoiceSaved $event): void
    {
        $invoice = $event->invoice;
        if (empty($invoice->id)) {
            return;
        }

        $this->quotations->autoWin(
            (int) $invoice->id,
            isset($invoice->customer_id) ? (int) $invoice->customer_id : null,
            ! empty($invoice->quotation_id) ? (int) $invoice->quotation_id : null,
        );
    }
}
