<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Spatie\DbDumper\Compressors\GzipCompressor;

/*
 * Nightly backup (spatie/laravel-backup), scheduled in routes/console.php at 23:30 IST.
 *
 * One zip = a gzipped dump of the database + storage/app/private (bills, job photos, uploads).
 * Zips land in storage/app/backups/<BACKUP_NAME>/ (the "backups" disk), are kept 30 days,
 * and a GitHub Action copies the newest one to Google Drive at 01:00 IST (crm/DEPLOY.md).
 * Restore steps: crm/RESTORE.md.
 */
$name = env('BACKUP_NAME', 'jayganesh-crm');

return [

    'backup' => [
        // Also the folder name inside the backups disk. No spaces, so scp/ssh paths stay simple.
        'name' => $name,

        'source' => [
            'files' => [
                'include' => [
                    storage_path('app/private'),
                ],
                'exclude' => [
                    storage_path('app/backups'),
                    storage_path('app/backup-temp'),
                ],
                'follow_links' => false,
                'ignore_unreadable_directories' => true,
                // Paths inside the zip start at storage/app/private/… (easy to put back).
                'relative_path' => base_path(),
            ],

            // The main connection (mysql on the server). The dump options live in config/database.php.
            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        'database_dump_compressor' => GzipCompressor::class,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => '',
            'disks' => [
                'backups',
            ],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        // Strongly recommended: the zip is copied to Google Drive. Keep this password in the
        // owner's password manager too, or old backups can't be opened (crm/RESTORE.md).
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',

        'tries' => 2,
        'retry_delay' => 60,
    ],

    /*
     * Email only when something is wrong. Successful runs stay quiet.
     */
    'notifications' => [
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            // Must be a valid address, even before the real one is set in .env.
            'to' => env('BACKUP_NOTIFY_EMAIL', 'change-me@example.com'),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Jay Ganesh CRM'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],
    ],

    /*
     * backup:monitor (daily, 09:00 IST) emails if the newest backup is over a day old
     * or the folder grows past 5 GB.
     */
    'monitor_backups' => [
        [
            'name' => $name,
            'disks' => ['backups'],
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    /*
     * Keep every backup for 30 days, then delete it. The newest backup is never deleted.
     */
    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 30,
            'keep_daily_backups_for_days' => 0,
            'keep_weekly_backups_for_weeks' => 0,
            'keep_monthly_backups_for_months' => 0,
            'keep_yearly_backups_for_years' => 0,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        'tries' => 1,
        'retry_delay' => 0,
    ],

];
