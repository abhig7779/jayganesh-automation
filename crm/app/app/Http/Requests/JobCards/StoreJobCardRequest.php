<?php

namespace App\Http\Requests\JobCards;

use App\Models\JobCard;
use App\Support\ClientKey;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJobCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', JobCard::class);
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($i) => is_array($i) && (filled($i['product_id'] ?? null) || filled($i['description'] ?? null)))
            ->values()->all();

        $this->merge([
            'items' => $items,
            'mobile' => $this->filled('mobile') ? Phone::normalize((string) $this->input('mobile')) : null,
            'odometer' => $this->filled('odometer') ? preg_replace('/\D+/', '', (string) $this->input('odometer')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'mobile' => ['nullable', 'required_without:customer_id', 'string'],
            'name' => ['nullable', 'required_without:customer_id', 'string', 'max:120'],
            'customer_car_id' => ['nullable', 'integer', 'exists:customer_cars,id'],
            'car_model' => ['nullable', 'string', 'max:80'],
            'car_reg_no' => ['nullable', 'string', 'max:20'],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'fuel_level' => ['nullable', Rule::in(JobCard::FUEL_LEVELS)],
            'promised_at' => ['nullable', 'date'],
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'bay' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string', 'max:200'],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            'items.*.price' => ['nullable', 'regex:/^\s*(₹\s*)?[\d,]+(\.\d{1,2})?\s*$/u'],
            'client_key' => ClientKey::RULE,
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one work item.',
            'mobile.required_without' => 'Enter the customer’s mobile number.',
            'name.required_without' => 'Enter the customer’s name.',
            'items.*.price.regex' => 'Price must be an amount like 4999 or 4,999.50.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if (! $this->filled('customer_id') && $this->filled('mobile') && ! Phone::isValidIndianMobile($this->input('mobile'))) {
                $v->errors()->add('mobile', 'Mobile number needs 10 digits.');
            }
            if (! $this->filled('customer_car_id') && ! $this->filled('car_model')) {
                $v->errors()->add('customer_car_id', 'Pick the car or type its model.');
            }
        }];
    }

    /** Validated data in the shape JobCardService::create() takes (paise, UTC). */
    public function forService(): array
    {
        $d = $this->validated();
        $d['items'] = array_map(fn (array $i) => [
            'product_id' => $i['product_id'] ?? null,
            'description' => $i['description'] ?? null,
            'qty' => (int) ($i['qty'] ?? 1),
            'price_paise' => filled($i['price'] ?? null) ? Money::toPaise((string) $i['price']) : null,
        ], $d['items']);
        // The form's datetime-local is shop time (IST); the database stores UTC.
        $d['promised_at'] = filled($d['promised_at'] ?? null)
            ? Carbon::parse($d['promised_at'], 'Asia/Kolkata')->utc()
            : null;

        return $d;
    }
}
