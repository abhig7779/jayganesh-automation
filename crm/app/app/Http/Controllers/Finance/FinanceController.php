<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashClose;
use App\Models\Expense;
use App\Support\Dashboard\FinanceHome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** Finance home: today's money in and out, dues ageing and the daily jobs. */
class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->user()->branch_id;

        return view('finance.index', [
            'home' => FinanceHome::today($branchId),
            'recentExpenses' => Expense::query()->live()->with('category:id,name')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderByDesc('date')->orderByDesc('id')->limit(6)->get(),
            'recentCloses' => CashClose::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderByDesc('date')->limit(7)->get(),
            'collectRoute' => Route::has('payments.collect') ? route('payments.collect') : null,
        ]);
    }
}
