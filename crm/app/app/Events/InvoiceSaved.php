<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * STUB from the quotations branch. MERGE NOTE (for the lead): billing owns the real
 * `App\Events\InvoiceSaved(Invoice $invoice)`; keep billing's file and delete this one.
 * The quotations listener (App\Listeners\MarkQuotationsWon) only reads `id`, `customer_id`,
 * `quotation_id` and `total_paise` from `$invoice`, so it works with either.
 */
class InvoiceSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(public object $invoice) {}
}
