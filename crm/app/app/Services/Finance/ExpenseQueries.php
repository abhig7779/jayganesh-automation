<?php

namespace App\Services\Finance;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;

/** Sums over the expense book (cancelled rows never count). */
final class ExpenseQueries
{
    public static function base(Period $p, ?int $branchId = null): Builder
    {
        return Expense::query()->live()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('date', [$p->fromDate(), $p->toDate()]);
    }

    public static function total(Period $p, ?int $branchId = null, ?string $mode = null): int
    {
        return (int) self::base($p, $branchId)->when($mode, fn ($q) => $q->where('mode', $mode))->sum('amount_paise');
    }

    public static function count(Period $p, ?int $branchId = null): int
    {
        return self::base($p, $branchId)->count();
    }

    /** @return list<array{name:string, count:int, total_paise:int}> biggest first */
    public static function byCategory(Period $p, ?int $branchId = null): array
    {
        return self::base($p, $branchId)
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->selectRaw('expense_categories.name as name, COUNT(*) as n, SUM(expenses.amount_paise) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->n, 'total_paise' => (int) $r->total])
            ->all();
    }

    /** One row per expense for the CA. @return list<array<string,mixed>> */
    public static function register(Period $p, ?int $branchId = null): array
    {
        return self::base($p, $branchId)
            ->with(['category:id,name', 'creator:id,name'])
            ->orderBy('date')->orderBy('id')
            ->get()
            ->map(fn (Expense $e) => [
                'date' => $e->date->toDateString(),
                'category' => $e->category?->name,
                'paid_to' => $e->paid_to,
                'mode' => Expense::MODES[$e->mode] ?? $e->mode,
                'amount_paise' => $e->amount_paise,
                'note' => $e->note,
                'receipt' => $e->receipt_path ? 'Yes' : 'No',
                'entered_by' => $e->creator?->name,
            ])->all();
    }
}
