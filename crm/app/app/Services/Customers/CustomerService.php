<?php

namespace App\Services\Customers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/** Every customer write goes through here, so the web pages and /api/v1 behave the same. */
class CustomerService
{
    private const CUSTOMER_FIELDS = [
        'name', 'alt_mobile', 'email', 'area', 'source', 'whatsapp_opt_in', 'tags', 'notes',
    ];

    private const CAR_FIELDS = ['make', 'model', 'variant', 'year', 'reg_no', 'colour', 'notes'];

    /**
     * The counter's "type the number" flow: returns the customer with this mobile, or creates
     * one. Calling it again with the same number (in any format) returns the same row and does
     * not overwrite what is already saved. An archived customer who comes back is restored.
     */
    public function findOrCreateByMobile(string $mobile, array $attrs, User $by): Customer
    {
        $e164 = self::validMobile($mobile);

        if ($existing = Customer::query()->where('mobile', $e164)->first()) {
            return $this->restoreIfArchived($existing);
        }

        if (blank($attrs['name'] ?? null)) {
            throw ValidationException::withMessages(['name' => 'Enter the customer’s name.']);
        }

        try {
            return Customer::create(Arr::only($attrs, self::CUSTOMER_FIELDS) + [
                'mobile' => $e164,
                'branch_id' => $attrs['branch_id'] ?? $by->branch_id ?? Branch::query()->value('id'),
                'created_by' => $by->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two counters saved the same new number at once: the other one won, use it.
            return Customer::query()->where('mobile', $e164)->firstOrFail();
        }
    }

    public function update(Customer $customer, array $attrs): Customer
    {
        $data = Arr::only($attrs, [...self::CUSTOMER_FIELDS, 'mobile']);
        if (array_key_exists('mobile', $data)) {
            $data['mobile'] = self::validMobile((string) $data['mobile']);
        }
        $customer->update($data);

        return $customer;
    }

    public function addCar(Customer $customer, array $attrs, User $by): CustomerCar
    {
        return $customer->cars()->create(Arr::only($attrs, self::CAR_FIELDS) + ['created_by' => $by->id]);
    }

    public function updateCar(CustomerCar $car, array $attrs): CustomerCar
    {
        $car->update(Arr::only($attrs, self::CAR_FIELDS));

        return $car;
    }

    /** Hides the customer from search and lists. Bills and history stay; nothing is deleted. */
    public function archive(Customer $customer, User $by): Customer
    {
        if (! $customer->isArchived()) {
            $customer->archived_at = now();
            $customer->save();
            activity('customers')->performedOn($customer)->causedBy($by)->event('archived')->log('archived');
        }

        return $customer;
    }

    private function restoreIfArchived(Customer $customer): Customer
    {
        if ($customer->isArchived()) {
            $customer->archived_at = null;
            $customer->save();
        }

        return $customer;
    }

    /** @throws ValidationException */
    public static function validMobile(string $mobile): string
    {
        $e164 = Phone::normalize($mobile);
        if (! Phone::isValidIndianMobile($e164)) {
            throw ValidationException::withMessages(['mobile' => 'Enter a valid 10-digit Indian mobile number.']);
        }

        return $e164;
    }
}
