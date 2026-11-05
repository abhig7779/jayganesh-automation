<?php

namespace Database\Seeders\Demo;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Billing\GstCalculator;
use App\Services\Billing\InvoiceService;
use App\Services\Quotations\FollowUpScheduler;
use App\Services\Quotations\LeadService;
use App\Services\Quotations\QuotationService;
use App\Services\Stock\StockService;
use App\Support\Quotations\ShopClock;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Local demo data for quotations, follow-ups and leads: ~25 quotes over the last 14 days in
 * mixed states (won, lost, expired, visit booked, open, a few overdue) and ~10 leads. Works
 * before the products module is merged by adding a few products itself when the table is empty.
 */
class QuotationsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Quotation::query()->exists()) {
            return;
        }
        $branch = Branch::query()->first();
        $customers = Customer::query()->whereHas('cars')->with('cars')->inRandomOrder()->limit(35)->get();
        if (! $branch || $customers->count() < 10) {
            return;
        }
        $products = $this->products($branch);
        [$rohit, $sagar, $owner, $marketing] = $this->staff($branch);

        $quotes = app(QuotationService::class);
        $leads = app(LeadService::class);
        $scheduler = app(FollowUpScheduler::class);
        mt_srand(30);

        // fate per quote, oldest first. Days ago 13 → 0.
        $plan = [
            [13, 'won'], [13, 'lost'], [12, 'expired'], [12, 'won'], [11, 'lost'], [11, 'expired'],
            [10, 'chased'], [9, 'won'], [9, 'price'], [8, 'overdue'], [8, 'lost'], [7, 'visit'],
            [7, 'chased'], [6, 'overdue'], [6, 'won'], [5, 'chased'], [5, 'overdue'], [4, 'visit'],
            [4, 'chased'], [3, 'overdue'], [3, 'price'], [2, 'chased'], [2, 'fresh'], [1, 'fresh'], [0, 'fresh'],
        ];

        $realToday = ShopClock::now();
        foreach ($plan as $i => [$daysAgo, $fate]) {
            $customer = $customers[$i % $customers->count()];
            $by = $i % 3 === 0 ? $sagar : $rohit;
            $at = $realToday->copy()->subDays($daysAgo)->setTime(10 + ($i % 8), 15 * ($i % 4));
            Carbon::setTestNow($at);

            $picked = $products->random(mt_rand(1, 3));
            $q = $quotes->create([
                'customer_id' => $customer->id,
                'customer_car_id' => $customer->cars->first()?->id,
                'items' => $picked->map(fn ($p) => ['product_id' => $p->id, 'qty' => $p->type === 'service' ? 1 : mt_rand(1, 2)])->values()->all(),
                'sent' => true,
            ], $by);

            $logs = 0;
            while ($logs < 8) {
                $f = $q->followUps()->pending()->whereDate('due_on', '<', $realToday->toDateString())->orderBy('due_on')->first();
                if (! $f) {
                    break;
                }
                // Logged at 11 a.m. on the due day (some a day late).
                Carbon::setTestNow(Carbon::parse($f->due_on->toDateString().' 11:00', ShopClock::TZ)->addDays($logs === 1 && $fate === 'chased' ? 1 : 0));
                $when = fn (int $d) => $f->due_on->copy()->addDays($d)->toDateString();

                match ($fate) {
                    'overdue' => $logs === 0 ? $scheduler->log($f, 'no_answer', $by) : null,
                    'lost' => $logs === 0
                        ? $scheduler->log($f, 'call_later', $by, $when(2), 'Comparing with another shop')
                        : $scheduler->log($f, $i % 2 ? 'bought_elsewhere' : 'not_interested', $by, null, $i % 2 ? 'Got it ₹2,000 cheaper online' : null),
                    'visit' => $scheduler->log($f, 'coming_on', $by, $realToday->copy()->addDays($i % 2)->toDateString(), 'Will come after office'),
                    'price' => $logs === 0 ? $scheduler->log($f, 'wants_better_price', $by, null, 'Asking for ₹1,500 off') : $scheduler->log($f, 'call_later', $by, $when(3)),
                    'won' => $scheduler->log($f, 'coming_on', $by, $when(1)),
                    default => $scheduler->log($f, $logs % 2 ? 'call_later' : 'no_answer', $by, $logs % 2 ? $when(3) : null),
                };
                if ($fate === 'overdue' && $logs === 0) {
                    $logs++;
                    break;
                }
                if ($fate === 'won') {
                    Carbon::setTestNow($f->due_on->copy()->addDay()->setTime(16, 0));
                    $q->load('items');
                    // A real bill from the quote; InvoiceSaved then marks the quote Won.
                    $this->paidBill(
                        $q->items->map(fn ($it) => ['product_id' => $it->product_id, 'qty' => (int) $it->qty])->all(),
                        $customer->id, $q->customer_car_id, $q->id, $owner,
                    );
                    break;
                }
                if (in_array($fate, ['visit', 'lost'], true) && $q->fresh()->status !== Quotation::OPEN) {
                    break;
                }
                $logs++;
            }
        }

        // Leads: ad enquiries and "asked price" at the counter.
        $leadPlan = [
            ['9823104512', 'Rahul Pawar', 'Garware PPF full body for Creta', 'whatsapp_ad', 6500000, 6, 'overdue'],
            ['9765432109', null, 'Ceramic coating price for Scorpio-N', 'instagram', 1499900, 5, 'chased'],
            ['9822045671', 'Ganesh Kale', 'Android stereo 9 inch for Swift', 'phone_call', 1150000, 4, 'quote'],
            ['9970123456', 'Priya Deshpande', '7D mats + seat covers, Innova Hycross', 'whatsapp_ad', null, 3, 'overdue'],
            ['9823011245', 'Amit Jadhav', 'LED headlights for Nexon', 'walk_in_enquiry', 499900, 2, 'chased'],
            ['9823107788', 'Sneha Kulkarni', 'PPF bonnet + bumper price', 'website', 1800000, 2, 'lost'],
            ['9881234567', null, 'Sunroof visor and door guards', 'referral', null, 1, 'fresh'],
            ['9767011223', 'Vikram Shinde', 'Ceramic coating 9H', 'whatsapp_ad', 1499900, 1, 'fresh'],
            ['9850098765', 'Nilesh Bhosale', 'Full body PPF, Fortuner', 'instagram', 6500000, 0, 'fresh'],
            ['9604455667', 'Pooja More', 'Dashcam fitting price', 'phone_call', 450000, 0, 'fresh'],
        ];
        foreach ($leadPlan as $i => [$mobile, $name, $interest, $source, $price, $daysAgo, $fate]) {
            Carbon::setTestNow($realToday->copy()->subDays($daysAgo)->setTime(12, 10 * $i));
            $product = $price ? $products->firstWhere('price_paise', $price) : null;
            $lead = $leads->create([
                'mobile' => $mobile, 'name' => $name, 'interest' => $interest, 'source' => $source,
                'price_told_paise' => $price, 'product_id' => $product?->id,
                'type' => $source === 'walk_in_enquiry' || $source === 'phone_call' ? Lead::TYPE_PRICE : Lead::TYPE_ENQUIRY,
                'assigned_to' => $marketing->id,
            ], $marketing);
            $f = $lead->followUps()->pending()->orderBy('due_on')->first();
            if ($fate === 'chased' && $f) {
                $scheduler->log($f, 'call_later', $marketing, ShopClock::now()->addDays(2)->toDateString(), 'Will decide by weekend');
            } elseif ($fate === 'lost' && $f) {
                $scheduler->log($f, 'bought_elsewhere', $marketing, null, 'Went to a shop in Hadapsar');
            } elseif ($fate === 'overdue' && $f) {
                $scheduler->log($f, 'no_answer', $marketing);
            } elseif ($fate === 'quote' && $f) {
                $scheduler->log($f, 'coming_on', $marketing, ShopClock::now()->addDay()->toDateString());
            }
        }

        Carbon::setTestNow();
        $quotes->expireDue();

        // Mark the demo notifications read except the latest, so the owner's home isn't noisy.
        $owner->notifications()->latest()->skip(1)->take(100)->get()->each->markAsRead();
    }

    /**
     * A real, fully paid bill through InvoiceService (so numbers, stock, totals and InvoiceSaved
     * are real). Restocks first when the demo shelf is short, so stock never goes negative.
     *
     * @param  list<array{product_id:?int, name?:string, qty:int, unit_price_paise?:int}>  $items
     */
    private function paidBill(array $items, int $customerId, ?int $carId, ?int $quotationId, User $by): Invoice
    {
        $stock = app(StockService::class);
        $lines = [];
        foreach ($items as $i) {
            $p = ! empty($i['product_id']) ? Product::query()->find($i['product_id']) : null;
            if ($p && $p->track_stock && $p->on_hand < $i['qty']) {
                $stock->move($p, $i['qty'] + 3, 'opening', null, null, null, 'Demo restock');
            }
            $lines[] = $i + ['gst_rate' => 18];
        }
        $calc = GstCalculator::fromSettings();
        $total = $calc->calculate(array_map(function ($l) {
            $p = ! empty($l['product_id']) ? Product::query()->find($l['product_id']) : null;

            return [
                'qty' => $l['qty'], 'unit_price_paise' => $l['unit_price_paise'] ?? $p?->price_paise ?? 0,
                'gst_rate' => $p?->gst_rate ?? 18, 'price_includes_gst' => $p?->price_includes_gst ?? true,
            ];
        }, $lines))['total_paise'];

        return app(InvoiceService::class)->create([
            'customer_id' => $customerId, 'customer_car_id' => $carId, 'quotation_id' => $quotationId,
            'items' => $lines, 'payments' => [['mode' => 'upi', 'amount_paise' => $total]], 'send_whatsapp' => false,
        ], $by);
    }

    private function products(Branch $branch)
    {
        if (Product::query()->where('is_active', true)->count() >= 5) {
            return Product::query()->where('is_active', true)->orderByDesc('sold_count')->limit(30)->get();
        }
        $rows = [
            ['DHC Gleam 2.0 LED headlight (pair)', 499900, 650000, 'product', 'DHC-GLEAM2', 48],
            ['Autoform seat covers (Creta)', 850000, 990000, 'product', 'AF-SC-CRETA', 31],
            ['7D floor mats', 320000, 450000, 'product', '7D-MAT', 64],
            ['Garware PPF full body', 6500000, 7800000, 'service', 'GW-PPF-FULL', 9],
            ['3M PPF bonnet + bumper', 1800000, 2100000, 'service', '3M-PPF-BB', 14],
            ['Ceramic coating 9H', 1499900, 1800000, 'service', 'CER-9H', 22],
            ['Android stereo 9" 2+32 GB', 1150000, 1499000, 'product', 'AND-9-232', 27],
            ['Blaupunkt Android stereo 9" with CarPlay', 2150000, 2499000, 'product', 'BLP-9-CP', 12],
            ['Dashcam front + rear', 450000, 599000, 'product', 'DC-FR', 18],
            ['Fitting charge', 50000, null, 'service', 'FIT', 80],
        ];
        foreach ($rows as [$name, $price, $mrp, $type, $sku, $sold]) {
            if (! Product::query()->where('sku', $sku)->exists()) {
                (new Product)->forceFill([
                    'sku' => $sku, 'name' => $name, 'price_paise' => $price, 'mrp_paise' => $mrp, 'type' => $type,
                    'gst_rate' => 18, 'sold_count' => $sold, 'on_hand' => $type === 'product' ? 6 : 0,
                    'hsn' => $type === 'service' ? '998714' : '8708',
                ])->save();
            }
        }

        return Product::query()->where('is_active', true)->get();
    }

    /** Two counter staff, the owner and a marketing person, so the per-staff report has rows. */
    private function staff(Branch $branch): array
    {
        $make = function (string $email, string $name, string $role, ?string $phone) use ($branch) {
            $u = User::query()->firstOrCreate(['email' => $email], [
                'name' => $name, 'password' => 'password', 'branch_id' => $branch->id, 'phone' => $phone,
            ]);
            if (! $u->hasRole($role)) {
                $u->syncRoles([$role]);
            }

            return $u;
        };

        $rohit = User::role('sales')->orderBy('id')->first() ?? $make('sales@crm.test', 'Rohit Shinde', 'sales', null);
        if (str_contains($rohit->name, '(demo)')) {
            $rohit->forceFill(['name' => 'Rohit Shinde'])->save();
        }

        return [
            $rohit,
            $make('sagar@crm.test', 'Sagar More', 'sales', '+919823100771'),
            User::role('owner')->orderBy('id')->first(),
            $make('marketing@crm.test', 'Kiran Patil', 'marketing', '+919823100772'),
        ];
    }
}
