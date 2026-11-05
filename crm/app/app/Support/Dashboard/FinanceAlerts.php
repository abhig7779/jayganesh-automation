<?php

namespace App\Support\Dashboard;

use App\Models\CashClose;
use App\Services\Finance\ExpenseQueries;
use App\Services\Finance\Period;
use App\Services\Finance\SalesQueries;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Finance items for the owner's "Needs attention" block (UX-FLOWS.md §2). */
final class FinanceAlerts
{
    /**
     * Yesterday's cash close is missing. Days with no bill, payment or expense (shop shut) are not flagged.
     *
     * @return array{date:string, label:string, url:?string}|null
     */
    public static function missingCashClose(?int $branchId = null): ?array
    {
        if (! Schema::hasTable('cash_closes')) {
            return null;
        }
        $yesterday = Period::today()->subDay();
        $day = Period::day($yesterday);

        $closed = CashClose::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('date', $yesterday->toDateString())
            ->exists();
        if ($closed) {
            return null;
        }

        $active = SalesQueries::hadActivity($day, $branchId) || ExpenseQueries::count($day, $branchId) > 0;
        if (! $active) {
            return null;
        }

        return [
            'date' => $yesterday->toDateString(),
            'label' => 'Cash close not done for '.$yesterday->format('D, j M'),
            'url' => Route::has('finance.cash-close') ? route('finance.cash-close', ['date' => $yesterday->toDateString()]) : null,
        ];
    }
}
