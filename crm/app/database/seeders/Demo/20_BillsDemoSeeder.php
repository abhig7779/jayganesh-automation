<?php

namespace Database\Seeders\Demo;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Billing\GstCalculator;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\PaymentService;
use App\Services\Stock\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * About 40 demo bills over the last 30 days (local only, crm/CONTRACTS.md): mixed payment
 * modes, a few dues (one part-collected later) and one cancelled bill. Uses the products
 * module's demo products when they exist; if the products table is empty it adds 15 minimal
 * ones itself. Bills go through InvoiceService, so numbers, stock and totals are real.
 */
class BillsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Invoice::query()->exists()) {
            return;
        }
        $this->ensureProducts();
        // Demo shop details so bills show GST and a UPI QR (placeholders from DESIGN.md §10).
        if (blank(Setting::get('tax.gstin'))) {
            Setting::put('tax.gstin', '27AAJFJ1234K1Z5');
        }
        if (blank(Setting::get('billing.upi_id'))) {
            Setting::put('billing.upi_id', 'jayganesh@upi');
        }

        $products = Product::query()->active()->get();
        $customers = Customer::query()->with('cars')->get();
        $staff = User::query()->role(['sales', 'admin', 'owner'])->get();
        $owner = User::query()->role('owner')->first();
        if ($products->isEmpty() || $customers->isEmpty() || $staff->isEmpty() || ! $owner) {
            return;
        }

        $faker = fake('en_IN');
        $faker->seed(2026);
        mt_srand(2026);
        $service = app(InvoiceService::class);
        $popular = $products->sortByDesc(fn ($p) => $p->price_paise < 2_000_000 ? 1 : 0)->values();

        $base = now('Asia/Kolkata');
        $made = [];
        $days = collect(range(29, 0))->flatMap(fn ($d) => array_fill(0, $d % 3 === 0 ? 2 : 1, $d))->take(40)->values();

        foreach ($days as $n => $daysAgo) {
            $when = $base->copy()->subDays($daysAgo)->setTime(mt_rand(10, 19), mt_rand(0, 59))->utc();
            Carbon::setTestNow($when);

            $customer = $n % 9 === 4 ? null : $customers[$n % $customers->count()];
            $lines = [];
            foreach ($popular->random(mt_rand(1, 3)) as $p) {
                $lines[] = ['product_id' => $p->id, 'qty' => $p->isService() ? 1 : mt_rand(1, 2)];
            }
            $mode = ['cash', 'upi', 'upi', 'card', 'cash', 'split'][$n % 6];
            $isDue = $customer && in_array($n, [5, 11, 17, 25, 34], true);
            $by = $staff[$n % $staff->count()];

            // Price the bill first to split the payment.
            $calc = GstCalculator::fromSettings();
            $total = $calc->calculate(array_map(fn ($l) => [
                'qty' => $l['qty'], 'unit_price_paise' => $products->firstWhere('id', $l['product_id'])->price_paise,
                'gst_rate' => $products->firstWhere('id', $l['product_id'])->gst_rate,
            ], $lines), $n % 7 === 3 ? 20000 : 0)['total_paise'];

            $payments = match (true) {
                $isDue => [['mode' => 'cash', 'amount_paise' => min($total, (int) (round($total * 0.4 / 10000) * 10000))]],
                $mode === 'split' => [['mode' => 'cash', 'amount_paise' => intdiv($total, 200) * 100], ['mode' => 'upi', 'amount_paise' => $total - intdiv($total, 200) * 100]],
                default => [['mode' => $mode, 'amount_paise' => $total, 'reference' => $mode === 'upi' ? (string) $faker->numerify('4#########') : null]],
            };

            $invoice = $service->create([
                'customer_id' => $customer?->id,
                'customer_car_id' => $customer?->cars->first()?->id,
                'items' => $lines,
                'payments' => $payments,
                'bill_discount_paise' => $n % 7 === 3 ? 20000 : 0,
                'discount_reason' => $n % 7 === 3 ? 'Regular customer' : null,
                'send_whatsapp' => true,
            ], ($isDue || $n % 7 === 3) ? $owner : $by);
            DB::table('invoices')->where('id', $invoice->id)->update(['created_at' => $when, 'updated_at' => $when]);
            $made[] = $invoice;
        }

        // One cancelled bill (yesterday), one due part-collected.
        Carbon::setTestNow($base->copy()->subDay()->setTime(12, 30)->utc());
        $cancel = collect($made)->first(fn ($i) => $i->due_paise === 0 && $i->customer_id);
        if ($cancel) {
            $service->cancel($cancel, 'Duplicate: entered twice by mistake', $owner);
        }
        $due = collect($made)->first(fn ($i) => $i->fresh()->due_paise > 0);
        if ($due) {
            Carbon::setTestNow(now()->addHours(2));
            app(PaymentService::class)->collect($due->customer, intdiv($due->fresh()->due_paise, 200) * 100, 'upi', null, [$due->id], $owner);
        }
        Carbon::setTestNow();
    }

    private function ensureProducts(): void
    {
        if (Product::query()->exists()) {
            return;
        }
        // name, brand, hsn, gst, price ₹, mrp ₹, stock, type, sku
        $rows = [
            ['DHC Gleam 2.0 LED headlight (H4)', 'DHC', '8512', 18, 4999, 6500, 14, 'product', 'DHC-GLM-H4'],
            ['7D floor mats (Creta)', 'Autoform', '5705', 12, 3200, 3999, 6, 'product', 'AF-7D-CRETA'],
            ['Autoform seat covers (Creta)', 'Autoform', '9401', 18, 8500, 9999, 8, 'product', 'AF-SC-CRETA'],
            ['Ceramic coating 9H', 'Shield', '9987', 18, 14999, 18000, 0, 'service', 'SVC-CER-9H'],
            ['Garware PPF full body', 'Garware', '9987', 18, 65000, 72000, 0, 'service', 'SVC-PPF-GAR'],
            ['3M PPF bonnet + bumper', '3M', '9987', 18, 18000, null, 0, 'service', 'SVC-PPF-3M'],
            ['Android stereo 9" 2+32 GB', 'Blaupunkt', '8527', 18, 11500, 14990, 6, 'product', 'BP-AND-9'],
            ['Fitting charge', null, '998714', 18, 500, null, 0, 'service', 'SVC-FIT'],
            ['Mud flaps (OEM)', 'Hyundai', '8708', 28, 650, 799, 22, 'product', 'HY-MUD-OEM'],
            ['Steering cover (leather stitch)', 'Autofurnish', '8708', 28, 799, 1199, 19, 'product', 'AFN-STR-01'],
            ['Dashcam 70mai A400', '70mai', '8525', 18, 4500, 5999, 7, 'product', '70M-A400'],
            ['Godrej aer car perfume', 'Godrej', '3307', 18, 349, 399, 35, 'product', 'GOD-AER-01'],
            ['Gleam LED fog lamps 3000K/6500K', 'DHC', '8512', 18, 3450, 4200, 6, 'product', 'DHC-FOG-GL'],
            ['Gleam ceramic coating spray 250 ml', 'AutoGleam', '3405', 18, 1200, 1500, 2, 'product', 'AG-CC-250'],
            ['TVS tyre inflator 12V', 'TVS', '8414', 18, 2499, 3299, 0, 'product', 'TVS-INF-12'],
        ];
        $stock = app(StockService::class);
        foreach ($rows as [$name, $brand, $hsn, $gst, $price, $mrp, $qty, $type, $sku]) {
            $p = Product::create([
                'sku' => $sku, 'name' => $name, 'brand_id' => $brand ? Brand::firstOrCreate(['name' => $brand])->id : null,
                'type' => $type, 'hsn' => $hsn, 'gst_rate' => $gst, 'price_includes_gst' => true,
                'price_paise' => $price * 100, 'mrp_paise' => $mrp ? $mrp * 100 : null,
                'cost_paise' => (int) ($price * 62), 'reorder_level' => 3,
            ]);
            if ($type === 'product' && $qty > 0) {
                $stock->move($p, $qty * 3, 'opening', null, null, (int) ($price * 62), 'Demo opening stock');
            }
        }
    }
}
