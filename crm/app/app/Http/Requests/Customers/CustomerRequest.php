<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used for POST /customers and PATCH /customers/{id} (on PATCH every field is optional). */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            ? $this->user()->can('update', $customer)
            : $this->user()->can('create', Customer::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['mobile', 'alt_mobile'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => Phone::normalize((string) $this->input($field))]);
            }
        }
        if (is_string($this->input('tags'))) {
            $this->merge(['tags' => array_values(array_filter(array_map('trim', explode(',', $this->input('tags')))))]);
        }
    }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $sometimes = $customer instanceof Customer ? 'sometimes' : null;
        $indianMobile = function (string $attribute, mixed $value, \Closure $fail) {
            if (! Phone::isValidIndianMobile($value)) {
                $fail('Enter a valid 10-digit Indian mobile number.');
            }
        };

        return [
            'name' => array_filter([$sometimes, 'required', 'string', 'max:120']),
            'mobile' => array_filter([
                $sometimes, 'required', 'string', $indianMobile,
                Rule::unique('customers', 'mobile')->ignore($customer?->id),
            ]),
            'alt_mobile' => ['nullable', 'string', $indianMobile],
            'email' => ['nullable', 'email', 'max:150'],
            'area' => ['nullable', 'string', 'max:120'],
            'source' => ['sometimes', 'string', Rule::in(Customer::SOURCES)],
            'whatsapp_opt_in' => ['sometimes', 'boolean'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['mobile.unique' => 'A customer with this mobile number already exists.'];
    }
}
