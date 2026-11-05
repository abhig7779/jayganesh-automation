<?php

namespace App\Console\Commands;

use App\Support\Ops\HealthCheck;
use Illuminate\Console\Command;

class HealthCommand extends Command
{
    protected $signature = 'crm:health';

    protected $description = 'Check the database, queue, last backup, storage and scheduler; prints OK or FAIL';

    public function handle(HealthCheck $health): int
    {
        $results = $health->run();

        foreach ($results as $r) {
            $status = $r['ok'] ? '<fg=green>OK  </>' : '<fg=red>FAIL</>';
            $this->line(sprintf('%s  %-24s %s', $status, $r['label'], $r['detail']));
        }

        $ok = $health->allOk($results);
        $this->newLine();
        $ok ? $this->info('OK: everything is healthy.') : $this->error('FAIL: see the lines above.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
