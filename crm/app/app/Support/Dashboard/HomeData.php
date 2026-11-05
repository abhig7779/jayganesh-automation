<?php

namespace App\Support\Dashboard;

use App\Models\Customer;
use App\Models\JobCard;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Money;
use App\Support\RegNo;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the role-aware Home shows (UX-FLOWS §2, DESIGN §7), in one place.
 *
 * Modules merge at different times, so every query on a table this branch doesn't own is
 * guarded by has*() (class_exists + Schema::hasTable). A section whose module is missing
 * returns `null`, and the view shows a calm "switches on with …" state instead of breaking.
 *
 * Quotations / follow-ups, job cards, products, finance and offers are merged: their sections call
 * the modules' own helpers (Dashboard\FollowUps, Workshop, LowStock, FinanceHome, FinanceAlerts,
 * Offers). Billing (invoices / payments) is still to come: the remaining "WIRE:" comments mark
 * those guarded queries. Check their column names against the billing migrations when it merges.
 */
class HomeData
{
    public const INVOICE = 'App\\Models\\Invoice';

    public const PAYMENT = 'App\\Models\\Payment';

    public const QUOTATION = 'App\\Models\\Quotation';

    public const FOLLOW_UP = 'App\\Models\\FollowUp';

    public const JOB_CARD = 'App\\Models\\JobCard';

    /** The shop's clock. The database stores UTC, so day boundaries are converted back to UTC. */
    public const TZ = 'Asia/Kolkata';

    /** Start of today in the shop's time zone, expressed in UTC (safe to compare with timestamps). */
    private CarbonImmutable $today;

    public function __construct(private readonly User $user, ?CarbonImmutable $now = null)
    {
        $this->today = ($now ?? CarbonImmutable::now())->setTimezone(self::TZ)->startOfDay()->utc();
    }

    /**
     * owner | admin | finance | sales | marketing | technician: decides which Home layout is used.
     * What appears inside a layout is decided by permissions ($user->can), never by role name,
     * so Extra access switched on for one person shows up on their Home too.
     */
    public function role(): string
    {
        foreach (['owner', 'admin', 'finance', 'sales', 'marketing', 'technician'] as $role) {
            if ($this->user->hasRole($role)) {
                return $role;
            }
        }

        return 'sales';
    }

    /** Everything one role's view needs. */
    public function toArray(): array
    {
        $role = $this->role();
        $data = [
            'role' => $role,
            'user' => $this->user,
            'today' => $this->localToday(),
            'tiles' => $this->tiles(),
        ];

        return $data + match ($role) {
            'owner', 'admin' => [
                'business' => $this->businessToday(),
                'attention' => $this->needsAttention(),
                'followUps' => $this->followUpsToday(),
                'lowStock' => $this->lowStockItems(),
                'drafts' => $this->draftBills(),
                'staffToday' => $this->staffToday(),
                'workshop' => $this->workshop(),
                'month' => $this->thisMonth(),
                'recentBills' => $this->recentBills(),
                'finance' => $this->financeToday(),
                'offers' => $this->recentOffers(),
                'showMargins' => $this->user->can('reports.margins'),
            ],
            'finance' => [
                'collections' => $this->collectionsToday(),
                'duesAgeing' => $this->duesAgeing(),
                'recentBills' => $this->recentBills(),
                'newCustomers' => $this->newCustomersToday(),
                'finance' => $this->financeToday(),
            ],
            'technician' => $this->technicianHome(),
            default => [
                'myToday' => $this->myToday(),
                'followUps' => $this->followUpsToday(),
                'waiting' => $this->waitingOnMe(),
                'drafts' => $this->draftBills(),
                'lowStock' => $this->lowStockItems(),
                'workshop' => $this->user->can('jobcards.view') ? $this->workshop() : null,
                'recentBills' => $this->recentBills(onlyMine: true),
                'offers' => $this->recentOffers(),
            ],
        };
    }

    // ---------------------------------------------------------------- action tiles

