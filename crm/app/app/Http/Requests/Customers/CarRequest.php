<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Models\CustomerCar;
use Illuminate\Foundation\Http\FormRequest;

/** Used for POST /customers/{id}/cars and PATCH /cars/{id} (on PATCH every field is optional). */
class CarRequest extends FormRequest
{
    public function authorize(): bool
    {
        $car = $this->route('car');
        $customer = $car instanceof CustomerCar ? $car->customer : $this->route('customer');

        return $customer instanceof Customer && $this->user()->can('update', $customer);
    }

    public function rules(): array
    {
        $sometimes = $this->route('car') instanceof CustomerCar ? 'sometimes' : null;

        return [
            'make' => array_filter([$sometimes, 'required', 'string', 'max:60']),
            'model' => array_filter([$sometimes, 'required', 'string', 'max:80']),
            'variant' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'reg_no' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'colour' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
