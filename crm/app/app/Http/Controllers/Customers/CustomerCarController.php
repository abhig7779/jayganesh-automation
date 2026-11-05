<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\WebCarRequest;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Services\Customers\CustomerService;

/** Add / edit a car from the customer profile (bottom sheet). */
class CustomerCarController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function store(WebCarRequest $request, Customer $customer)
    {
        $car = $this->customers->addCar($customer, $request->validated(), $request->user());

        return redirect()->route('customers.show', [$customer, 'tab' => 'cars'])->with('status', $car->label().' added.');
    }

    public function update(WebCarRequest $request, Customer $customer, CustomerCar $car)
    {
        $this->customers->updateCar($car, $request->validated());

        return redirect()->route('customers.show', [$customer, 'tab' => 'cars'])->with('status', 'Car saved.');
    }
}
