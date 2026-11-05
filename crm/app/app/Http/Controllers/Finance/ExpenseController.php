<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Finance\Period;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $period = Period::fromRequest($request, 'this_month');
        $filters = [
            'category' => $request->integer('category') ?: null,
            'mode' => array_key_exists((string) $request->query('mode'), Expense::MODES) ? $request->query('mode') : null,
            'q' => trim((string) $request->query('q')) ?: null,
            'cancelled' => $request->boolean('cancelled'),
        ];

        $base = Expense::query()
            ->when($branchId, fn ($q) => $q->where('expenses.branch_id', $branchId))
            ->whereBetween('expenses.date', [$period->fromDate(), $period->toDate()])
            ->when($filters['category'], fn ($q, $c) => $q->where('expenses.expense_category_id', $c))
            ->when($filters['mode'], fn ($q, $m) => $q->where('expenses.mode', $m))
            ->when($filters['q'], fn ($q, $s) => $q->where(fn ($w) => $w->where('expenses.paid_to', 'like', "%$s%")->orWhere('expenses.note', 'like', "%$s%")));

        $live = (clone $base)->whereNull('expenses.cancelled_at');
        $totals = [
            'total_paise' => (int) (clone $live)->sum('amount_paise'),
            'count' => (clone $live)->count(),
            'modes' => (clone $live)->groupBy('mode')->selectRaw('mode, SUM(amount_paise) as total')->pluck('total', 'mode')->map(fn ($v) => (int) $v)->all(),
        ];
        $byCategory = (clone $live)
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->selectRaw('expense_categories.name as name, SUM(expenses.amount_paise) as total, COUNT(*) as n')
            ->orderByDesc('total')->get();

        $expenses = ($filters['cancelled'] ? $base : $live)
            ->with(['category:id,name', 'creator:id,name'])
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(50)->withQueryString();

        return view('finance.expenses.index', [
            'period' => $period,
            'filters' => $filters,
            'totals' => $totals,
            'byCategory' => $byCategory,
            'expenses' => $expenses,
            'categories' => ExpenseCategory::query()->active()->get(),
        ]);
    }

    public function create()
    {
        return view('finance.expenses.create', [
            'categories' => ExpenseCategory::query()->active()->get(),
            'expense' => new Expense(['date' => Period::today()->toDateString(), 'mode' => 'cash']),
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $expense = new Expense($request->expenseData() + [
            'branch_id' => $request->user()->branch_id,
            'created_by' => $request->user()->id,
        ]);
        if ($request->hasFile('receipt')) {
            $expense->receipt_path = $request->file('receipt')->store('expenses/'.now()->format('Y/m'), 'local');
        }
        $expense->save();

        return redirect()->route('finance.expenses.index', ['preset' => 'this_month'])
            ->with('status', 'Expense saved: '.Money::format($expense->amount_paise).' for '.$expense->category->name.'.');
    }

    public function edit(Expense $expense)
    {
        $this->authorizeBranch($expense);
        abort_if($expense->isCancelled(), 404);

        return view('finance.expenses.edit', [
            'categories' => ExpenseCategory::query()->active()->get(),
            'expense' => $expense,
        ]);
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorizeBranch($expense);
        abort_if($expense->isCancelled(), 404);

        $expense->fill($request->expenseData());
        if ($request->boolean('remove_receipt') && $expense->receipt_path) {
            $expense->receipt_path = null; // the old file stays on disk (no hard deletes)
        }
        if ($request->hasFile('receipt')) {
            $expense->receipt_path = $request->file('receipt')->store('expenses/'.now()->format('Y/m'), 'local');
        }
        $expense->save();

        return redirect()->route('finance.expenses.index', ['preset' => 'this_month'])->with('status', 'Expense updated.');
    }

    /** Expenses are cancelled, never deleted, so the books keep a trail. */
    public function cancel(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizeBranch($expense);
        if (! $expense->isCancelled()) {
            $expense->forceFill(['cancelled_at' => now(), 'cancelled_by' => $request->user()->id])->save();
        }

        return back()->with('status', 'Expense of '.Money::format($expense->amount_paise).' cancelled. It no longer counts in totals.');
    }

    public function receipt(Expense $expense)
    {
        $this->authorizeBranch($expense);
        abort_unless($expense->receipt_path && Storage::disk('local')->exists($expense->receipt_path), 404);

        return Storage::disk('local')->response($expense->receipt_path);
    }

    private function authorizeBranch(Expense $expense): void
    {
        $branchId = request()->user()->branch_id;
        abort_if($branchId && $expense->branch_id !== $branchId, 404);
    }
}
