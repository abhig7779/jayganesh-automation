<?php

namespace Database\Seeders\Demo;

use App\Models\Branch;
use App\Models\CashClose;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Finance\CashCloseService;
use App\Services\Finance\Period;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** Local demo data: 30 days of shop expenses and the last 7 cash closes. */
class FinanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Expense::query()->exists()) {
            return;
        }
        $branch = Branch::query()->first();
        $finance = User::query()->where('email', 'finance@crm.test')->first() ?? User::query()->first();
        if (! $branch || ! $finance) {
            return;
        }
        $cat = ExpenseCategory::query()->pluck('id', 'name');
        $today = Period::today();
        mt_srand(2611);

        $add = function (string $date, string $category, int $rupees, string $mode, ?string $paidTo = null, ?string $note = null) use ($branch, $finance, $cat) {
            Expense::query()->create([
                'branch_id' => $branch->id, 'date' => $date, 'expense_category_id' => $cat[$category],
                'amount_paise' => $rupees * 100, 'mode' => $mode, 'paid_to' => $paidTo, 'note' => $note,
                'created_by' => $finance->id,
            ]);
        };

        for ($i = 29; $i >= 0; $i--) {
            $d = $today->subDays($i);
            $date = $d->toDateString();
            if ($d->isSunday() && $i > 0) {
                continue; // shop shut on most Sundays
            }
            $add($date, 'Tea & snacks', [60, 80, 120, 140][mt_rand(0, 3)], 'cash', 'Shree Tea Stall');
            if (mt_rand(1, 3) === 1) {
                $add($date, 'Transport', [150, 250, 400][mt_rand(0, 2)], mt_rand(0, 1) ? 'cash' : 'upi', 'Tempo · Hadapsar', 'Stock pickup from supplier');
            }
            if (mt_rand(1, 5) === 1) {
                $add($date, 'Supplies', [180, 320, 650][mt_rand(0, 2)], 'cash', 'Kalbhor Hardware', 'Cotton waste, masking tape');
            }
            if ($d->day === 1) {
                $add($date, 'Rent', 35000, 'bank', 'Shop owner (Mr. Kale)', 'Monthly shop rent');
            }
            if ($d->day === 5) {
                $add($date, 'Salaries', 18000, 'bank', 'Sunil Gaikwad', 'Technician salary');
                $add($date, 'Salaries', 15000, 'bank', 'Akash Pawar', 'Technician salary');
            }
            if ($d->day === 10) {
                $add($date, 'Electricity', 4870, 'upi', 'MSEDCL', 'Shop electricity bill');
            }
            if ($i === 12) {
                $add($date, 'Marketing', 3000, 'upi', 'Meta ads', 'WhatsApp ad top-up');
            }
            if ($i === 18) {
                $add($date, 'Repairs', 1200, 'cash', 'Pawar Electricals', 'Compressor wiring fix');
            }
        }

        // Cash closes for the last 7 days (not today). The cash maths needs the billing tables.
        if (! Schema::hasTable('payments')) {
            return;
        }
        $service = app(CashCloseService::class);
        $diffs = [0, 0, -20000, 0, 5000, 0, 0];
        for ($i = 7; $i >= 1; $i--) {
            $date = $today->subDays($i)->toDateString();
            if (CashClose::query()->where('branch_id', $branch->id)->whereDate('date', $date)->exists()) {
                continue;
            }
            $expected = $service->expected($date, $branch->id)['expected_cash_paise'];
            $diff = $diffs[7 - $i];
            $note = $diff < 0 ? 'Gave ₹200 to Akash for petrol, forgot to enter it' : ($diff > 0 ? 'Customer paid ₹50 extra, will adjust next visit' : null);
            $service->close($finance, $date, $expected + $diff, $note, $branch->id);
        }
    }
}
