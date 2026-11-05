<?php

namespace Database\Seeders\Demo;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchases\PurchaseService;
use App\Support\Money;
use Illuminate\Database\Seeder;

/** Local demo data: five suppliers, a month of stock-in bills, payments and one cancelled bill. */
class PurchasesDemoSeeder extends Seeder
{
    private const SUPPLIERS = [
        ['Pune Auto Distributors', '9822012345', '27AAHFP1234K1Z2', 'Shop 14, Budhwar Peth, Pune', 1850000],
        ['DHC Lighting (Maharashtra)', '9890054321', '27AADCD5678M1Z9', 'Bhosari MIDC, Pune', 0],
        ['Garware Surface Films', '9763011122', '27AAACG4321P1ZX', 'Wakad, Pune', 0],
        ['Shree Seat Covers', '9921033344', null, 'Kasba Peth, Pune', 420000],
        ['Autoform India', '9850077788', '07AAECA1111Q1Z5', 'Naraina, New Delhi', -150000],
    ];

    public function run(): void
    {
        if (Supplier::query()->exists()) {
            return;
        }
        $branch = Branch::query()->first();
        $by = User::query()->where('email', 'finance@crm.test')->first() ?? User::query()->first();
        $products = Product::query()->where('type', Product::TYPE_PRODUCT)->where('track_stock', true)->orderBy('id')->get();
        if (! $branch || ! $by || $products->count() < 6) {
            return;
        }

        $suppliers = collect(self::SUPPLIERS)->map(fn ($s) => Supplier::query()->create([
            'branch_id' => $branch->id, 'name' => $s[0], 'mobile' => '+91'.$s[1], 'gstin' => $s[2],
            'address' => $s[3], 'opening_balance_paise' => $s[4], 'created_by' => $by->id,
        ]));

        $service = app(PurchaseService::class);
        $today = now('Asia/Kolkata')->startOfDay();
        mt_srand(2711);

        // [supplier index, days ago, bill no, paid share (0..1), how many lines]
        $bills = [
            [0, 27, 'PAD/2526/1184', 1.0, 4], [1, 24, 'DHC-INV-30711', 0.0, 3], [3, 20, 'SSC-552', 0.5, 2],
            [0, 16, 'PAD/2526/1302', 0.0, 5], [2, 12, 'GSF/PN/0921', 1.0, 2], [4, 9, 'AF-DL-7781', 0.0, 3],
            [1, 6, 'DHC-INV-30958', 0.3, 4], [0, 3, 'PAD/2526/1417', 0.0, 3], [3, 1, 'SSC-571', 0.0, 2],
        ];

        foreach ($bills as [$si, $daysAgo, $billNo, $share, $lines]) {
            $items = $products->random($lines)->map(fn (Product $p) => [
                'product_id' => $p->id,
                'qty' => [2, 4, 5, 6, 10, 12][mt_rand(0, 5)],
                // a little above or below the last known cost, rounded to ₹10
                'unit_cost_paise' => (int) max(5000, round(((int) ($p->cost_paise ?: $p->price_paise * 0.6)) * mt_rand(95, 106) / 100 / 1000) * 1000),
                'gst_rate' => (float) $p->gst_rate,
            ])->values()->all();

            $total = array_sum(array_map(fn ($l) => $l['qty'] * $l['unit_cost_paise'] + Money::percent($l['qty'] * $l['unit_cost_paise'], $l['gst_rate']), $items));
            $paid = $share >= 1 ? $total : (int) (floor($total * $share / 10000) * 10000); // part payments in round ₹100s
            $service->create([
                'supplier_id' => $suppliers[$si]->id, 'bill_no' => $billNo,
                'bill_date' => $today->copy()->subDays($daysAgo)->toDateString(),
                'items' => $items,
                'paid_paise' => $paid,
                'paid_mode' => $share >= 1 ? 'upi' : 'cash',
            ], $by);
        }

        // Later payments from the ledger.
        $service->pay($suppliers[0], ['amount_paise' => 1500000, 'date' => $today->copy()->subDays(10)->toDateString(), 'mode' => 'bank', 'reference' => 'UTR 4402 1187 3311'], $by);
        $service->pay($suppliers[1], ['amount_paise' => 2000000, 'date' => $today->copy()->subDays(4)->toDateString(), 'mode' => 'upi', 'reference' => 'UPI 6612 0098'], $by);

        // One bill entered twice by mistake, then cancelled by the owner.
        $owner = User::query()->where('email', 'owner@crm.test')->first() ?? $by;
        $dup = $service->create([
            'supplier_id' => $suppliers[2]->id, 'bill_no' => 'GSF/PN/0921-DUP', 'bill_date' => $today->copy()->subDays(12)->toDateString(),
            'items' => [['product_id' => $products->first()->id, 'qty' => 2, 'unit_cost_paise' => (int) ($products->first()->cost_paise ?: 100000), 'gst_rate' => 18]],
        ], $by);
        $service->cancel($dup, 'Entered twice', $owner);

        // Make the timestamps look like the bills were entered on their day.
        foreach (Purchase::query()->get() as $p) {
            $at = $p->bill_date->copy()->setTime(11, mt_rand(0, 59));
            Purchase::query()->whereKey($p->id)->update(['created_at' => $at, 'updated_at' => $at]);
        }
    }
}
