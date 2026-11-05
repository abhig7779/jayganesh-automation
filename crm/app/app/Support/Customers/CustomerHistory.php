<?php

namespace App\Support\Customers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's bills and quotations for the profile's History tab. Billing and quotations
 * merge later, so each query is guarded and returns null while its module is missing.
 * LEAD: "WIRE:" marks the column names to check after the merge.
 */
class CustomerHistory
{
    /**
     * With a viewer, bills and quotes are limited to what that person may open (audit M1):
     * Sales and Marketing see their own; everyone else gets only a count (hiddenCount).
     */
    public function __construct(private readonly Customer $customer, private readonly ?User $viewer = null) {}

    /** How many of this customer's bills and quotes are left out for this viewer. */
    public function hiddenCount(): int
    {
        if (! $this->viewer) {
            return 0;
        }

        return (int) $this->safe(function () {
            $bills = Invoice::query()->where('customer_id', $this->customer->id);
            $quotes = Quotation::query()->where('customer_id', $this->customer->id);

            return ($bills->count() - (clone $bills)->visibleTo($this->viewer)->count())
                + ($quotes->count() - (clone $quotes)->visibleTo($this->viewer)->count());
        });
    }

    /** @return array<int, array{type:string,number:string,date:string,paise:int,status:?string,url:?string}>|null */
    public function bills(int $limit = 20): ?array
    {
        $class = 'App\\Models\\Invoice';
        if (! class_exists($class) || ! Schema::hasTable('invoices')) {
            return null;
        }

        // WIRE: invoices(customer_id, number, total_paise, status, created_at)
        return $this->safe(fn () => $class::query()->where('customer_id', $this->customer->id)
            ->when($this->viewer, fn ($q) => $q->visibleTo($this->viewer))
            ->latest()->limit($limit)->get()
            ->map(fn ($i) => [
                'type' => 'Bill',
                'number' => $i->number ?? '#'.$i->id,
                'date' => $i->created_at?->timezone('Asia/Kolkata')->format('j M Y'),
                'paise' => (int) $i->total_paise,
                'status' => $i->status ?? null,
                'url' => Route::has('bills.show') ? route('bills.show', $i) : null,
            ])->all());
    }

    public function quotations(int $limit = 20): ?array
    {
        $class = 'App\\Models\\Quotation';
        if (! class_exists($class) || ! Schema::hasTable('quotations')) {
            return null;
        }

        // WIRE: quotations(customer_id, number, total_paise, status, created_at)
        return $this->safe(fn () => $class::query()->where('customer_id', $this->customer->id)
            ->when($this->viewer, fn ($q) => $q->visibleTo($this->viewer))
            ->latest()->limit($limit)->get()
            ->map(fn ($q) => [
                'type' => 'Quote',
                'number' => $q->number ?? '#'.$q->id,
                'date' => $q->created_at?->timezone('Asia/Kolkata')->format('j M Y'),
                'paise' => (int) $q->total_paise,
                'status' => $q->status ?? null,
                'url' => Route::has('quotations.show') ? route('quotations.show', $q) : null,
            ])->all());
    }

    /** Pending due across unpaid bills; 0 until billing merges. */
    public function duePaise(): int
    {
        $class = 'App\\Models\\Invoice';
        if (! class_exists($class) || ! Schema::hasTable('invoices') || ! Schema::hasColumn('invoices', 'due_paise')) {
            return 0;
        }

        return (int) ($this->safe(fn () => $class::query()->where('customer_id', $this->customer->id)
            ->where('status', '!=', 'cancelled')->sum('due_paise')) ?? 0);
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
