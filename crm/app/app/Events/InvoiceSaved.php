<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once a new bill has been committed (crm/CONTRACTS.md hand-off 1).
 * WhatsApp listens to send the e-bill (check $invoice->send_whatsapp); quotations listens to
 * mark quotes/leads won (by $invoice->quotation_id or the same customer within 30 days).
 */
class InvoiceSaved implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Invoice $invoice) {}
}
