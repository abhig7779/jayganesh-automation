<?php

namespace App\Http\Controllers\Reminders;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ReminderRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Owner-only: which bill lines start a check-up reminder, when, and what the message says. */
class ReminderRuleController extends Controller
{
    public function index(): View
    {
        return view('reminders.rules', [
            'rules' => ReminderRule::query()->with(['product', 'category'])->orderByDesc('is_active')->orderBy('name')->get(),
            'rule' => new ReminderRule(['match_type' => 'name', 'is_active' => true, 'offsets_days' => [180]]),
            ...$this->options(),
        ]);
    }

    public function edit(ReminderRule $rule): View
    {
        return view('reminders.rules', [
            'rules' => ReminderRule::query()->with(['product', 'category'])->orderByDesc('is_active')->orderBy('name')->get(),
            'rule' => $rule,
            ...$this->options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rule = ReminderRule::query()->create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('reminders.rules.index')->with('status', "Rule “{$rule->name}” added. New bills from now on will use it.");
    }

    public function update(Request $request, ReminderRule $rule): RedirectResponse
    {
        $rule->update($this->validated($request));

        return redirect()->route('reminders.rules.index')->with('status', "Rule “{$rule->name}” saved. Reminders already made keep their dates.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'match_type' => ['required', Rule::in(array_keys(ReminderRule::MATCH_TYPES))],
            'keywords' => ['nullable', 'required_if:match_type,name', 'string', 'max:200'],
            'product_id' => ['nullable', 'required_if:match_type,product', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'required_if:match_type,category', 'integer', 'exists:categories,id'],
            'offsets' => ['required', 'string', 'max:60', 'regex:/^\s*\d{1,4}(\s*,\s*\d{1,4}){0,5}\s*$/'],
            'message' => ['required', 'string', 'max:1000'],
        ], [
            'keywords.required_if' => 'Type at least one word, for example “ceramic”.',
            'product_id.required_if' => 'Pick the product.',
            'category_id.required_if' => 'Pick the category.',
            'offsets.regex' => 'Days after the bill, separated by commas, for example 180, 365.',
        ]);

        $offsets = collect(explode(',', $data['offsets']))->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d > 0 && $d <= 3650)->unique()->sort()->values()->all();

        return [
            'name' => $data['name'],
            'match_type' => $data['match_type'],
            'keywords' => $data['match_type'] === 'name' ? $data['keywords'] : null,
            'product_id' => $data['match_type'] === 'product' ? $data['product_id'] : null,
            'category_id' => $data['match_type'] === 'category' ? $data['category_id'] : null,
            'services_only' => $request->boolean('services_only'),
            'offsets_days' => $offsets ?: [180],
            'message' => $data['message'],
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function options(): array
    {
        return [
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'matchTypes' => ReminderRule::MATCH_TYPES,
        ];
    }
}
