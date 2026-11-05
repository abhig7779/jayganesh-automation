<?php

// Menu for the app shell. 'soon' items are modules from later build steps; they show as
// disabled so staff can see what is coming. 'can' is the permission needed to see the item.
return [
    ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'can' => null, 'mobile' => true],
    ['label' => 'New bill', 'route' => null, 'icon' => 'plus', 'can' => 'invoices.create', 'soon' => true, 'mobile' => true, 'shortcut' => 'new-bill'],
    ['label' => 'Customers', 'route' => null, 'icon' => 'users', 'can' => 'customers.view', 'soon' => true, 'mobile' => true],
    ['label' => 'Quotations', 'route' => null, 'icon' => 'doc', 'can' => 'quotations.view', 'soon' => true],
    ['label' => 'Bills', 'route' => null, 'icon' => 'receipt', 'can' => 'invoices.view', 'soon' => true],
    ['label' => 'Stock', 'route' => null, 'icon' => 'box', 'can' => 'inventory.view', 'soon' => true, 'mobile' => true],
    ['label' => 'Job cards', 'route' => null, 'icon' => 'wrench', 'can' => 'jobcards.view', 'soon' => true],
    ['label' => 'Finance', 'route' => null, 'icon' => 'rupee', 'can' => 'finance.view', 'soon' => true],
    ['label' => 'Leads', 'route' => null, 'icon' => 'chat', 'can' => 'leads.manage', 'soon' => true],
    ['label' => 'Reports', 'route' => null, 'icon' => 'chart', 'can' => 'reports.view', 'soon' => true],
    ['label' => 'Staff', 'route' => 'staff.index', 'icon' => 'id', 'can' => 'staff.manage'],
    ['label' => 'Settings', 'route' => 'settings.edit', 'icon' => 'cog', 'can' => 'settings.manage'],
];
