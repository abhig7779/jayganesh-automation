<?php

namespace App\Support\Ops;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Backup\BackupDestination\BackupDestination;
use Throwable;

/**
 * The checks behind `php artisan crm:health` and the Owner-only /health page.
 * Each check returns ['key', 'label', 'ok' => bool, 'detail' => plain English].
 */
class HealthCheck
{
    /** @return list<array{key: string, label: string, ok: bool, detail: string}> */
    public function run(): array
    {
        return [
            $this->guard('database', 'Database', fn () => $this->database()),
            $this->guard('queue', 'Queue (WhatsApp, PDFs)', fn () => $this->queue()),
            $this->guard('backup', 'Last backup', fn () => $this->backup()),
            $this->guard('storage', 'Storage writable', fn () => $this->storage()),
            $this->guard('scheduler', 'Scheduler (cron)', fn () => $this->scheduler()),
        ];
    }

    public function allOk(?array $results = null): bool
    {
        return collect($results ?? $this->run())->every(fn ($r) => $r['ok']);
    }

    /** Called every minute by the scheduler (routes/console.php). */
    public static function beat(): void
    {
        Cache::forever(config('ops.health.heartbeat_key'), now()->getTimestamp());
    }

    private function guard(string $key, string $label, callable $check): array
    {
        try {
            [$ok, $detail] = $check();
        } catch (Throwable $e) {
            [$ok, $detail] = [false, class_basename($e).': '.str($e->getMessage())->limit(160)];
        }

        return ['key' => $key, 'label' => $label, 'ok' => $ok, 'detail' => $detail];
    }

    private function database(): array
    {
        $started = microtime(true);
        DB::select('select 1');
        $ms = (int) round((microtime(true) - $started) * 1000);

        return [true, DB::connection()->getDriverName()." answered in {$ms} ms"];
    }

    private function queue(): array
    {
        if (config('queue.default') !== 'database') {
            return [true, 'Queue driver is '.config('queue.default').' (not checked)'];
        }

        $table = config('queue.connections.database.table', 'jobs');
        $waiting = DB::table($table)->whereNull('reserved_at')->count();
        $oldest = DB::table($table)->whereNull('reserved_at')->min('available_at');
        $failed = Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count()
            : 0;

        $suffix = $failed ? ", {$failed} failed in the last 24 h" : '';
        if ($oldest === null) {
            return [true, 'No jobs waiting'.$suffix];
        }

        $lag = (int) floor((now()->getTimestamp() - (int) $oldest) / 60);
        $limit = config('ops.health.queue_lag_minutes');
        if ($lag > $limit) {
            return [false, "{$waiting} jobs waiting, oldest for {$lag} min (limit {$limit}). Is the cron running?{$suffix}"];
        }

        return [true, "{$waiting} jobs waiting, oldest for {$lag} min".$suffix];
    }

    private function backup(): array
    {
        $disk = config('backup.backup.destination.disks')[0] ?? 'backups';
        $newest = BackupDestination::create($disk, config('backup.backup.name'))->newestBackup();

        if (! $newest) {
            return [false, 'No backup found yet'];
        }

        $hours = (int) floor($newest->date()->diffInMinutes(now(), true) / 60);
        $limit = config('ops.health.backup_max_age_hours');
        $mb = number_format($newest->sizeInBytes() / 1048576, 1);
        $when = $newest->date()->copy()->timezone('Asia/Kolkata')->format('d M Y, H:i').' IST';

        return [$hours <= $limit, "{$when} ({$hours} h ago, {$mb} MB)".($hours > $limit ? " — older than {$limit} h" : '')];
    }

    private function storage(): array
    {
        $bad = collect(config('ops.health.writable_paths'))
            ->reject(function (string $dir) {
                if (! is_dir($dir) || ! is_writable($dir)) {
                    return false;
                }
                $probe = $dir.'/.health-'.bin2hex(random_bytes(4));

                return @file_put_contents($probe, 'ok') !== false && @unlink($probe);
            })
            ->map(fn ($dir) => str_replace(base_path().'/', '', $dir));

        return $bad->isEmpty()
            ? [true, 'All app folders are writable']
            : [false, 'Not writable: '.$bad->implode(', ')];
    }

    private function scheduler(): array
    {
        $ts = Cache::get(config('ops.health.heartbeat_key'));
        if (! $ts) {
            return [false, 'No heartbeat yet. Check the cron line (crm/DEPLOY.md, step 8)'];
        }

        $minutes = (int) floor((now()->getTimestamp() - (int) $ts) / 60);
        $limit = config('ops.health.heartbeat_max_minutes');
        $at = Carbon::createFromTimestamp((int) $ts)->timezone('Asia/Kolkata')->format('d M, H:i').' IST';

        return $minutes <= $limit
            ? [true, "Last run {$at}"]
            : [false, "Last run {$at}, {$minutes} min ago. Check the cron line"];
    }
}
