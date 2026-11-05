<?php

namespace Tests\Support;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;

/**
 * A realistic shop week for the report tests. "Today" is Thu 5 Nov 2026 (tests travel there).
 * Every expected number below is worked out by hand in FinanceReportsTest.
 *
 * Today:      A  sales1  LED (18%) ₹4,999 + 2× floor mats (28%) ₹6,400 = ₹11,399 · paid cash ₹6,399 + UPI ₹5,000
 *             B  owner   fitting (18%) ₹500 · unpaid (due ₹500)
 *             D  owner   LED ₹999 · paid cash · CANCELLED
 *             card ₹500 collected today against bill E (dues)
 * Yesterday:  C  sales1  LED ₹4,999 to a Gujarat customer (IGST) · paid UPI
 * 40 days ago E  sales1  LED ₹3,000 bill · paid ₹1,000 then, ₹500 today → due ₹1,500
 * Expenses:   today Tea ₹300 cash, UPI transport ₹450 · 1 Nov rent ₹35,000 bank · a cancelled ₹999 cash
 */
final class FinanceFixture
{
    public static function build(int $branchId): array
    {
        CreateBillingTables::up();

        $sales = staff('sales', ['name' => 'Rohit Shinde']);
        $owner = staff('owner', ['name' => 'Prakash Jadhav']);

        $lights = Category::query()->create(['name' => 'Lighting']);
        $mats = Category::query()->create(['name' => 'Mats']);
        $led = Product::query()->create(['name' => 'DHC Gleam 2.0 LED', 'hsn' => '85122010', 'gst_rate' => 18, 'price_paise' => 499900, 'cost_paise' => 300000, 'category_id' => $lights->id]);
        $mat = Product::query()->create(['name' => '7D floor mats', 'hsn' => '87089900', 'gst_rate' => 28, 'price_paise' => 320000, 'cost_paise' => null, 'category_id' => $mats->id]);
        $fit = Product::query()->create(['name' => 'Fitting charge', 'type' => 'service', 'hsn' => '998714', 'gst_rate' => 18, 'price_paise' => 50000, 'cost_paise' => 10000]);

        $rahul = Customer::query()->create(['branch_id' => $branchId, 'name' => 'Rahul Pawar', 'mobile' => '+919823104512']);
        $sneha = Customer::query()->create(['branch_id' => $branchId, 'name' => 'Sneha Kulkarni', 'mobile' => '+919823107788']);
        $amit = Customer::query()->create(['branch_id' => $branchId, 'name' => 'Amit Jadhav', 'mobile' => '+919823011245']);

        $ledLine = fn (array $x = []) => array_merge(['product_id' => $led->id, 'name' => $led->name, 'hsn' => '85122010', 'qty' => 1, 'gst_rate' => 18,
            'taxable_paise' => 423644, 'cgst_paise' => 38128, 'sgst_paise' => 38128, 'line_total_paise' => 499900], $x);

        $a = CreateBillingTables::bill(['at' => '2026-11-05 11:30', 'customer_id' => $rahul->id, 'created_by' => $sales->id, 'branch_id' => $branchId], [
            $ledLine(),
            ['product_id' => $mat->id, 'name' => $mat->name, 'hsn' => '87089900', 'qty' => 2, 'gst_rate' => 28,
                'taxable_paise' => 500000, 'cgst_paise' => 70000, 'sgst_paise' => 70000, 'line_total_paise' => 640000],
        ], [['cash', 639900], ['upi', 500000]]);

        $b = CreateBillingTables::bill(['at' => '2026-11-05 16:10', 'customer_id' => $sneha->id, 'created_by' => $owner->id, 'branch_id' => $branchId], [
            ['product_id' => $fit->id, 'name' => $fit->name, 'hsn' => '998714', 'qty' => 1, 'gst_rate' => 18,
                'taxable_paise' => 42373, 'cgst_paise' => 3814, 'sgst_paise' => 3813, 'line_total_paise' => 50000],
        ]);

        $d = CreateBillingTables::bill(['at' => '2026-11-05 12:00', 'customer_id' => $rahul->id, 'created_by' => $owner->id, 'branch_id' => $branchId,
            'status' => 'cancelled', 'cancelled_at' => '2026-11-05 07:00:00'], [
                $ledLine(['taxable_paise' => 84661, 'cgst_paise' => 7620, 'sgst_paise' => 7619, 'line_total_paise' => 99900]),
            ], [['cash', 99900]]);

        $c = CreateBillingTables::bill(['at' => '2026-11-04 10:00', 'customer_id' => $amit->id, 'created_by' => $sales->id, 'branch_id' => $branchId], [
            $ledLine(['cgst_paise' => 0, 'sgst_paise' => 0, 'igst_paise' => 76256]),
        ], [['upi', 499900]]);

        $e = CreateBillingTables::bill(['at' => '2026-09-26 13:00', 'customer_id' => $amit->id, 'created_by' => $sales->id, 'branch_id' => $branchId,
            'total_paise' => 300000, 'paid_paise' => 150000, 'status' => 'partial'], [
                $ledLine(['taxable_paise' => 254237, 'cgst_paise' => 22882, 'sgst_paise' => 22881, 'line_total_paise' => 300000]),
            ], [['cash', 100000]]);
        CreateBillingTables::payment('card', 50000, '2026-11-05 10:15', $e, $amit->id);

        $cat = ExpenseCategory::query()->pluck('id', 'name');
        $exp = fn (string $date, string $c, int $paise, string $mode) => Expense::query()->create([
            'branch_id' => $branchId, 'date' => $date, 'expense_category_id' => $cat[$c], 'amount_paise' => $paise, 'mode' => $mode, 'created_by' => $owner->id,
        ]);
        $exp('2026-11-05', 'Tea & snacks', 30000, 'cash');
        $exp('2026-11-05', 'Transport', 45000, 'upi');
        $exp('2026-11-01', 'Rent', 3500000, 'bank');
        $cancelled = $exp('2026-11-05', 'Supplies', 99900, 'cash');
        $cancelled->forceFill(['cancelled_at' => now()])->save();

        return compact('sales', 'owner', 'a', 'b', 'c', 'd', 'e', 'rahul', 'sneha', 'amit');
    }
}
