<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/** Who can see and change bills. Sales see only their own bills unless they have reports.all_staff. */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view')
            && ($user->can('reports.all_staff') || $invoice->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.cancel') && ! $invoice->isCancelled();
    }

    public function returnItems(User $user, Invoice $invoice): bool
    {
        return $this->cancel($user, $invoice);
    }
}
