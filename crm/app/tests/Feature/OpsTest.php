<?php

use App\Mail\ErrorDigest;
use App\Models\Branch;
use App\Providers\AppServiceProvider;
use App\Support\Ops\HealthCheck;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Config\Config as BackupConfig;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Branch::create(['name' => 'Main', 'code' => 'JG']);
});

/** Make every health check pass: a fresh backup zip and a scheduler heartbeat. */
function healthyOps(): void
{
    Storage::fake('backups');
    Storage::disk('backups')->put(config('backup.backup.name').'/'.now()->format('Y-m-d-H-i-s').'.zip', 'zip');
    HealthCheck::beat();
}

function inProduction(): void
{
    app()->detectEnvironment(fn () => 'production');
}

// --- Security headers ----------------------------------------------------------------------

it('sends the security headers on every page', function () {
    $res = $this->get('https://crm.test/login')->assertOk();

    $res->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
        ->assertHeader('Permissions-Policy');

    $csp = $res->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("'unsafe-eval'")       // standard Alpine build (documented decision)
        ->toMatch("/'nonce-[A-Za-z0-9]+'/")
        ->not->toContain("script-src 'self' 'unsafe-inline'");
});

it('also protects 404 pages and the /up health route', function () {
    $this->get('/no-such-page')->assertNotFound()->assertHeader('X-Frame-Options', 'DENY');
    $this->get('/up')->assertOk()->assertHeader('Content-Security-Policy');
});

it('only sends HSTS over https outside production', function () {
    $this->get('http://crm.test/login')->assertHeaderMissing('Strict-Transport-Security');
});

it('can switch CSP to report-only or off from .env', function () {
    config(['ops.csp' => 'report-only']);
    $this->get('/login')->assertHeader('Content-Security-Policy-Report-Only')->assertHeaderMissing('Content-Security-Policy');

    config(['ops.csp' => 'off']);
    $this->get('/login')->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

// --- HTTPS in production -------------------------------------------------------------------

it('redirects http to https in production', function () {
    inProduction();

    $this->get('http://crm.test/login?x=1')->assertStatus(301)->assertRedirect('https://crm.test/login?x=1');
    $this->get('https://crm.test/login')->assertOk()->assertHeader('Strict-Transport-Security');
    expect($this->get('https://crm.test/login')->headers->get('Content-Security-Policy'))->toContain('upgrade-insecure-requests');
});

it('does not redirect when FORCE_HTTPS is off', function () {
    inProduction();
    config(['ops.force_https' => false]);

    $this->get('http://crm.test/login')->assertOk();
});

it('generates https links in production', function () {
    inProduction();
    (new AppServiceProvider(app()))->boot();

    expect(url('/bills'))->toStartWith('https://')
        ->and(route('login'))->toStartWith('https://');
});

it('keeps http links on a developer machine', function () {
    (new AppServiceProvider(app()))->boot();

    expect(url('/x'))->toStartWith('http://');
});

it('blocks all crawlers in robots.txt', function () {
    expect(file_get_contents(public_path('robots.txt')))->toContain("User-agent: *\nDisallow: /\n");
});

// --- Health command and page ---------------------------------------------------------------

it('prints OK when everything is healthy', function () {
    healthyOps();

    $this->artisan('crm:health')
        ->expectsOutputToContain('OK: everything is healthy.')
        ->assertExitCode(0);
});

it('fails when there is no backup and no scheduler heartbeat', function () {
    Storage::fake('backups');

    $this->artisan('crm:health')
        ->expectsOutputToContain('No backup found yet')
        ->expectsOutputToContain('No heartbeat yet')
        ->expectsOutputToContain('FAIL: see the lines above.')
        ->assertExitCode(1);
});

it('fails when the newest backup is too old', function () {
    healthyOps();
    Storage::fake('backups');
    Storage::disk('backups')->put('jayganesh-crm/'.now()->subHours(30)->format('Y-m-d-H-i-s').'.zip', 'zip');

    $results = collect(app(HealthCheck::class)->run())->keyBy('key');
    expect($results['backup']['ok'])->toBeFalse()
        ->and($results['backup']['detail'])->toContain('30 h ago');
});

it('fails when the scheduler heartbeat is stale', function () {
    healthyOps();
    $this->travel(10)->minutes();

    $results = collect(app(HealthCheck::class)->run())->keyBy('key');
    expect($results['scheduler']['ok'])->toBeFalse()
        ->and($results['backup']['ok'])->toBeTrue();
});

it('fails when queued jobs have waited too long', function () {
    healthyOps();
    config(['queue.default' => 'database']);
    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null,
        'available_at' => now()->subMinutes(25)->getTimestamp(), 'created_at' => now()->subMinutes(25)->getTimestamp(),
    ]);

    $results = collect(app(HealthCheck::class)->run())->keyBy('key');
    expect($results['queue']['ok'])->toBeFalse()
        ->and($results['queue']['detail'])->toContain('oldest for 25 min');
});

