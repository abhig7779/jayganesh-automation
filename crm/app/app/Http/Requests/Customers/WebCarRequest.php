<?php

namespace App\Http\Requests\Customers;

use App\Support\CarModels;

/**
 * Add / edit a car from the customer profile sheet. The picker sends one `car` text ("Hyundai
 * Creta", or just "Creta") plus the picked make; this splits it into make + model, then the
 * API's CarRequest rules apply unchanged.
 */
class WebCarRequest extends CarRequest
{
    protected function prepareForValidation(): void
    {
        $typed = trim((string) $this->input('car'));
        if ($typed !== '') {
            [$make, $model] = CarModels::split($this->input('car_make'), $typed);
            $this->merge(['make' => $make, 'model' => $model]);
        }
        foreach (['reg_no', 'year', 'colour', 'variant'] as $optional) {
            if ($this->has($optional) && trim((string) $this->input($optional)) === '') {
                $this->merge([$optional => null]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'make.required' => 'Pick or type the car model.',
            'model.required' => 'Pick or type the car model.',
            'reg_no.regex' => 'Car number can only have letters, numbers and spaces.',
        ];
    }

    public function attributes(): array
    {
        return ['reg_no' => 'car number'];
    }
}
