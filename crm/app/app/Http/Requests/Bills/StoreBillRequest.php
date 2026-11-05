<?php

namespace App\Http\Requests\Bills;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\GstCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shape of the New bill / POS save. Business rules (limits, stock, totals) live in InvoiceService. */
class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    public function rules(): array
    {
        return [
            'draft_key' => ['nullable', 'string', 'max:64'],
            'customer_id' => ['nullable', 'integer'],
            'customer_car_id' => ['nullable', 'integer'],
            'customer' => ['nullable', 'array'],
            'customer.mobile' => ['nullable', 'string', 'max:20'],
            'customer.name' => ['nullable', 'string', 'max:120'],
            'customer.source' => ['nullable', Rule::in(Customer::SOURCES)],
            'customer.car' => ['nullable', 'array'],
            'customer.car.make' => ['nullable', 'string', 'max:60'],
            'customer.car.model' => ['nullable', 'string', 'max:80'],
            'customer.car.reg_no' => ['nullable', 'string', 'max:20'],
            'quotation_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:160'],
            'items.*.hsn' => ['nullable', 'string', 'max:10'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.unit_price_paise' => ['nullable', 'integer', 'min:0'],
            'items.*.gst_rate' => ['nullable', 'numeric', Rule::in(GstCalculator::RATES)],
            'items.*.discount_paise' => ['nullable', 'integer', 'min:0'],
            'bill_discount_paise' => ['nullable', 'integer', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:60'],
            'payments' => ['nullable', 'array', 'max:4'],
            'payments.*.mode' => ['required', Rule::in(Payment::MODES)],
            'payments.*.amount_paise' => ['required', 'integer', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:80'],
            'send_whatsapp' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            // IGST and B2B bills (audit M9): 2-digit state code, and the buyer's GSTIN.
            'place_of_supply' => ['nullable', 'regex:/^\d{2}$/'],
            'customer_gstin' => ['nullable', 'regex:/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($gstin = $this->input('customer_gstin'))) {
            $this->merge(['customer_gstin' => strtoupper(preg_replace('/\s+/', '', $gstin)) ?: null]);
        }
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
            'customer_gstin.regex' => 'That GSTIN doesn’t look right. It has 15 characters, like 27ABCDE1234F1Z5.',
            'place_of_supply.regex' => 'Place of supply is the 2-digit state code, like 27 for Maharashtra.',
        ];
    }
}