    /**
     * Tiles in the approved order. A tile shows only when its route exists AND the user has the
     * permission, so nobody sees a dead link or a greyed-out button (UX-FLOWS §0 rule 10).
     * Billing tiles (New bill, Collect payment) appear on their own when billing registers its routes.
     *
     * @return array<int, array{key:string,label:string,icon:string,url:string,hint:?string,hintTone:?string,primary:bool,shortcut:?string}>
     */
    public function tiles(): array
    {
        $defs = match ($this->role()) {
            'finance' => [
                ['collect', 'Collect payment', 'cash', 'payments.collect', 'payments.collect', 'Customer pays an old due', 'F8'],
                ['expense', 'Add expense', 'rupee', 'finance.expenses.create', 'finance.manage', 'Shop spends', 'F6'],
                ['cashclose', 'Cash close', 'lock', 'finance.cash-close', 'finance.manage', 'Count the drawer', 'F7'],
                ['supplier', 'Supplier payment', 'clipboard', 'supplier-payments.create', 'purchases.manage', 'Pay a supplier', null],
                ['dues', 'Dues list', 'clock', 'finance.dues', 'finance.view', 'Who owes the shop', 'F9'],
                ['report', "Today's report", 'chart', 'reports.index', 'reports.view', 'Day closing & cash', null],
            ],
            'technician' => [],
            default => [
                ['new-bill', 'New bill', 'receipt', 'bills.create', 'invoices.create', 'Customer, items, payment', 'F2'],
                ['quote', 'New quotation', 'doc', 'quotations.create', 'quotations.manage', 'Estimate on WhatsApp', 'F6'],
                ['customer', 'Add customer', 'user-plus', 'customers.create', 'customers.manage', 'Mobile number first', 'F7'],
                ['collect', 'Collect payment', 'cash', 'payments.collect', 'payments.collect', null, 'F8'],
                ['car-in', 'Car in', 'car', 'jobcards.create', 'jobcards.create', 'New bay intake', 'F9'],
                ['stock', 'Check price', 'box', 'stock.check', 'inventory.view', 'Stock, price & MRP', 'F4'],
                ['report', "Today's report", 'chart', 'reports.index', 'reports.view', 'Day closing & cash', null],
                // Marketing tools show only for people given them (role Marketing, or Staff → Extra access).
                ['offer', 'Send offer', 'megaphone', 'offers.create', 'offers.manage', 'WhatsApp customers', null],
                ['leads', 'Leads', 'chat', 'leads.index', 'leads.manage', 'Ad & WhatsApp enquiries', null],
            ],
        };

        // Owner/Admin get the full counter row, so Leads would only crowd it; it stays in the menu.
        if (in_array($this->role(), ['owner', 'admin', 'sales'], true)) {
            $defs = array_values(array_filter($defs, fn ($d) => $d[0] !== 'leads'));
        }

        $tiles = [];
        foreach ($defs as [$key, $label, $icon, $route, $permission, $hint, $fkey]) {
            if (! $this->user->can($permission) || ! Route::has($route)) {
                continue;
            }
            $tile = [
                'key' => $key, 'label' => $label, 'icon' => $icon, 'hint' => $hint, 'hintTone' => null,
                'url' => route($route),
                'primary' => $key === 'new-bill', 'shortcut' => $fkey,
            ];
            if ($key === 'collect' && ($due = $this->totalDuePaise()) > 0) {
                [$tile['hint'], $tile['hintTone']] = [Money::format($due).' due', 'bad'];
            } elseif ($key === 'collect') {
                $tile['hint'] ??= 'Old dues';
            }
            $tiles[] = $tile;
        }

        return $tiles;
    }

    /** Every route name Home can link to (tiles and panels), for the no-dead-links test. */
    public static function tileRouteNames(): array
    {
        return [
            'payments.collect', 'finance.expenses.create', 'finance.cash-close', 'supplier-payments.create', 'finance.dues',
            'reports.index', 'bills.create', 'quotations.create', 'customers.create', 'jobcards.create', 'stock.check',
            'offers.create', 'leads.index',
        ];
    }

    // ---------------------------------------------------------------- customers (real now)

