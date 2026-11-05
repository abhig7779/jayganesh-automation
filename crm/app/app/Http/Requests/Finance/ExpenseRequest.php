<?php

namespace App\Http\Requests\Finance;

use App\Models\Expense;
use App\Services\Finance\Period;
use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.manage');
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.Period::today()->toDateString(), 'after:'.Period::today()->subYears(2)->toDateString()],
            'expense_category_id' => ['required', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'amount' => ['required', 'string', 'max:20', function (string $attr, mixed $value, Closure $fail) {
                try {
                    $paise = Money::toPaise((string) $value);
                } catch (\InvalidArgumentException) {
                    $fail('Type the amount in rupees, like 450 or 1,250.50.');

                    return;
                }
                if ($paise <= 0) {
                    $fail('The amount must be more than ₹0.');
                } elseif ($paise > 1_00_00_000_00) {
                    $fail('That amount looks too big. Check the number.');
                }
            }],
            'mode' => ['required', Rule::in(array_keys(Expense::MODES))],
            'paid_to' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:8192'],
            'remove_receipt' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'The date can\'t be in the future.',
            'date.after' => 'That date is too far back.',
            'expense_category_id.required' => 'Pick what the money was spent on.',
            'amount.required' => 'Type the amount.',
            'receipt.mimes' => 'The receipt must be a photo (JPG, PNG, WEBP, HEIC) or a PDF.',
            'receipt.max' => 'The receipt file is too big (8 MB at most).',
        ];
    }

    /** @return array<string,mixed> columns for the expenses table */
    public function expenseData(): array
    {
        return [
            'date' => $this->input('date'),
            'expense_category_id' => (int) $this->input('expense_category_id'),
            'amount_paise' => Money::toPaise((string) $this->input('amount')),
            'mode' => $this->input('mode'),
            'paid_to' => $this->filled('paid_to') ? trim($this->input('paid_to')) : null,
            'note' => $this->filled('note') ? trim($this->input('note')) : null,
        ];
    }
}
