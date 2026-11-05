<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * STUB from the WhatsApp branch. The billing branch owns the real event
 * (`InvoiceSaved(Invoice $invoice)`, fired after the bill transaction commits).
 * LEAD: when merging, keep billing's version of this file and delete this stub. The WhatsApp
 * listener only reads the public `$invoice` property, so both versions work with it.
 */
class InvoiceSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(public object $invoice) {}
}
