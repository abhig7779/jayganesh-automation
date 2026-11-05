<?php

namespace App\Listeners\JobCards;

use App\Events\InvoiceSaved;
use App\Models\JobCard;
use App\Services\JobCards\JobCardService;

/**
 * When billing saves a bill that started from a job card's "Make bill", store the bill id on the
 * job card. Auto-discovered by Laravel; it does nothing until billing (and InvoiceSaved) is merged.
 * Billing may also put job_card_id on the invoice itself; that wins over the session hint.
 */
class LinkInvoiceToJobCard
{
    public function __construct(private readonly JobCardService $service) {}

    public function handle(InvoiceSaved $event): void
    {
        $invoice = $event->invoice ?? null;
        if (! $invoice || ! isset($invoice->id)) {
            return;
        }

        $cardId = $invoice->job_card_id ?? (app()->bound('session') ? session('jobcards.billing_job_card_id') : null);
        if (! $cardId) {
            return;
        }

        $card = JobCard::query()->find($cardId);
        if (! $card || (int) $card->customer_id !== (int) ($invoice->customer_id ?? 0)) {
            return; // the counter billed someone else in between: don't link the wrong bill
        }

        $this->service->linkInvoice($card, (int) $invoice->id, auth()->user());
        if (app()->bound('session')) {
            session()->forget('jobcards.billing_job_card_id');
        }
    }
}
