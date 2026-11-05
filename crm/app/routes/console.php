<?php

use App\Models\Branch;
use App\Models\User;
use App\Services\Quotations\QuotationService;
use App\Support\Phone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/*
 * First-time production setup, run once over SSH:
 *   php artisan migrate --force && php artisan crm:setup && php artisan crm:create-owner
 * The password is typed by the owner at the prompt; it is never stored in code or chat.
 */
Artisan::command('crm:setup', function () {
    $this->call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    Branch::firstOrCreate(['code' => 'JG'], [
        'name' => 'Jay Ganesh Car Accessories',
        'address' => 'Kawadipat Toll Naka, Loni Kalbhor, Pune, Maharashtra',
        'state_code' => '27',
    ]);
    $this->call('db:seed', ['--class' => SettingsSeeder::class, '--force' => true]);
    $this->info('Roles, main branch and default settings are ready.');
})->purpose('Create roles, the main branch and default settings (safe to re-run)');

Artisan::command('crm:create-owner', function () {
    $name = text('Owner name', required: true);
    $phone = Phone::normalize(text('Mobile number (used to sign in)', required: true));
    $email = text('Email (optional)');
    $pass = password('Password (min 8 characters)', required: true);

    $v = Validator::make(compact('name', 'phone', 'email', 'pass'), [
        'phone' => ['regex:/^\+91[6-9]\d{9}$/', 'unique:users,phone'],
        'email' => ['nullable', 'email', 'unique:users,email'],
        'pass' => ['min:8'],
    ]);
    if ($v->fails()) {
        foreach ($v->errors()->all() as $e) {
            $this->error($e);
        }

        return 1;
    }

    $user = User::create([
        'name' => $name, 'phone' => $phone, 'email' => $email ?: null,
        'password' => $pass, 'branch_id' => Branch::query()->value('id'), 'is_active' => true,
    ]);
    $user->syncRoles(['owner']);
    $this->info("Owner {$user->name} created. Sign in with ".Phone::pretty($phone).'.');
})->purpose('Create an Owner login');

// --- quotations ---
// Quotes past their validity become Expired (the last planned follow-up is kept). Daily, shop time.
Artisan::command('quotations:expire', function (QuotationService $quotations) {
    $n = $quotations->expireDue();
    $this->info("{$n} quotation(s) marked expired.");
})->purpose('Mark quotations past their valid-until date as expired');

Schedule::command('quotations:expire')->dailyAt('00:30')->timezone('Asia/Kolkata');
