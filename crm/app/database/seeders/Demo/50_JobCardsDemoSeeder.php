<?php

namespace Database\Seeders\Demo;

use App\Models\Branch;
use App\Models\CustomerCar;
use App\Models\JobCard;
use App\Models\Product;
use App\Models\User;
use App\Services\JobCards\JobCardService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** About 12 cars on the job board in mixed statuses (local only, no photos). Safe to run twice. */
class JobCardsDemoSeeder extends Seeder
{
    /** [work items, status, promised (hours from now), technician index] */
    private const JOBS = [
        [['Garware PPF full body'], 'waiting', 30, 0],
        [['Android stereo 9" 2+32 GB', 'Fitting charge'], 'waiting', 3, null],
        [['Ceramic coating 9H', 'Interior detailing'], 'working', 2, 0],
        [['3M PPF bonnet + bumper'], 'working', -0.4, 1],
        [['7D floor mats', 'Autoform seat covers'], 'working', 4, 2],
        [['DHC Gleam 2.0 LED headlight', 'Fitting charge'], 'qc', 1, 1],
        [['Ceramic coating 9H'], 'ready', -1, 0],
        [['Dashcam with hardwire kit'], 'ready', -2, 2],
        [['Rubbing and polishing'], 'delivered', -4, 1],
        [['Glass coating', 'Alloy wheel coating'], 'delivered', -3, 0],
        [['Reverse camera'], 'delivered', -5, 2],
        [['Underbody anti-rust coating'], 'waiting', 26, 1],
    ];

    /** Rupee prices for work with no matching product (DESIGN.md §10 content kit). */
    private const PRICES = [
        'Garware PPF full body' => 65000, 'Android stereo 9" 2+32 GB' => 11500, 'Fitting charge' => 500,
        'Ceramic coating 9H' => 14999, 'Interior detailing' => 2500, '3M PPF bonnet + bumper' => 18000,
        '7D floor mats' => 3200, 'Autoform seat covers' => 8500, 'DHC Gleam 2.0 LED headlight' => 4999,
        'Dashcam with hardwire kit' => 6500, 'Rubbing and polishing' => 1800, 'Glass coating' => 1500,
        'Alloy wheel coating' => 2000, 'Reverse camera' => 3500, 'Underbody anti-rust coating' => 3000,
    ];

    public function run(): void
    {
        if (JobCard::query()->exists()) {
            return;
        }
        $branch = Branch::query()->first();
        $counter = User::query()->where('email', 'sales@crm.test')->first() ?? User::query()->first();
        if (! $branch || ! $counter) {
            return;
        }

        $techs = collect([
            User::query()->where('email', 'technician@crm.test')->first(),
            $this->technician('Sunil Gaikwad', 'sunil@crm.test', '+919800000101', $branch),
            $this->technician('Akash Pawar', 'akash@crm.test', '+919800000102', $branch),
        ])->filter()->values();

        $cars = CustomerCar::query()->whereNotNull('reg_no')->with('customer')->inRandomOrder()->limit(count(self::JOBS))->get();
        $service = app(JobCardService::class);

        foreach (self::JOBS as $i => [$work, $status, $promiseHours, $techIndex]) {
            $car = $cars[$i] ?? null;
            if (! $car) {
                break;
            }
            $promised = $this->roundQuarter(now()->addMinutes((int) round($promiseHours * 60)));
            $cameIn = now()->subHours(match ($status) {
                'waiting' => 1, 'working' => 3, 'qc' => 4, 'ready' => 5, default => 7,
            })->subMinutes(($i * 13) % 50);
            Carbon::setTestNow($cameIn);

            $card = $service->create([
                'customer_id' => $car->customer_id,
                'customer_car_id' => $car->id,
                'odometer' => 12000 + ($i * 7919) % 80000,
                'fuel_level' => JobCard::FUEL_LEVELS[$i % 5],
                'promised_at' => $promised,
                'technician_id' => $techIndex !== null ? $techs[$techIndex % max(1, $techs->count())]?->id : null,
                'bay' => $status === 'waiting' ? null : 'Bay '.(($i % 4) + 1),
                'notes' => $i === 3 ? 'Customer waiting in the lounge.' : null,
                'items' => array_map(fn ($w) => $this->item($w), $work),
            ], $counter);
            Carbon::setTestNow();

            $path = ['waiting' => [], 'working' => ['working'], 'qc' => ['working', 'qc'], 'ready' => ['working', 'qc', 'ready'], 'delivered' => ['working', 'qc', 'ready', 'delivered']][$status];
            $at = $cameIn->copy();
            foreach ($path as $step) {
                $at = $at->copy()->addMinutes(40 + ($i * 7) % 30);
                if ($at->isFuture()) {
                    $at = now()->subMinutes(5);
                }
                Carbon::setTestNow($at);
                $by = in_array($step, ['working', 'qc'], true) && $card->technician_id ? User::find($card->technician_id) : $counter;
                $service->transition($card, $step, $by);
                if ($step === 'qc' || $step === 'ready') {
                    $card->items()->get()->each(fn ($it) => $service->setItemDone($it, true, $by));
                }
                Carbon::setTestNow();
            }
            if ($status === 'working') {
                $first = $card->items()->first();
                $first && $service->setItemDone($first, true, $card->technician ?? $counter);
            }
        }
    }

    private function roundQuarter(Carbon $t): Carbon
    {
        return $t->copy()->addMinutes((15 - $t->minute % 15) % 15)->second(0);
    }

    private function item(string $work): array
    {
        $product = Product::query()->where('name', 'like', '%'.$work.'%')->first();

        return [
            'product_id' => $product?->id,
            'description' => $work,
            'qty' => 1,
            'price_paise' => $product?->price_paise ?? (self::PRICES[$work] ?? 1000) * 100,
        ];
    }

    private function technician(string $name, string $email, string $phone, Branch $branch): User
    {
        $u = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name, 'phone' => $phone, 'password' => 'password', 'branch_id' => $branch->id,
        ]);
        $u->syncRoles(['technician']);

        return $u;
    }
}
