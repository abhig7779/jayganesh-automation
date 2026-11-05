<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Finance\CashCloseService;
use App\Services\Finance\SalesQueries;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** Dues ageing: who owes money, on which bills, and for how long. */
class DuesController extends Controller
{
    public function __invoke(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $installed = SalesQueries::installed();
        $bucket = array_key_exists((string) $request->query('age'), SalesQueries::AGEING) ? $request->query('age') : null;

        $dues = $installed ? SalesQueries::openDues($branchId) : [];
        $ageing = $installed ? SalesQueries::duesAgeing($branchId) : null;
        $shop = (string) Setting::get('shop.name', 'Jay Ganesh Car Accessories');
        $collect = Route::has('payments.collect');

        $customers = collect($dues)
            ->groupBy(fn ($d) => $d['customer_id'] ?? 'walkin-'.$d['invoice_id'])
            ->map(function ($bills) use ($shop, $collect) {
                $first = $bills->first();
                $due = $bills->sum('due_paise');
                $oldest = $bills->max('age_days');

                return [
                    'customer_id' => $first['customer_id'],
                    'name' => $first['customer'],
                    'mobile' => $first['mobile'],
                    'bills' => $bills->sortByDesc('age_days')->values()->all(),
                    'due_paise' => $due,
                    'oldest_days' => $oldest,
                    'bucket' => SalesQueries::bucket($oldest),
                    'collect_url' => $collect && $first['customer_id'] ? route('payments.collect', ['customer' => $first['customer_id']]) : null,
                    'remind_url' => $first['mobile'] ? CashCloseService::waLink($first['mobile'], self::reminderText($first['customer'], $due, $bills->pluck('number')->all(), $shop)) : null,
                ];
            })
            ->when($bucket, fn ($c) => $c->where('bucket', $bucket))
            ->sortByDesc('oldest_days')
            ->values();

        return view('finance.dues', compact('installed', 'ageing', 'customers', 'bucket'));
    }

    public static function reminderText(string $name, int $duePaise, array $billNumbers, string $shop): string
    {
        $first = trim(strtok($name, ' ') ?: $name);
        $bills = count($billNumbers) === 1 ? 'bill '.$billNumbers[0] : 'bills '.implode(', ', $billNumbers);

        return "Hello {$first}, greetings from {$shop}. This is a gentle reminder that ".Money::format($duePaise)
            ." is pending on your {$bills}. You can pay at the shop or by UPI whenever it suits you. "
            .'If you have already paid, please ignore this message. Thank you!';
    }
}
