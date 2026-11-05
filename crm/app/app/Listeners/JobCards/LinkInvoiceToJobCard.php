<?php

namespace App\Listeners\JobCards;

use App\Events\InvoiceSaved;
use App\Models\JobCard;
use App\Services\JobCards\JobCardService;

/**
 * When billing saves a bill that started from a job card's "Make bill", store the bill id on the
 * job card. Auto-discovered by Laravel;.
 * The "Make bill" button leaves the job card id in the session; the invoice itself carries no job card column.
 */
class LinkInvoiceToJobCard
{
    public function __construct(private readonly JobCardService $service) {}

    public function handle(InvoiceSaved $event): void
    {
        $invoice = $event->invoice;
        $cardId = app()->bound('session') ? session('jobcards.billing_job_card_id') : null;
        if (! $cardId) {
            return;
        }

        $card = JobCard::query()->find($cardId);
        if (! $card || (int) $card->customer_id !== (int) $invoice->customer_id) {
            return; // the counter billed someone else in between: don't link the wrong bill
        }

        $this->service->linkInvoice($card, (int) $invoice->id, auth()->user());
        if (app()->bound('session')) {
            session()->forget('jobcards.billing_job_card_id');
        }
    }
}
