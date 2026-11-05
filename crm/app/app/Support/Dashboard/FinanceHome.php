<?php

namespace App\Support\Dashboard;

use App\Models\CashClose;
use App\Services\Finance\CashCloseService;
use App\Services\Finance\ExpenseQueries;
use App\Services\Finance\Period;
use App\Services\Finance\SalesQueries;
use Illuminate\Support\Facades\Schema;

/** Numbers for the Finance home (UX-FLOWS.md §2 Finance). The shell's HomeData can call this. */
final class FinanceHome
{
    /**
     * @return array{
     *   date:string, billing_installed:bool,
     *   collections:array{cash:int, upi:int, card:int, bank:int, total:int},
     *   dues:array{buckets:array<string,int>, counts:array<string,int>, total_paise:int, customers:int},
     *   expenses:array{total_paise:int, cash_paise:int, count:int},
     *   expected_cash_paise:int, cash_closed:bool, missing_close:?array
     * }
     */
    public static function today(?int $branchId = null): array
    {
        $today = Period::today();
        $p = Period::day($today);
        $modes = SalesQueries::collectionsByMode($p, $branchId);
        $ready = Schema::hasTable('expenses');

        return [
            'date' => $today->toDateString(),
            'billing_installed' => SalesQueries::installed(),
            'collections' => $modes + ['total' => array_sum($modes)],
            'dues' => SalesQueries::duesAgeing($branchId),
            'expenses' => [
                'total_paise' => $ready ? ExpenseQueries::total($p, $branchId) : 0,
                'cash_paise' => $ready ? ExpenseQueries::total($p, $branchId, 'cash') : 0,
                'count' => $ready ? ExpenseQueries::count($p, $branchId) : 0,
            ],
            'expected_cash_paise' => $ready ? app(CashCloseService::class)->expected($today->toDateString(), $branchId)['expected_cash_paise'] : 0,
            'cash_closed' => $ready && CashClose::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->whereDate('date', $today->toDateString())->exists(),
            'missing_close' => FinanceAlerts::missingCashClose($branchId),
        ];
    }
}
