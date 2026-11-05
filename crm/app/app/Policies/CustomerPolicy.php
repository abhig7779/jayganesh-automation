<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/** customers.view lets a role look up and read customers; customers.manage lets it add and edit them. */
class CustomerPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false; // a switched-off login can't use tokens either
    }

    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage');
    }

    public function archive(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage');
    }
}