    public function newCustomersToday(?User $by = null): int
    {
        return Customer::query()
            ->where('created_at', '>=', $this->today)
            ->when($by, fn ($q) => $q->where('created_by', $by->id))
            ->count();
    }

    public function newCustomersThisMonth(): int
    {
        return Customer::query()->where('created_at', '>=', $this->today->startOfMonth())->count();
    }

    // ---------------------------------------------------------------- sales / counter

    /** My bills, my sales, cash I collected today, plus new customers I added (real). */
    public function myToday(): array
    {
        $out = ['newCustomers' => $this->newCustomersToday($this->user), 'billing' => false];

        if ($this->hasInvoices()) {
            // WIRE: invoices(created_by, total_paise, status, created_at); payments(received_by, mode, amount_paise).
            $out = $this->safe(function () use ($out) {
                $mine = $this->invoicesToday()->where('created_by', $this->user->id);
                $lastWeek = $this->invoiceQuery()->where('status', '!=', 'cancelled')->where('created_by', $this->user->id)
                    ->whereBetween('created_at', [$this->today->subWeek(), $this->today->subWeek()->endOfDay()]);
                $cash = $this->hasPayments()
                    ? (int) (self::PAYMENT)::query()->where('received_by', $this->user->id)->where('mode', 'cash')
                        ->where('created_at', '>=', $this->today)->sum('amount_paise')
                    : null;

                return $out + [
                    'billing' => true,
                    'bills' => (clone $mine)->count(),
                    'salesPaise' => (int) (clone $mine)->sum('total_paise'),
                    'lastWeekPaise' => (int) $lastWeek->sum('total_paise'),
                    'cashPaise' => $cash,
                    'modes' => $this->hasPayments()
                        ? (self::PAYMENT)::query()->where('received_by', $this->user->id)->where('created_at', '>=', $this->today)
                            ->selectRaw('mode, SUM(amount_paise) as total')->groupBy('mode')->pluck('total', 'mode')->map(fn ($v) => (int) $v)->all()
                        : [],
                ];
            }, $out);
        }

        return $out;
    }

