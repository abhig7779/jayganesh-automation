<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Support\Ops\HealthCheck;

/** Owner-only system health page: the same checks as `php artisan crm:health`. */
class HealthController extends Controller
{
    public function __invoke(HealthCheck $health)
    {
        $results = $health->run();

        return view('ops.health', [
            'results' => $results,
            'allOk' => $health->allOk($results),
            'php' => PHP_VERSION,
            'checkedAt' => now()->timezone('Asia/Kolkata'),
        ]);
    }
}
