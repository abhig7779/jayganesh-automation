<?php

/*
 * Production operations: security headers, HTTPS, health checks and the daily error email.
 * See crm/DEPLOY.md. Every value can be changed in the server .env without a code change.
 */
return [

    // Content-Security-Policy: "enforce" (default), "report-only" (logs in the browser console
    // but blocks nothing; use it if a new screen suddenly stops working) or "off".
    'csp' => env('SECURITY_CSP', 'enforce'),

    // In production, send plain http:// visitors to https:// (GET/HEAD only).
    // Turn off only if the host's own "force HTTPS" causes a redirect loop.
    'force_https' => (bool) env('FORCE_HTTPS', true),

    'health' => [
        // A queued job waiting longer than this means the cron / queue worker is stuck.
        'queue_lag_minutes' => (int) env('HEALTH_QUEUE_LAG_MINUTES', 10),
        // The nightly backup runs at 23:30 IST, so anything older than ~1 day is a failure.
        'backup_max_age_hours' => (int) env('HEALTH_BACKUP_MAX_AGE_HOURS', 26),
        // The scheduler writes a heartbeat every minute.
        'heartbeat_max_minutes' => (int) env('HEALTH_HEARTBEAT_MAX_MINUTES', 3),
        'heartbeat_key' => 'ops:scheduler-heartbeat',
        // Folders the app must be able to write to.
        'writable_paths' => [
            storage_path('app'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ],
    ],

    'error_digest' => [
        // A daily email listing the errors from the last 24 hours. Skipped when there are none.
        'enabled' => (bool) env('ERROR_DIGEST_ENABLED', true),
        'to' => env('ERROR_DIGEST_EMAIL', env('BACKUP_NOTIFY_EMAIL')),
        'log_path' => storage_path('logs'),
    ],
];
