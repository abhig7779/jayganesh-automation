<?php

namespace App\Support;

/**
 * Single list of every permission and the default role matrix (CRM-PLAN.md §4).
 * Roles and permissions live in the database (spatie/laravel-permission); this class
 * only seeds the defaults. New roles can be added from the database later without code.
 */
final class Permissions
{
    public const ALL = [
        'customers.view', 'customers.manage',
        'quotations.view', 'quotations.manage',
        'invoices.view', 'invoices.create', 'invoices.cancel',
        'payments.collect',
        'inventory.view', 'inventory.manage', 'prices.manage',
        'purchases.manage',
        'jobcards.view', 'jobcards.manage',
        'jobcards.create', // job cards module: open "Car in" cards and see the whole board (Sales, Admin, Owner)
        'finance.view', 'finance.manage',
        'leads.manage', 'offers.manage',
        'reports.view', 'reports.margins', 'reports.all_staff',
        'settings.manage', 'staff.manage', 'audit.view',
    ];

    public const ROLES = [
        'owner' => ['*'],
        'admin' => [
            'customers.view', 'customers.manage', 'quotations.view', 'quotations.manage',
            'invoices.view', 'invoices.create', 'invoices.cancel', 'payments.collect',
            'inventory.view', 'inventory.manage', 'purchases.manage',
            'jobcards.view', 'jobcards.manage', 'jobcards.create', 'finance.view',
            'leads.manage', 'offers.manage', 'reports.view', 'reports.all_staff',
            'staff.manage', 'audit.view',
        ],
        'finance' => [
            'customers.view', 'invoices.view', 'payments.collect',
            'purchases.manage', 'finance.view', 'finance.manage',
            'reports.view', 'reports.all_staff', 'inventory.view',
        ],
        'sales' => [
            'customers.view', 'customers.manage', 'quotations.view', 'quotations.manage',
            'invoices.view', 'invoices.create', 'payments.collect',
            'inventory.view', 'jobcards.view', 'jobcards.create', 'leads.manage',
        ],
        'technician' => ['jobcards.view', 'jobcards.manage'],
    ];

    public const ROLE_LABELS = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'finance' => 'Finance',
        'sales' => 'Sales / Counter',
        'technician' => 'Technician',
    ];
}
