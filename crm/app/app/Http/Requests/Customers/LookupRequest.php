<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;

class LookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Customer::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('mobile')) {
            $this->merge(['mobile' => Phone::normalize((string) $this->input('mobile'))]);
        }
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (! Phone::isValidIndianMobile($value)) {
                    $fail('Enter a valid 10-digit Indian mobile number.');
                }
            }],
        ];
    }
}
