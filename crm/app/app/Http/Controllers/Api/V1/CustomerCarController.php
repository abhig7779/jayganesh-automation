<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CarRequest;
use App\Http\Resources\CustomerCarResource;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Services\Customers\CustomerService;
use Illuminate\Http\JsonResponse;

class CustomerCarController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function store(CarRequest $request, Customer $customer): JsonResponse
    {
        $car = $this->customers->addCar($customer, $request->validated(), $request->user());

        return (new CustomerCarResource($car))->response()->setStatusCode(201);
    }

    public function update(CarRequest $request, CustomerCar $car): CustomerCarResource
    {
        return new CustomerCarResource($this->customers->updateCar($car, $request->validated()));
    }
}
