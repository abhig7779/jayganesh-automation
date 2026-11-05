<?php

use Illuminate\Support\Facades\Route;

// Menu for the app shell. An item switches on by itself as soon as its module registers the
// route name (routes/modules/*.php); until then it shows as "Soon". 'can' is the permission
// needed to see it. Route names are fixed in crm/CONTRACTS.md, so modules never edit this file.
$items = [
    ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'can' => null, 'mobile' => true],
    ['label' => 'New bill', 'route' => 'bills.create', 'icon' => 'plus', 'can' => 'invoices.create', 'mobile' => true, 'shortcut' => 'new-bill'],
    ['label' => 'Follow-ups', 'route' => 'followups.index', 'icon' => 'chat', 'can' => 'quotations.view'],
    ['label' => 'Customers', 'route' => 'customers.index', 'icon' => 'users', 'can' => 'customers.view', 'mobile' => true],
    ['label' => 'Quotations', 'route' => 'quotations.index', 'icon' => 'doc', 'can' => 'quotations.view'],
    ['label' => 'Bills', 'route' => 'bills.index', 'icon' => 'receipt', 'can' => 'invoices.view', 'mobile' => true],
    ['label' => 'Stock', 'route' => 'products.index', 'icon' => 'box', 'can' => 'inventory.view'],
    ['label' => 'Purchases', 'route' => 'purchases.index', 'icon' => 'archive', 'can' => 'purchases.manage'], // purchases module
    ['label' => 'Job cards', 'route' => 'jobcards.index', 'icon' => 'wrench', 'can' => 'jobcards.view'],
    ['label' => 'Finance', 'route' => 'finance.index', 'icon' => 'rupee', 'can' => 'finance.view'],
    ['label' => 'Leads', 'route' => 'leads.index', 'icon' => 'chat', 'can' => 'leads.manage'],
    ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'chart', 'can' => 'reports.view'],
    ['label' => 'Staff', 'route' => 'staff.index', 'icon' => 'id', 'can' => 'staff.manage'],
    ['label' => 'Activity', 'route' => 'activity.index', 'icon' => 'history', 'can' => 'audit.view'], // activity log
    ['label' => 'Settings', 'route' => 'settings.edit', 'icon' => 'cog', 'can' => 'settings.manage'],
];

return array_map(function (array $i) {
    $i['soon'] = ! Route::has($i['route']);

    return $i;
}, $items);