it('fails when a storage folder is not writable', function () {
    healthyOps();
    config(['ops.health.writable_paths' => [storage_path('logs'), '/no/such/folder']]);

    $results = collect(app(HealthCheck::class)->run())->keyBy('key');
    expect($results['storage']['ok'])->toBeFalse()
        ->and($results['storage']['detail'])->toContain('/no/such/folder');
});

it('fails when the database cannot be reached', function () {
    healthyOps();
    $default = config('database.default');
    config([
        'database.connections.broken' => ['driver' => 'sqlite', 'database' => '/no/such/folder/crm.sqlite', 'prefix' => ''],
        'database.default' => 'broken',
    ]);

    $health = app(HealthCheck::class);
    $results = $health->run();
    config(['database.default' => $default]); // let RefreshDatabase roll back the real connection

    expect(collect($results)->keyBy('key')['database']['ok'])->toBeFalse()
        ->and($health->allOk($results))->toBeFalse();
});

it('shows the health page to the owner only', function () {
    healthyOps();

    $this->actingAs(staff('owner'))->get('/health')
        ->assertOk()->assertSee('System health')->assertSee('OK: everything is healthy.')->assertSee('Last backup');

    $this->actingAs(staff('admin'))->get('/health')->assertForbidden();
    $this->actingAs(staff('sales'))->get('/health')->assertForbidden();
});

// --- Error pages ---------------------------------------------------------------------------

it('shows branded, plain-English error pages', function (int $code, string $text) {
    config(['app.debug' => false]);
    Route::get('/_test/error', fn () => abort($code));

    $this->get('/_test/error')->assertStatus($code)
        ->assertSee($text)
        ->assertSee('Jay Ganesh CRM')
        ->assertDontSee('Whoops');
})->with([
    [403, 'You don’t have access to this page'],
    [404, 'Page not found'],
    [419, 'Session expired, please sign in again'],
    [429, 'Too many tries'],
    [500, 'Something went wrong'],
    [503, 'The CRM is being updated'],
]);

it('hides the details of a crash behind the 500 page', function () {
    config(['app.debug' => false]);
    Route::get('/_test/crash', fn () => throw new RuntimeException('secret stack detail'));

    $this->get('/_test/crash')->assertStatus(500)->assertSee('Something went wrong')->assertDontSee('secret stack detail');
});

it('shows the branded 403 when a role opens a page it may not use', function () {
    $this->actingAs(staff('technician'))->get('/settings')->assertForbidden()->assertSee('You don’t have access to this page');
});

// --- Backups -------------------------------------------------------------------------------

it('has a sane backup configuration', function () {
    $c = config('backup');

    expect($c['backup']['name'])->toBe('jayganesh-crm')->not->toContain(' ')
        ->and($c['backup']['source']['files']['include'])->toBe([storage_path('app/private')])
        ->and($c['backup']['source']['databases'])->toBe([config('database.default')])
        ->and($c['backup']['destination']['disks'])->toBe(['backups'])
        ->and(config('filesystems.disks.backups.root'))->toBe(storage_path('app/backups'))
        ->and($c['backup']['source']['files']['exclude'])->toContain(storage_path('app/backups'))
        ->and($c['cleanup']['default_strategy']['keep_all_backups_for_days'])->toBe(30)
        ->and($c['monitor_backups'][0]['name'])->toBe($c['backup']['name'])
        ->and($c['monitor_backups'][0]['disks'])->toBe(['backups'])
        ->and($c['notifications']['notifications'][BackupHasFailedNotification::class])->toBe(['mail'])
        ->and($c['notifications']['notifications'][UnhealthyBackupWasFoundNotification::class])->toBe(['mail'])
        ->and($c['notifications']['notifications'][BackupWasSuccessfulNotification::class])->toBe([]);

    // The package validates it (e-mail address, compression level, strategy class…).
    expect(BackupConfig::fromArray($c))->toBeInstanceOf(BackupConfig::class);
});

