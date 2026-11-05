<?php

namespace App\Listeners\Reminders;

use App\Events\PaymentCollected;
use App\Models\Invoice;
use App\Services\Reminders\ReminderService;
use Throwable;

/**
 * A bill that was on credit becomes paid when the due is collected later: ask for the review
 * then (the 90-day cap still applies).
 */
class ReviewAfterDueCleared
{
    public function __construct(private readonly ReminderService $reminders) {}

    public function handle(PaymentCollected $event): void
    {
        try {
            $ids = $event->payment->allocations()->pluck('invoice_id')
                ->push($event->payment->invoice_id)->filter()->unique();
            Invoice::query()->whereIn('id', $ids)->where('status', Invoice::STATUS_PAID)->get()
                ->each(fn (Invoice $i) => $this->reminders->reviewFor($i, now()));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
