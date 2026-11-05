<?php

use App\Models\Branch;
use App\Models\User;
use App\Support\Ops\HealthCheck;
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

// --- devops: scheduler (crm/DEPLOY.md, "Cron"). One cron line runs `schedule:run` every minute. ---
// Times are India time; the app itself stores UTC.
Schedule::call(fn () => HealthCheck::beat())
    ->name('ops:heartbeat')->everyMinute();

// Shared hosting has no supervisor, so the scheduler starts a short-lived worker every minute.
// It exits when the queue is empty or after 50 s; withoutOverlapping stops two running at once.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()->withoutOverlapping(5);

// Nightly backup at 23:30 IST: clean first (keeps 30 days), then back up. Monitor emails if stale.
Schedule::command('backup:clean')
    ->dailyAt('23:30')->timezone('Asia/Kolkata')->withoutOverlapping(60);
Schedule::command('backup:run')
    ->dailyAt('23:30')->timezone('Asia/Kolkata')->withoutOverlapping(60);
Schedule::command('backup:monitor')
    ->dailyAt('09:00')->timezone('Asia/Kolkata');

// Daily email of yesterday's errors (skipped when there are none). ERROR_DIGEST_ENABLED toggles it.
Schedule::command('crm:error-digest')
    ->dailyAt('08:00')->timezone('Asia/Kolkata');
// --- end devops ---
// --- quotations ---
// Quotes past their validity become Expired (the last planned follow-up is kept). Daily, shop time.
Artisan::command('quotations:expire', function (QuotationService $quotations) {
    $n = $quotations->expireDue();
    $this->info("{$n} quotation(s) marked expired.");
})->purpose('Mark quotations past their valid-until date as expired');

Schedule::command('quotations:expire')->dailyAt('00:30')->timezone('Asia/Kolkata');
