<?php

namespace App\Listeners\Reminders;

use App\Events\InvoiceSaved;
use App\Services\Reminders\ReminderService;
use Throwable;

/**
 * Saved bill → service check-up reminders from the matching rules, plus a Google review request
 * for a paid bill. Found by Laravel's listener discovery. A failure here never breaks billing.
 */
class CreateRemindersForInvoice
{
    public function __construct(private readonly ReminderService $reminders) {}

    public function handle(InvoiceSaved $event): void
    {
        if (empty($event->invoice->id)) {
            return;
        }

        try {
            $this->reminders->forInvoice($event->invoice->fresh() ?? $event->invoice);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
