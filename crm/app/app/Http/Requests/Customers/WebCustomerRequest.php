<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Support\Phone;

/**
 * The web Add / Edit customer form. Same rules as the API (CustomerRequest), plus an optional
 * first car on Add, and a duplicate message in plain words with the existing customer's name
 * (UX-FLOWS §0.7): "This number already belongs to Rahul Pawar."
 */
class WebCustomerRequest extends CustomerRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge([
            'whatsapp_opt_in' => $this->boolean('whatsapp_opt_in'),
            'car' => trim((string) $this->input('car')) ?: null,
        ]);
        foreach (['email', 'area', 'alt_mobile', 'notes'] as $optional) {
            if ($this->has($optional) && trim((string) $this->input($optional)) === '') {
                $this->merge([$optional => null]);
            }
        }
    }

    public function rules(): array
    {
        $rules = parent::rules();
        // The web form always sends every field, so they are required on edit too.
        $rules['name'] = ['required', 'string', 'max:120'];
        $rules['mobile'] = array_values(array_filter($rules['mobile'], fn ($r) => $r !== 'sometimes'));

        if (! $this->route('customer') instanceof Customer) {
            $rules += [
                'car' => ['nullable', 'string', 'max:120'],
                'car_make' => ['nullable', 'string', 'max:60'],
                'reg_no' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 \-]+$/'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        $existing = Phone::isValidIndianMobile($this->input('mobile'))
            ? Customer::query()->where('mobile', $this->input('mobile'))->first()
            : null;

        return [
            'mobile.unique' => $existing
                ? "This number already belongs to {$existing->name}".($existing->isArchived() ? ' (archived)' : '').'.'
                : 'A customer with this mobile number already exists.',
            'mobile.required' => 'Enter the customer’s mobile number.',
            'name.required' => 'Enter the customer’s name.',
            'reg_no.regex' => 'Car number can only have letters, numbers and spaces.',
        ];
    }

    public function attributes(): array
    {
        return ['reg_no' => 'car number', 'alt_mobile' => 'second mobile'];
    }
}
