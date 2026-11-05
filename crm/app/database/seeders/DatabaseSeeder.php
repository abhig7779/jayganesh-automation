<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $branch = Branch::firstOrCreate(['code' => 'JG'], [
            'name' => 'Jay Ganesh Car Accessories',
            'address' => 'Kawadipat Toll Naka, Loni Kalbhor, Pune, Maharashtra',
            'phone' => '+919823107676',
            'state_code' => '27',
        ]);

        $this->call(SettingsSeeder::class);

        // Demo staff only on a developer machine. Production owners are created with
        // `php artisan crm:create-owner`, which asks for the password interactively.
        if (app()->environment('local')) {
            foreach (['owner', 'admin', 'finance', 'sales', 'technician'] as $role) {
                $u = User::firstOrCreate(['email' => "$role@crm.test"], [
                    'name' => ucfirst($role).' (demo)',
                    'password' => 'password',
                    'branch_id' => $branch->id,
                ]);
                $u->syncRoles([$role]);
            }

            $this->seedDemoCustomers($branch);

            // Each module drops its own demo seeder in database/seeders/Demo/ (crm/CONTRACTS.md).
            // They run in file-name order, so prefix them: 10_ProductsDemoSeeder.php, 20_…
            foreach (glob(__DIR__.'/Demo/*Seeder.php') as $file) {
                $class = 'Database\\Seeders\\Demo\\'.preg_replace('/^\d+_/', '', basename($file, '.php'));
                require_once $file;
                $this->call($class);
            }
        }
    }

    /** About 30 Pune customers with 1-2 cars each, for the counter screens. Local only. */
    private function seedDemoCustomers(Branch $branch): void
    {
        if (Customer::query()->exists()) {
            return;
        }
        $sales = User::query()->where('email', 'sales@crm.test')->first();

        Customer::factory()
            ->count(30)
            ->state(fn () => [
                'branch_id' => $branch->id,
                'created_by' => $sales?->id,
            ])
            ->create()
            ->each(function (Customer $c) use ($sales) {
                CustomerCar::factory()->count(fake()->boolean(25) ? 2 : 1)
                    ->create(['customer_id' => $c->id, 'created_by' => $sales?->id]);
                $c->forceFill([
                    'last_visit_at' => fake()->boolean(80) ? now()->subDays(fake()->numberBetween(0, 400)) : null,
                    'total_spent_paise' => fake()->numberBetween(0, 150) * 100_000 + fake()->randomElement([0, 50_000]),
                ])->saveQuietly();
            });
    }
}
