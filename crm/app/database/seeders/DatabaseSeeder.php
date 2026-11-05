<?php

namespace Database\Seeders;

use App\Models\Branch;
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
        }
    }
}
