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
        'reminders.manage', // --- reminders module: service check-ups + Google review requests (owner, admin, marketing; not sales) ---
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
            'reminders.manage', // --- reminders module ---
        ],
        'finance' => [
            'customers.view', 'invoices.view', 'payments.collect',
            'purchases.manage', 'finance.view', 'finance.manage',
            'reports.view', 'reports.all_staff', 'inventory.view',
        ],
        'sales' => [
            'customers.view', 'customers.manage', 'quotations.view', 'quotations.manage',
            'invoices.view', 'invoices.create', 'payments.collect',
            'inventory.view', 'jobcards.view', 'jobcards.create',
        ],
        // Marketing (owner, 5 Nov 2026): ad leads, offers and audiences live here, NOT on the
        // counter. Give it to whoever handles marketing, or switch the same permissions on for
        // one person from Staff → Extra access.
        'marketing' => [
            'customers.view', 'quotations.view', 'leads.manage', 'offers.manage', 'reports.view',
            'reminders.manage', // --- reminders module ---
        ],
        // Leads/marketing are also given per person (owner rule, 5 Nov): the `leads.manage`
        // permission switched on for one user directly. `reports.view` here opens Top products
        // only; registers, GST, CA and daily sales need finance.view / reports.all_staff (H4).
        'technician' => ['jobcards.view', 'jobcards.manage'],
    ];

    public const ROLE_LABELS = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'finance' => 'Finance',
        'sales' => 'Sales / Counter',
        'marketing' => 'Marketing',
        'technician' => 'Technician',
    ];

    /**
     * Extra access the Owner can switch on for one person on top of their role
     * (Staff → Extra access). Keys are permissions; values are plain-English labels.
     */
    public const EXTRA_ACCESS = [
        'leads.manage' => 'Ad & WhatsApp leads',
        'offers.manage' => 'Send offers to customers',
        'reminders.manage' => 'Service reminders & review requests', // --- reminders module ---
        'reports.view' => 'Reports',
        'finance.view' => 'Finance (view)',
        'invoices.cancel' => 'Cancel bills',
        'prices.manage' => 'Change prices',
        'inventory.manage' => 'Edit products & stock',
    ];
}
