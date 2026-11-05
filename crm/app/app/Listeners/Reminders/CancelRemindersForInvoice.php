<?php

namespace App\Listeners\Reminders;

use App\Events\InvoiceCancelled;
use App\Services\Reminders\ReminderService;
use Throwable;

/** Cancelled bill → its open reminders are cancelled (and any check-ups it had replaced come back). */
class CancelRemindersForInvoice
{
    public function __construct(private readonly ReminderService $reminders) {}

    public function handle(InvoiceCancelled $event): void
    {
        try {
            $this->reminders->cancelForInvoice($event->invoice);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
