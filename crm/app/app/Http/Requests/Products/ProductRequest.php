<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use App\Services\Billing\GstCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create / edit a product. Prices are typed in rupees ("4,999" or "4999.50") and stored in paise.
 * Price fields need prices.manage when editing; cost needs reports.margins as well.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.manage');
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => is_string($v) ? trim(str_replace([',', '₹', ' '], '', $v)) : $v;
        $this->merge([
            'price' => $clean($this->input('price')),
            'mrp' => $clean($this->input('mrp')),
            'cost' => $clean($this->input('cost')),
            'sku' => $this->filled('sku') ? strtoupper(trim($this->input('sku'))) : null,
            'hsn' => $this->filled('hsn') ? preg_replace('/\D/', '', $this->input('hsn')) : null,
        ]);
    }

    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $money = ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'];

        return [
            'name' => ['required', 'string', 'max:160'],
            'aliases' => ['nullable', 'string', 'max:500'],
            'sku' => ['nullable', 'string', 'max:40', Rule::unique('products', 'sku')->ignore($product?->id)],
            'brand_name' => ['nullable', 'string', 'max:80'],
            'category_name' => ['nullable', 'string', 'max:80'],
            'type' => ['required', Rule::in([Product::TYPE_PRODUCT, Product::TYPE_SERVICE])],
            'price' => $product ? $money : ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'mrp' => $money,
            'cost' => $money,
            'gst_rate' => ['required', Rule::in(array_map('strval', GstCalculator::RATES))],
            'price_includes_gst' => ['nullable', 'boolean'],
            'hsn' => ['nullable', 'digits_between:4,8'],
            'unit' => ['nullable', 'string', 'max:10'],
            'reorder_level' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'shelf' => ['nullable', 'string', 'max:40'],
            'warranty' => ['nullable', 'string', 'max:80'],
            'compatible_cars' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'opening_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $price = $this->input('price');
            $mrp = $this->input('mrp');
            if (is_numeric($price) && is_numeric($mrp) && (float) $mrp > 0 && (float) $price > (float) $mrp) {
                $validator->errors()->add('price', 'Our price can\'t be more than the normal price (MRP).');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'price.required' => 'Type the selling price.',
            'price.regex' => 'Price should be a number like 4999 or 4999.50.',
            'mrp.regex' => 'MRP should be a number like 5999.',
            'cost.regex' => 'Cost should be a number like 3200.',
            'sku.unique' => 'Another product already has this code.',
            'hsn.digits_between' => 'HSN / SAC should be 4 to 8 digits.',
            'gst_rate.in' => 'Pick a GST rate: 0, 5, 12, 18 or 28%.',
        ];
    }
}
