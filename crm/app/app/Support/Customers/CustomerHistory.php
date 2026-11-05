<?php

namespace App\Support\Customers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's bills, quotations and job cards for the profile's History tab. Each row links to
 * its own screen when the viewer may open it; a section is null when the viewer can't see it.
 *
 * Rows: type, number, date (display), at (unix time, for sorting), paise (null = not shown),
 * status, status_label, url.
 */
class CustomerHistory
{
    public function __construct(private readonly Customer $customer, private readonly ?User $viewer = null) {}

    /** Bills, newest first. Sales see only their own unless they have reports.all_staff. */
    public function bills(int $limit = 20): ?array
    {
        if (! Schema::hasTable('invoices') || ($this->viewer && ! $this->viewer->can('invoices.view'))) {
            return null;
        }

        return $this->safe(fn () => Invoice::query()->where('customer_id', $this->customer->id)
            ->when($this->viewer, fn ($q) => $q->visibleTo($this->viewer))
            ->latest('id')->limit($limit)->get()
            ->map(fn (Invoice $i) => [
                'type' => 'Bill',
                'number' => $i->number,
                'date' => $i->created_at?->timezone('Asia/Kolkata')->format('j M Y'),
                'at' => $i->created_at?->getTimestamp() ?? 0,
                'paise' => (int) $i->total_paise,
                'status' => $i->status === Invoice::STATUS_PARTIAL ? 'part_paid' : $i->status,
                'status_label' => $i->statusLabel(),
                'url' => route('bills.show', $i),
            ])->all());
    }

    /** Every version shown once (the latest revision), newest first. */
    public function quotations(int $limit = 20): ?array
    {
        if (! Schema::hasTable('quotations') || ($this->viewer && ! $this->viewer->can('quotations.view'))) {
            return null;
        }

        return $this->safe(fn () => Quotation::query()->where('customer_id', $this->customer->id)->whereNull('revised_to_id')
            ->latest('id')->limit($limit)->get()
            ->map(fn (Quotation $q) => [
                'type' => 'Quote',
                'number' => $q->number,
                'date' => $q->created_at?->timezone('Asia/Kolkata')->format('j M Y'),
                'at' => $q->created_at?->getTimestamp() ?? 0,
                'paise' => (int) $q->total_paise,
                'status' => $q->status,
                'status_label' => $q->statusLabel(),
                'url' => ! $this->viewer || Quotation::seesAll($this->viewer) || $q->assigned_to === $this->viewer->id
                    ? route('quotations.show', $q) : null,
            ])->all());
    }

    /** Car-in visits. The amount shows only to people who may see job card prices. */
    public function jobCards(int $limit = 20): ?array
    {
        if (! Schema::hasTable('job_cards') || ($this->viewer && ! $this->viewer->can('jobcards.view'))) {
            return null;
        }
        $prices = ! $this->viewer || Gate::forUser($this->viewer)->allows('seePrices', JobCard::class);

        return $this->safe(fn () => JobCard::query()->where('customer_id', $this->customer->id)
            ->with(['car', 'items'])->latest('id')->limit($limit)->get()
            ->map(fn (JobCard $j) => [
                'type' => 'Job card',
                'number' => $j->number.($j->plate() ? ' · '.$j->plate() : ''),
                'date' => $j->created_at?->timezone('Asia/Kolkata')->format('j M Y'),
                'at' => $j->created_at?->getTimestamp() ?? 0,
                'paise' => $prices ? $j->totalPaise() : null,
                'status' => $j->status,
                'status_label' => $j->label(),
                'url' => ! $this->viewer || Gate::forUser($this->viewer)->allows('view', $j) ? route('jobcards.show', $j) : null,
            ])->all());
    }

    /** What the customer still owes across unpaid bills. */
    public function duePaise(): int
    {
        if (! Schema::hasTable('invoices')) {
            return 0;
        }

        return (int) ($this->safe(fn () => Invoice::query()->where('customer_id', $this->customer->id)->unpaid()->sum('due_paise')) ?: 0);
    }

    private function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (QueryException $e) {
            report($e);

            return [];
        }
    }
}