it('mails backup failures to BACKUP_NOTIFY_EMAIL', function () {
    putenv('BACKUP_NOTIFY_EMAIL=owner@example.in');
    $config = require config_path('backup.php');
    putenv('BACKUP_NOTIFY_EMAIL');

    expect($config['notifications']['mail']['to'])->toBe('owner@example.in');
});

it('schedules the backup, queue worker, heartbeat, monitor and error email', function () {
    $events = collect(app(Schedule::class)->events());
    $find = fn (string $needle) => $events->first(fn ($e) => str_contains((string) $e->command.' '.$e->description, $needle));

    expect($find('backup:run')->expression)->toBe('30 23 * * *')
        ->and($find('backup:run')->timezone)->toBe('Asia/Kolkata')
        ->and($find('backup:clean')->expression)->toBe('30 23 * * *')
        ->and($find('backup:monitor'))->not->toBeNull()
        ->and($find('crm:error-digest'))->not->toBeNull()
        ->and($find('ops:heartbeat')->expression)->toBe('* * * * *');

    $worker = $find('queue:work');
    expect($worker->command)->toContain('--stop-when-empty')->toContain('--max-time=50')
        ->and($worker->expression)->toBe('* * * * *')
        ->and($worker->withoutOverlapping)->toBeTrue();
});

// --- Daily error email ---------------------------------------------------------------------

function fakeLog(string $body): string
{
    $dir = storage_path('framework/testing/logs-'.uniqid());
    @mkdir($dir, 0777, true);
    file_put_contents($dir.'/laravel-'.now()->format('Y-m-d').'.log', $body);
    config(['ops.error_digest.log_path' => $dir, 'ops.error_digest.to' => 'owner@example.in']);

    return $dir;
}

it('emails a digest of the last day\'s errors with phone numbers masked', function () {
    Mail::fake();
    $now = now()->format('Y-m-d H:i:s');
    $old = now()->subDays(3)->format('Y-m-d H:i:s');
    fakeLog(implode("\n", [
        "[$now] production.ERROR: WhatsApp send failed for +919823107676 {\"exception\":\"[object] (...)\"}",
        '#0 /home/app/vendor/foo.php(12): stack line',
        "[$now] production.ERROR: WhatsApp send failed for +919823107676 {\"exception\":\"[object] (...)\"}",
        "[$now] production.INFO: Bill saved",
        "[$now] production.CRITICAL: Disk full",
        "[$old] production.ERROR: Old problem",
    ])."\n");

    $this->artisan('crm:error-digest')->assertExitCode(0);

    Mail::assertSent(ErrorDigest::class, function ($mail) {
        $text = $mail->render();
        expect($mail->groups)->toHaveCount(2)
            ->and($mail->groups[0]['count'])->toBe(2)
            ->and($text)->toContain('Disk full')
            ->and($text)->not->toContain('9823107676')
            ->and($text)->toContain('7676')
            ->and($text)->not->toContain('Old problem')
            ->and($text)->not->toContain('Bill saved');

        return $mail->hasTo('owner@example.in');
    });
});

it('sends no digest when the log has no errors', function () {
    Mail::fake();
    fakeLog('['.now()->format('Y-m-d H:i:s')."] production.INFO: all good\n");

    $this->artisan('crm:error-digest')->expectsOutputToContain('Nothing sent')->assertExitCode(0);
    Mail::assertNothingSent();
});

it('sends no digest when it is switched off', function () {
    Mail::fake();
    fakeLog('['.now()->format('Y-m-d H:i:s')."] production.ERROR: boom\n");
    config(['ops.error_digest.enabled' => false]);

    $this->artisan('crm:error-digest')->assertExitCode(0);
    Mail::assertNothingSent();
});
