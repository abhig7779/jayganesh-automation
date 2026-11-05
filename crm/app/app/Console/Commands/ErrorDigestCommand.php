<?php

namespace App\Console\Commands;

use App\Mail\ErrorDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a summary of ERROR / CRITICAL / ALERT / EMERGENCY log entries from the last 24 hours.
 * Scheduled daily at 08:00 IST. Sends nothing when there were no errors.
 * Toggle: ERROR_DIGEST_ENABLED; recipient: ERROR_DIGEST_EMAIL (falls back to BACKUP_NOTIFY_EMAIL).
 */
class ErrorDigestCommand extends Command
{
    protected $signature = 'crm:error-digest {--hours=24 : How far back to look}';

    protected $description = 'Email a daily summary of errors from the log (skipped when there are none)';

    private const LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    public function handle(): int
    {
        if (! config('ops.error_digest.enabled')) {
            $this->info('Error digest is turned off (ERROR_DIGEST_ENABLED=false).');

            return self::SUCCESS;
        }

        $to = config('ops.error_digest.to');
        if (! $to) {
            $this->warn('No recipient: set ERROR_DIGEST_EMAIL or BACKUP_NOTIFY_EMAIL in .env.');

            return self::SUCCESS;
        }

        $since = now()->subHours((int) $this->option('hours'));
        $groups = $this->collect($since);

        if ($groups === []) {
            $this->info('No errors in the log. Nothing sent.');

            return self::SUCCESS;
        }

        Mail::to($to)->send(new ErrorDigest($groups, $since));
        $this->info(sprintf('Sent %d error group(s) to %s.', count($groups), $to));

        return self::SUCCESS;
    }

    /**
     * @return list<array{level: string, message: string, count: int, last: string}>
     */
    public function collect(Carbon $since): array
    {
        $files = glob(rtrim(config('ops.error_digest.log_path'), '/').'/*.log') ?: [];
        $groups = [];

        foreach ($files as $file) {
            if (filemtime($file) < $since->getTimestamp()) {
                continue;
            }
            $handle = fopen($file, 'r');
            while (($line = fgets($handle)) !== false) {
                // [2026-11-05 10:00:00] production.ERROR: message {"context"}
                if (! preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\] \w+\.([A-Z]+): (.*)$/', $line, $m)) {
                    continue;
                }
                if (! in_array($m[2], self::LEVELS, true)) {
                    continue;
                }
                $at = Carbon::parse($m[1]);
                if ($at->lt($since)) {
                    continue;
                }
                $message = $this->clean($m[3]);
                $key = $m[2].'|'.$message;
                $groups[$key] ??= ['level' => $m[2], 'message' => $message, 'count' => 0, 'ts' => 0];
                $groups[$key]['count']++;
                $groups[$key]['ts'] = max($groups[$key]['ts'], $at->getTimestamp());
            }
            fclose($handle);
        }

        return collect($groups)->sortByDesc('count')->take(50)
            ->map(fn ($g) => [
                'level' => $g['level'], 'message' => $g['message'], 'count' => $g['count'],
                'last' => Carbon::createFromTimestamp($g['ts'])->timezone('Asia/Kolkata')->format('d M, H:i'),
            ])->values()->all();
    }

    /** Drop the JSON context, shorten, and mask phone numbers (no customer data in email). */
    private function clean(string $message): string
    {
        $message = preg_replace('/\s\{".*$|\s\[\]\s*\[\]$|\s\{"exception".*$/', '', trim($message));
        $message = preg_replace_callback('/\+?\d[\d\s-]{8,}\d/', fn ($m) => str_repeat('•', 6).substr(preg_replace('/\D/', '', $m[0]), -4), $message);

        return (string) str($message)->limit(240);
    }
}
