<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

// Menu for the app shell. An item switches on by itself as soon as its module registers the
// route name (routes/modules/*.php); until then it shows as "Soon". Keys:
//   can      permission needed to see it (null = everyone)
//   when     optional fn (User): bool for rules a single permission can't say
//   active   route patterns that highlight it (default: the route's own prefix.*)
//   children sub-links shown under it in the PC sidebar (each with route, label, can, active)
//   badge    'followups' = red count of follow-ups due (cached 60 s per user)
// Route names are fixed in crm/CONTRACTS.md.

// The job board is for people who open cards; a technician only gets "My job cards".
$seesBoard = fn (User $u) => $u->can('jobcards.create');

$items = [
    ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home', 'can' => null, 'mobile' => true, 'active' => ['dashboard'],
        'when' => fn (User $u) => ! $u->hasRole('technician') || $seesBoard($u)],
    ['label' => 'My job cards', 'route' => 'jobcards.mine', 'icon' => 'wrench', 'can' => 'jobcards.view', 'mobile' => true, 'active' => ['jobcards.*', 'dashboard'],
        'when' => fn (User $u) => ! $seesBoard($u)],
    ['label' => 'New bill', 'route' => 'bills.create', 'icon' => 'plus', 'can' => 'invoices.create', 'mobile' => true, 'shortcut' => 'new-bill'],
    ['label' => 'Follow-ups', 'route' => 'followups.index', 'icon' => 'chat', 'can' => 'quotations.view', 'badge' => 'followups', 'active' => ['followups.*']],
    ['label' => 'Customers', 'route' => 'customers.index', 'icon' => 'users', 'can' => 'customers.view', 'mobile' => true, 'active' => ['customers.*'],
        'children' => [
            ['label' => 'Import customers', 'route' => 'customers.import', 'can' => 'customers.manage', 'when' => fn (User $u) => $u->hasAnyRole(['owner', 'admin']), 'active' => ['customers.import*']],
        ]],
    ['label' => 'Quotations', 'route' => 'quotations.index', 'icon' => 'doc', 'can' => 'quotations.view'],
    ['label' => 'Bills', 'route' => 'bills.index', 'icon' => 'receipt', 'can' => 'invoices.view', 'mobile' => true],
    ['label' => 'Stock', 'route' => 'products.index', 'icon' => 'box', 'can' => 'inventory.view', 'active' => ['products.*', 'stock.*']],
    ['label' => 'Purchases', 'route' => 'purchases.index', 'icon' => 'box', 'can' => 'purchases.manage', 'active' => ['purchases.*', 'suppliers.*']],
    ['label' => 'Job cards', 'route' => 'jobcards.index', 'icon' => 'wrench', 'can' => 'jobcards.view', 'when' => $seesBoard],
    ['label' => 'Finance', 'route' => 'finance.index', 'icon' => 'rupee', 'can' => 'finance.view', 'active' => ['finance.index'],
        'children' => [
            ['label' => 'Expenses', 'route' => 'finance.expenses.index', 'can' => 'finance.view', 'active' => ['finance.expenses.*']],
            ['label' => 'Cash close', 'route' => 'finance.cash-close', 'can' => 'finance.view', 'active' => ['finance.cash-close', 'finance.cash-close.*', 'finance.cash-closes.*']],
            ['label' => 'Dues', 'route' => 'finance.dues', 'can' => 'finance.view', 'active' => ['finance.dues']],
        ]],
    ['label' => 'Reminders', 'route' => 'reminders.index', 'icon' => 'bell', 'can' => 'reminders.manage', 'active' => ['reminders.*']],
    ['label' => 'Leads', 'route' => 'leads.index', 'icon' => 'chat', 'can' => 'leads.manage'],
    ['label' => 'Offers', 'route' => 'offers.index', 'icon' => 'megaphone', 'can' => 'offers.manage', 'active' => ['offers.*', 'audiences.*']],
    ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'chart', 'can' => 'reports.view'],
    ['label' => 'Staff', 'route' => 'staff.index', 'icon' => 'id', 'can' => 'staff.manage'],
    ['label' => 'Activity log', 'route' => 'activity.index', 'icon' => 'doc', 'can' => 'audit.view', 'active' => ['activity.*']],
    ['label' => 'Settings', 'route' => 'settings.edit', 'icon' => 'cog', 'can' => 'settings.manage', 'active' => ['settings.*'],
        'children' => [
            ['label' => 'API keys', 'route' => 'api-tokens.index', 'can' => 'settings.manage', 'active' => ['api-tokens.*']],
            ['label' => 'Connect WhatsApp', 'route' => 'whatsapp.connect', 'can' => 'settings.manage', 'active' => ['whatsapp.connect*']],
            ['label' => 'WhatsApp log', 'route' => 'whatsapp.messages.index', 'can' => 'settings.manage', 'active' => ['whatsapp.messages.*']],
        ]],
];

return array_map(function (array $i) {
    $i['soon'] = ! Route::has($i['route']);
    $i['children'] = array_values(array_filter($i['children'] ?? [], fn ($c) => Route::has($c['route'])));

    return $i;
}, $items);