    /** Drafts to finish (billing), open quotes, visits due today, stale quotes and new leads assigned to me. */
    public function waitingOnMe(): ?array
    {
        if (! $this->hasInvoices() && ! $this->hasQuotations()) {
            return null;
        }

        $rows = [];
        if ($this->hasInvoices()) {
            // WIRE: invoices.status = 'draft' (billing not merged yet)
            $rows = array_merge($rows, $this->safe(function () {
                $drafts = $this->invoiceQuery()->where('status', 'draft')->where('created_by', $this->user->id)->count();

                return $drafts ? [['icon' => 'receipt', 'tone' => 'warn', 'title' => $drafts.' draft '.str('bill')->plural($drafts).' to finish', 'subtitle' => 'Saved automatically', 'url' => $this->routeOrNull('bills.index', ['status' => 'draft'])]] : [];
            }, []));
        }

        if ($this->hasQuotations() && $this->user->can('quotations.view')) {
            // quotations(assigned_to, status open|visit_booked, visit_on, total_paise, revised_to_id)
            $mine = fn () => Quotation::query()->where('assigned_to', $this->user->id)
                ->whereNull('revised_to_id')->whereIn('status', Quotation::ACTIVE);
            $today = $this->localToday()->toDateString();

            $visits = $mine()->where('status', Quotation::VISIT_BOOKED)->whereDate('visit_on', $today)->count();
            if ($visits) {
                $rows[] = ['icon' => 'car', 'tone' => 'ok', 'title' => $visits.' '.str('customer')->plural($visits).' coming in today', 'subtitle' => 'Visit booked from a quote', 'url' => $this->routeOrNull('quotations.index', ['status' => Quotation::VISIT_BOOKED])];
            }
            $open = $mine()->count();
            if ($open) {
                $value = (int) $mine()->sum('total_paise');
                $rows[] = ['icon' => 'doc', 'tone' => null, 'title' => $open.' open '.str('quotation')->plural($open), 'subtitle' => Money::format($value).' waiting to be won', 'url' => $this->routeOrNull('quotations.index', ['filter' => 'mine'])];
            }
            $stale = $mine()->where('created_at', '<', $this->today->subDays(7))->count();
            if ($stale) {
                $rows[] = ['icon' => 'clock', 'tone' => 'warn', 'title' => $stale.' '.str('quotation')->plural($stale).' open for over a week', 'subtitle' => 'Call or mark lost', 'url' => $this->routeOrNull('quotations.index', ['filter' => 'mine'])];
            }
        }

        if (class_exists(Lead::class) && Schema::hasTable('leads') && $this->user->can('leads.manage')) {
            $new = Lead::query()->where('assigned_to', $this->user->id)->where('status', Lead::NEW)->count();
            if ($new) {
                $rows[] = ['icon' => 'chat', 'tone' => 'warn', 'title' => $new.' new '.str('enquiry')->plural($new).' to answer', 'subtitle' => 'Ad and WhatsApp leads', 'url' => $this->routeOrNull('leads.index', ['status' => Lead::NEW])];
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------- follow-ups

    /**
     * "Follow-ups today" from the quotations module (Dashboard\FollowUps): overdue first, then
     * today's. Null when this person can't chase quotes or leads.
     *
     * @return array{count:int, overdue:int, rows:array}|null
     */
    public function followUpsToday(int $limit = 6): ?array
    {
        if (! $this->hasFollowUps() || ! ($this->user->can('quotations.view') || $this->user->can('leads.manage'))) {
            return null;
        }

        $rows = array_map(fn (array $r) => [
            'id' => $r['id'],
            'name' => $r['name'],
            'car' => trim($r['car_model'].($r['plate'] ? ' · '.$r['plate'] : '')),
            'detail' => trim($r['kind_label'].' '.$r['value']).($r['asked'] ? ' · '.$r['asked'] : ''),
            'overdue' => $r['overdue'],
            'when' => $r['overdue'] ? 'Overdue '.str_replace(' late', '', $r['due_label']).' · '.$r['meta'] : 'Today · '.$r['meta'],
            'assignee' => $r['assignee'],
            'whatsapp' => $r['wa_url'],
            'call' => $r['call_url'],
            'url' => $this->routeOrNull('followups.index', ['select' => $r['id']]),
        ], FollowUps::rowsForUser($this->user, $limit));

        return [
            'count' => FollowUps::countForUser($this->user),
            'overdue' => FollowUps::overdueCountForUser($this->user),
            'rows' => $rows,
            'url' => $this->routeOrNull('followups.index'),
        ];
    }

    // ---------------------------------------------------------------- recent bills

    /** Last 5 bills, or null when billing isn't merged. */
    public function recentBills(bool $onlyMine = false): ?array
    {
        if (! $this->hasInvoices() || ! $this->user->can('invoices.view')) {
            return null;
        }

        // WIRE: invoices(number, total_paise, status, customer_id, created_at, whatsapp_status)
        return $this->safe(fn () => $this->invoiceQuery()
            ->when($onlyMine, fn ($q) => $q->where('created_by', $this->user->id))
            ->with('customer')->latest()->limit(5)->get()
            ->map(fn ($i) => [
                'name' => $i->customer?->name ?? 'Walk-in',
                'number' => $i->number ?? ('#'.$i->id),
                'time' => $i->created_at?->timezone(self::TZ)->format('h:i A'),
                'paise' => (int) $i->total_paise,
                'status' => $i->status ?? null,
                'whatsapp' => $i->whatsapp_status ?? null,
                'url' => $this->routeOrNull('bills.show', $i),
            ])->all(), []);
    }

    // ---------------------------------------------------------------- owner / admin

    /** Today's business strip. Billing numbers are null until billing merges; new customers are real. */
    public function businessToday(): array
    {
        $out = [
            'billing' => false,
            'newCustomers' => $this->newCustomersToday(),
            'newCustomersLastWeek' => Customer::query()
                ->whereBetween('created_at', [$this->today->subWeek(), $this->today->subWeek()->endOfDay()])->count(),
        ];
        if (! $this->hasInvoices()) {
            return $out;
        }

        // WIRE: invoices(total_paise, status), payments(mode, amount_paise)
        return $this->safe(function () use ($out) {
            $today = $this->invoicesToday();
            $lastWeek = $this->invoiceQuery()->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$this->today->subWeek(), $this->today->subWeek()->endOfDay()]);
            $bills = (clone $today)->count();
            $sales = (int) (clone $today)->sum('total_paise');
            $modes = $this->hasPayments()
                ? (self::PAYMENT)::query()->where('created_at', '>=', $this->today)
                    ->selectRaw('mode, SUM(amount_paise) as total')->groupBy('mode')->pluck('total', 'mode')->map(fn ($v) => (int) $v)->all()
                : [];

            return array_merge($out, [
                'billing' => true,
                'salesPaise' => $sales,
                'lastWeekPaise' => (int) $lastWeek->sum('total_paise'),
                'bills' => $bills,
                'lastWeekBills' => (clone $lastWeek)->count(),
                'avgPaise' => $bills ? intdiv($sales, $bills) : 0,
                'modes' => $modes,
            ]);
        }, $out);
    }

    /**
     * Owner's "Needs attention". Low stock is real (products table is on main); the rest
     * switch on as their modules merge. Each row: icon, tone, title, detail, url.
     */
    public function needsAttention(): array
    {
        $rows = [];

        if ($this->hasFollowUps() && ($overdue = FollowUps::overdueCountForUser($this->user)) > 0) {
            // By staff member, with the value of the quotes going cold (UX-FLOWS §2 Owner).
            $late = collect(FollowUps::rowsForUser($this->user, 200))->where('overdue', true);
            $who = $late->groupBy(fn ($r) => $r['assignee'] ?: 'Unassigned')
                ->map(fn ($g, $name) => \Illuminate\Support\Str::before($name, ' ').' '.$g->count())
                ->sortDesc()->take(3)->implode(' · ');
            $value = (int) $late->sum('value_paise');
            $rows[] = [
                'icon' => 'chat', 'tone' => 'bad',
                'title' => $overdue.' overdue '.str('follow-up')->plural($overdue).($value ? ' · '.Money::format($value) : ''),
                'detail' => $who ?: 'Quotes going cold',
                'url' => $this->routeOrNull('followups.index'),
            ];
        }

        if ($this->hasInvoices()) {
            // WIRE: invoices(due_paise, status, created_at, cancelled_by, cancel_reason)
            $rows = array_merge($rows, $this->safe(function () {
                $r = [];
                $old = $this->invoiceQuery()->where('due_paise', '>', 0)->where('status', '!=', 'cancelled')
                    ->where('created_at', '<', $this->today->subDays(15));
                $count = (clone $old)->distinct('customer_id')->count('customer_id');
                if ($count) {
                    $r[] = ['icon' => 'rupee', 'tone' => 'bad', 'title' => Money::format((int) (clone $old)->sum('due_paise')).' due over 15 days', 'detail' => $count.' '.str('customer')->plural($count), 'url' => $this->routeOrNull('payments.collect')];
                }
                $cancelled = $this->invoiceQuery()->where('status', 'cancelled')->where('updated_at', '>=', $this->today)->count();
                if ($cancelled) {
                    $r[] = ['icon' => 'receipt', 'tone' => 'warn', 'title' => $cancelled.' '.str('bill')->plural($cancelled).' cancelled today', 'detail' => 'See who and why', 'url' => $this->routeOrNull('bills.index', ['status' => 'cancelled'])];
                }
                if (Schema::hasColumn('invoices', 'whatsapp_status')) {
                    $failed = $this->invoiceQuery()->where('whatsapp_status', 'failed')->where('created_at', '>=', $this->today->subDays(2))->count();
                    if ($failed) {
                        $r[] = ['icon' => 'whatsapp', 'tone' => 'bad', 'title' => $failed.' WhatsApp '.str('bill')->plural($failed).' not sent', 'detail' => 'Resend or send manually', 'url' => $this->routeOrNull('whatsapp.messages.index')];
                    }
                }

                return $r;
            }, []));
        }

        if ($this->user->can('inventory.view') && ($low = $this->lowStockCount())) {
            $rows[] = ['icon' => 'box', 'tone' => 'warn', 'title' => $low.' '.str('item')->plural($low).' low on stock', 'detail' => 'Reorder before the weekend', 'url' => LowStock::url()];
        }

        if ($this->user->can('finance.view') && ($close = FinanceAlerts::missingCashClose($this->user->branch_id))) {
            $rows[] = ['icon' => 'lock', 'tone' => 'bad', 'title' => $close['label'], 'detail' => 'Count the drawer and close the day', 'url' => $close['url']];
        }

        return $rows;
    }

    /** Which modules are still to come, so the owner's "Needs attention" can say what it will watch. */
    public function pendingWatches(): array
    {
        return $this->hasInvoices() ? [] : ['old dues, cancelled bills and failed WhatsApp bills'];
    }

    /** Stocked products at or under their reorder level, out of stock included (products module). */
    public function lowStockCount(): int
    {
        return Schema::hasTable('products') ? LowStock::count() : 0;
    }

    /** Up to 5 low-stock products, emptiest first. */
    public function lowStockItems(int $limit = 5): array
    {
        if (! Schema::hasTable('products') || ! $this->user->can('inventory.view')) {
            return [];
        }

        return LowStock::items($limit)->map(fn ($p) => [
            'name' => $p->name,
            'detail' => trim(($p->brand?->name ?? 'Product').' · reorder at '.$p->reorder_level),
            'left' => (int) $p->on_hand,
            'url' => $this->routeOrNull('products.show', $p),
        ])->all();
    }

    /** Low-stock list link for "See all". */
    public function lowStockUrl(): ?string
    {
        return Route::has('products.index') ? LowStock::url() : null;
    }

    /** Draft bills to resume (null until billing merges). */
    public function draftBills(): ?array
    {
        if (! $this->hasInvoices() || ! $this->user->can('invoices.create')) {
            return null;
        }

        // WIRE: invoices.status = 'draft', total_paise, updated_at
        return $this->safe(fn () => $this->invoiceQuery()->where('status', 'draft')
            ->when(! $this->user->can('reports.all_staff'), fn ($q) => $q->where('created_by', $this->user->id))
            ->with('customer')->latest('updated_at')->limit(3)->get()
            ->map(fn ($i) => [
                'name' => $i->customer?->name ?? 'Walk-in',
                'detail' => Money::format((int) $i->total_paise).' · saved '.$i->updated_at?->timezone(self::TZ)->format('h:i A'),
                'url' => $this->routeOrNull('bills.create', ['draft' => $i->id]),
            ])->all(), []);
    }

    /** Red count on the Follow-ups menu item: overdue + due today. Cached 60 s per user; null when 0. */
    public static function followUpBadge(User $user): ?int
    {
        if (! ($user->can('quotations.view') || $user->can('leads.manage')) || ! Schema::hasTable('follow_ups')) {
            return null;
        }
        $count = Cache::remember(self::badgeCacheKey($user), 60, fn () => FollowUps::countForUser($user));

        return $count ?: null;
    }

    public static function badgeCacheKey(User $user): string
    {
        return 'home.followup-badge.'.$user->id;
    }

    /** Each staff member's bills and sales today; null until billing merges. */
    public function staffToday(): ?array
    {
        if (! $this->hasInvoices()) {
            return null;
        }

        // WIRE: invoices.created_by
        return $this->safe(function () {
            $rows = $this->invoicesToday()->selectRaw('created_by, COUNT(*) as bills, SUM(total_paise) as total')
                ->groupBy('created_by')->orderByDesc('total')->get();
            $names = User::query()->whereIn('id', $rows->pluck('created_by'))->pluck('name', 'id');
            $top = max(1, (int) $rows->max('total'));

            return $rows->map(fn ($r) => [
                'name' => $names[$r->created_by] ?? 'Staff',
                'bills' => (int) $r->bills,
                'paise' => (int) $r->total,
                'share' => (int) round(((int) $r->total) * 100 / $top),
            ])->all();
        }, []);
    }

    /**
     * Cars in the workshop by status (job cards module), plus the late ones.
     *
     * @return array{counts:array<string,array{label:string,count:int}>, in_shop:int, delivered_today:int, late:array, url:string}|null
     */
    public function workshop(): ?array
    {
        if (! $this->hasJobCards()) {
            return null;
        }
        $w = Workshop::summary($this->user->branch_id);
        $labels = [JobCard::WAITING => 'Waiting', JobCard::WORKING => 'Working', JobCard::QC => 'QC', JobCard::READY => 'Ready'];

        return [
            'counts' => collect($labels)->map(fn ($label, $key) => ['label' => $label, 'count' => (int) ($w['counts'][$key] ?? 0)])->all(),
            'in_shop' => $w['in_shop'],
            'delivered_today' => (int) ($w['counts']['delivered_today'] ?? 0),
            'late' => $w['late']->take(3)->map(fn (JobCard $j) => [
                'plate' => $j->plate() ?? $j->number,
                'detail' => trim($j->carLabel().' · '.($j->customer?->name ?? '').($j->technician ? ' · '.$j->technician->name : ''), ' ·'),
                'when' => $j->promiseText(),
                'url' => $this->routeOrNull('jobcards.show', $j),
            ])->values()->all(),
            'url' => $w['board_url'],
        ];
    }

    /** This month: customers are real; sales/collected/dues when billing merges. */
    public function thisMonth(): array
    {
        $out = ['newCustomers' => $this->newCustomersThisMonth(), 'billing' => false];
        if (! $this->hasInvoices()) {
            return $out;
        }

        return $this->safe(function () use ($out) {
            $month = $this->invoiceQuery()->where('status', '!=', 'cancelled')->where('created_at', '>=', $this->today->startOfMonth());

            return array_merge($out, [
                'billing' => true,
                'salesPaise' => (int) (clone $month)->sum('total_paise'),
                'duePaise' => (int) (clone $month)->sum('due_paise'),
                'bills' => (clone $month)->count(),
            ]);
        }, $out);
    }

    // ---------------------------------------------------------------- finance

    public function collectionsToday(): ?array
    {
        if (! $this->hasPayments()) {
            return null;
        }

        // WIRE: payments(mode, amount_paise, created_at)
        return $this->safe(fn () => (self::PAYMENT)::query()->where('created_at', '>=', $this->today)
            ->selectRaw('mode, SUM(amount_paise) as total')->groupBy('mode')->pluck('total', 'mode')
            ->map(fn ($v) => (int) $v)->all(), []);
    }

    /** Dues by age: 0–15, 15–30, 30+ days. */
    public function duesAgeing(): ?array
    {
        if (! $this->hasInvoices()) {
            return null;
        }

        return $this->safe(function () {
            $base = fn () => $this->invoiceQuery()->where('due_paise', '>', 0)->where('status', '!=', 'cancelled');

            return [
                '0–15 days' => (int) $base()->where('created_at', '>=', $this->today->subDays(15))->sum('due_paise'),
                '15–30 days' => (int) $base()->whereBetween('created_at', [$this->today->subDays(30), $this->today->subDays(15)])->sum('due_paise'),
                '30+ days' => (int) $base()->where('created_at', '<', $this->today->subDays(30))->sum('due_paise'),
            ];
        }, []);
    }

    public function totalDuePaise(): int
    {
        if (! $this->hasInvoices()) {
            return 0;
        }

        return $this->safe(fn () => (int) $this->invoiceQuery()->where('status', '!=', 'cancelled')->sum('due_paise'), 0);
    }

    // ---------------------------------------------------------------- technician

    /**
     * Technician home: the job cards module's own "My jobs" cards (Start, Add photo, Ready for QC),
     * fed by Workshop::forTechnician. `jobs` is the same list as plain rows.
     */
    private function technicianHome(): array
    {
        $cards = $this->hasJobCards() ? Workshop::forTechnician($this->user) : null;

        return ['cards' => $cards, 'jobs' => $cards === null ? null : $this->jobRows($cards)];
    }

    /** My open job cards (Workshop::forTechnician): working first, then by promised time. No prices. */
    public function myJobCards(): ?array
    {
        return $this->hasJobCards() ? $this->jobRows(Workshop::forTechnician($this->user)) : null;
    }

    private function jobRows(\Illuminate\Support\Collection $cards): array
    {
        return $cards->map(fn (JobCard $j) => [
            'number' => $j->number,
            'car' => $j->carLabel(true),
            'plate' => $j->plate(),
            'customer' => $j->customer?->name,
            'status' => $j->status,
            'status_label' => $j->label(),
            'late' => $j->isLate(),
            'promised' => $j->promiseText(),
            'bay' => $j->bay,
            'items' => $j->items->sortBy('position')->map(fn ($i) => ['name' => $i->description, 'qty' => (int) $i->qty, 'done' => (bool) $i->done])->values()->all(),
            'done' => $j->items->where('done', true)->count(),
            'photo' => ($p = $j->photos->firstWhere('stage', 'before') ?? $j->photos->first()) ? $p->url() : null,
            'photos' => $j->photos->count(),
            'url' => $this->routeOrNull('jobcards.show', $j),
        ])->all();
    }

    // ---------------------------------------------------------------- finance & offers

    /** FinanceHome::today() for people who may see finance (Owner, Admin view, Finance). */
    public function financeToday(): ?array
    {
        if (! $this->user->can('finance.view') || ! Schema::hasTable('expenses')) {
            return null;
        }

        return FinanceHome::today($this->user->branch_id);
    }

    /** Latest offers with their progress, for people who send them. */
    public function recentOffers(): ?array
    {
        if (! $this->user->can('offers.manage') || ! Route::has('offers.index')) {
            return null;
        }

        return Offers::recent(3);
    }

    // ---------------------------------------------------------------- plain-words comparison

    /** "₹4,200 more than last Thursday" / "Same as last Thursday" (DESIGN §5 Money display). */
    public function compareWords(int $now, int $then, bool $money = true): array
    {
        $day = $this->localToday()->subWeek()->format('l');
        $diff = $now - $then;
        if ($diff === 0) {
            return ["Same as last $day", null];
        }
        $amount = $money ? Money::format(abs($diff)) : (string) abs($diff);

        return [$amount.($diff > 0 ? ' more' : ' less')." than last $day", $diff > 0 ? 'up' : 'down'];
    }

    // ---------------------------------------------------------------- guards

    /** Today at midnight on the shop's clock (for display and date columns). */
    public function localToday(): CarbonImmutable
    {
        return $this->today->setTimezone(self::TZ);
    }

    public function hasInvoices(): bool
    {
        return class_exists(self::INVOICE) && Schema::hasTable('invoices');
    }

    public function hasPayments(): bool
    {
        return class_exists(self::PAYMENT) && Schema::hasTable('payments');
    }

    public function hasQuotations(): bool
    {
        return class_exists(self::QUOTATION) && Schema::hasTable('quotations');
    }

    public function hasFollowUps(): bool
    {
        return class_exists(self::FOLLOW_UP) && Schema::hasTable('follow_ups');
    }

    public function hasJobCards(): bool
    {
        return class_exists(self::JOB_CARD) && Schema::hasTable('job_cards');
    }

    private function invoiceQuery()
    {
        return (self::INVOICE)::query();
    }

    private function invoicesToday()
    {
        return $this->invoiceQuery()->where('status', '!=', 'cancelled')->where('status', '!=', 'draft')
            ->where('created_at', '>=', $this->today);
    }

    private function routeOrNull(string $name, mixed $params = []): ?string
    {
        return Route::has($name) ? route($name, $params) : null;
    }

    /**
     * Runs a guarded query. If a merged module named a column differently, Home still renders
     * (with the fallback) and the error is reported to the log instead of a 500 at the counter.
     */
    private function safe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (QueryException $e) {
            report($e);

            return $fallback;
        }
    }
}
