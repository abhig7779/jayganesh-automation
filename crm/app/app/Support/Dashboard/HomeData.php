<?php

namespace App\Support\Dashboard;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use App\Support\RegNo;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the role-aware Home shows (UX-FLOWS §2, DESIGN §7), in one place.
 *
 * Modules merge at different times, so every query on a table this branch doesn't own is
 * guarded by has*() (class_exists + Schema::hasTable). A section whose module is missing
 * returns `null`, and the view shows a calm "switches on with …" state instead of breaking.
 *
 * LEAD, after merging billing / quotations / job cards: search this file for "WIRE:" — each
 * marks a guarded query written against the column names in CONTRACTS.md / CRM-PLAN.md. Check
 * the column names against the merged migrations and remove the try/catch fallbacks you no
 * longer need.
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
                'showMargins' => $this->user->can('reports.margins'),
            ],
            'finance' => [
                'collections' => $this->collectionsToday(),
                'duesAgeing' => $this->duesAgeing(),
                'recentBills' => $this->recentBills(),
                'newCustomers' => $this->newCustomersToday(),
            ],
            'technician' => [
                'jobs' => $this->myJobCards(),
            ],
            default => [
                'myToday' => $this->myToday(),
                'followUps' => $this->followUpsToday(),
                'waiting' => $this->waitingOnMe(),
                'drafts' => $this->draftBills(),
                'lowStock' => $this->lowStockItems(),
                'workshop' => $this->user->can('jobcards.view') ? $this->workshop() : null,
                'recentBills' => $this->recentBills(onlyMine: true),
            ],
        };
    }

    // ---------------------------------------------------------------- action tiles

    /**
     * Tiles in the approved order. `url` is null while the module's route doesn't exist yet
     * (the tile shows "Soon"); tiles for permissions the user lacks are left out entirely.
     *
     * @return array<int, array{key:string,label:string,icon:string,url:?string,hint:?string,hintTone:?string,primary:bool,shortcut:?string}>
     */
    public function tiles(): array
    {
        $defs = match ($this->role()) {
            'finance' => [
                ['collect', 'Collect payment', 'cash', 'payments.collect', 'payments.collect', 'Customer pays an old due', 'F8'],
                ['expense', 'Add expense', 'rupee', 'expenses.create', 'finance.manage', 'Shop spends', null],
                ['cashclose', 'Cash close', 'lock', 'cashclose.create', 'finance.manage', 'Count the drawer', null],
                ['supplier', 'Supplier payment', 'clipboard', 'supplier-payments.create', 'purchases.manage', 'Pay a supplier', null],
                ['report', "Today's report", 'chart', 'reports.index', 'reports.view', 'Day closing & cash', null],
            ],
            'technician' => [],
            default => array_values(array_filter([
                ['new-bill', 'New bill', 'receipt', 'bills.create', 'invoices.create', 'Customer, items, payment', 'F2'],
                ['quote', 'New quotation', 'doc', 'quotations.create', 'quotations.manage', 'Estimate on WhatsApp', 'F6'],
                ['customer', 'Add customer', 'user-plus', 'customers.create', 'customers.manage', 'Mobile number first', 'F7'],
                ['collect', 'Collect payment', 'cash', 'payments.collect', 'payments.collect', null, 'F8'],
                ['car-in', 'Car in', 'car', 'jobcards.create', 'jobcards.view', 'New bay intake', 'F9'],
                ['stock', 'Check price', 'box', 'stock.check', 'inventory.view', 'Stock, price & MRP', 'F4'],
                ['report', "Today's report", 'chart', 'reports.index', 'reports.view', 'Day closing & cash', null],
                // Marketing tools show only for people given them (role Marketing, or Staff → Extra access).
                ['offer', 'Send offer', 'megaphone', 'offers.create', 'offers.manage', 'WhatsApp customers', null],
            ])),
        };

        $tiles = [];
        foreach ($defs as [$key, $label, $icon, $route, $permission, $hint, $fkey]) {
            if (! $this->user->can($permission)) {
                continue;
            }
            $tile = [
                'key' => $key, 'label' => $label, 'icon' => $icon, 'hint' => $hint, 'hintTone' => null,
                'url' => Route::has($route) ? route($route) : null,
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

    /** Drafts to finish, quotes not converted in 7 days, leads assigned to me. */
    public function waitingOnMe(): ?array
    {
        if (! $this->hasInvoices() && ! $this->hasQuotations()) {
            return null;
        }

        return $this->safe(function () {
            $rows = [];
            if ($this->hasInvoices()) {
                // WIRE: invoices.status = 'draft'
                $drafts = $this->invoiceQuery()->where('status', 'draft')->where('created_by', $this->user->id)->count();
                if ($drafts) {
                    $rows[] = ['icon' => 'receipt', 'tone' => 'warn', 'title' => $drafts.' draft '.str('bill')->plural($drafts).' to finish', 'url' => $this->routeOrNull('bills.index', ['status' => 'draft'])];
                }
            }
            if ($this->hasQuotations()) {
                // WIRE: quotations(created_by, status, created_at)
                $stale = (self::QUOTATION)::query()->where('created_by', $this->user->id)->where('status', 'open')
                    ->where('created_at', '<', $this->today->subDays(7))->count();
                if ($stale) {
                    $rows[] = ['icon' => 'doc', 'tone' => null, 'title' => $stale.' '.str('quotation')->plural($stale).' open for over a week', 'url' => $this->routeOrNull('quotations.index', ['filter' => 'mine'])];
                }
            }

            return $rows;
        }, []);
    }

    // ---------------------------------------------------------------- follow-ups

    /**
     * "Follow-ups today": null while the quotations module isn't merged. Overdue rows first.
     *
     * @return array{count:int, overdue:int, rows:array}|null
     */
    public function followUpsToday(): ?array
    {
        if (! $this->hasFollowUps() || ! $this->user->can('quotations.view')) {
            return null;
        }

        // WIRE: follow_ups(assigned_to, due_on, status, customer_id, quotation_id) + relations customer, quotation.
        return $this->safe(function () {
            $query = (self::FOLLOW_UP)::query()
                ->where('status', 'open')
                ->whereDate('due_on', '<=', $this->localToday()->toDateString())
                ->when(! $this->user->can('reports.all_staff'), fn ($q) => $q->where('assigned_to', $this->user->id));
            $rows = (clone $query)->with(['customer'])->orderBy('due_on')->limit(6)->get()->map(function ($f) {
                $overdue = $f->due_on && CarbonImmutable::parse($f->due_on)->toDateString() < $this->localToday()->toDateString();
                $mobile = $f->customer?->mobile;

                return [
                    'name' => $f->customer?->name ?? 'Customer',
                    'detail' => $f->summary ?? ($f->quotation?->number ?? 'Follow-up'),
                    'overdue' => $overdue,
                    'when' => $overdue ? (int) CarbonImmutable::parse($f->due_on)->diffInDays($this->localToday()).'d overdue' : 'Today',
                    'call' => $mobile ? 'tel:'.$mobile : null,
                    'whatsapp' => $mobile ? 'https://wa.me/'.ltrim($mobile, '+') : null,
                    'url' => $this->routeOrNull('followups.index'),
                ];
            })->sortByDesc('overdue')->values()->all();

            return [
                'count' => (clone $query)->count(),
                'overdue' => (clone $query)->whereDate('due_on', '<', $this->localToday()->toDateString())->count(),
                'rows' => $rows,
            ];
        }, ['count' => 0, 'overdue' => 0, 'rows' => []]);
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

        if ($fu = $this->followUpsToday()) {
            if ($fu['overdue'] > 0) {
                $rows[] = ['icon' => 'chat', 'tone' => 'bad', 'title' => $fu['overdue'].' overdue '.str('follow-up')->plural($fu['overdue']), 'detail' => 'Quotes going cold', 'url' => $this->routeOrNull('followups.index')];
            }
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

        if ($low = $this->lowStockCount()) {
            $rows[] = ['icon' => 'box', 'tone' => 'warn', 'title' => $low.' '.str('item')->plural($low).' low on stock', 'detail' => 'Reorder before the weekend', 'url' => $this->routeOrNull('products.index', ['filter' => 'low'])];
        }

        return $rows;
    }

    /** Which modules are still to come, so the owner's "Needs attention" can say what it will watch. */
    public function pendingWatches(): array
    {
        return array_values(array_filter([
            $this->hasFollowUps() ? null : 'overdue follow-ups',
            $this->hasInvoices() ? null : 'old dues, cancelled bills and failed WhatsApp bills',
            'cash close',
        ]));
    }

    public function lowStockCount(): int
    {
        if (! class_exists(Product::class) || ! Schema::hasTable('products')) {
            return 0;
        }

        return $this->safe(fn () => Product::query()->where('is_active', true)->where('track_stock', true)
            ->where('reorder_level', '>', 0)->whereColumn('on_hand', '<=', 'reorder_level')->count(), 0);
    }

    /** Up to 5 low-stock products, lowest first (real: products is on main). */
    public function lowStockItems(int $limit = 5): array
    {
        if (! class_exists(Product::class) || ! Schema::hasTable('products') || ! $this->user->can('inventory.view')) {
            return [];
        }

        return $this->safe(fn () => Product::query()->where('is_active', true)->where('track_stock', true)
            ->where('reorder_level', '>', 0)->whereColumn('on_hand', '<=', 'reorder_level')
            ->with('brand')->orderBy('on_hand')->limit($limit)->get()
            ->map(fn (Product $p) => [
                'name' => $p->name,
                'detail' => $p->brand?->name ?? ($p->sku ?: 'Product'),
                'left' => (int) $p->on_hand,
                'url' => $this->routeOrNull('products.show', $p),
            ])->all(), []);
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

    /** Red count on the Follow-ups menu item (null = module not merged). */
    public static function followUpBadge(User $user): ?int
    {
        $count = (new self($user))->followUpsToday()['count'] ?? null;

        return $count ?: null;
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

    /** Cars in the workshop by status; null until job cards merge. */
    public function workshop(): ?array
    {
        if (! $this->hasJobCards()) {
            return null;
        }

        // WIRE: job_cards.status in waiting|working|qc|ready
        return $this->safe(function () {
            $counts = (self::JOB_CARD)::query()->whereIn('status', ['waiting', 'working', 'qc', 'ready'])
                ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

            return collect(['waiting' => 'Waiting', 'working' => 'Working', 'qc' => 'QC', 'ready' => 'Ready'])
                ->map(fn ($label, $key) => ['label' => $label, 'count' => (int) ($counts[$key] ?? 0)])->all();
        }, []);
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

    /** My job cards; null until the job cards module merges. */
    public function myJobCards(): ?array
    {
        if (! $this->hasJobCards()) {
            return null;
        }

        // WIRE: job_cards(technician_id, status, promised_at) + relations car, items
        return $this->safe(fn () => (self::JOB_CARD)::query()->where('technician_id', $this->user->id)
            ->whereIn('status', ['waiting', 'working', 'qc'])->with('car')->orderBy('promised_at')->get()
            ->map(fn ($j) => [
                'car' => $j->car?->label() ?? 'Car',
                'plate' => $j->car?->reg_no ? RegNo::pretty($j->car->reg_no) : null,
                'status' => $j->status,
                'promised' => $j->promised_at ? CarbonImmutable::parse($j->promised_at)->format('h:i A') : null,
                'url' => $this->routeOrNull('jobcards.show', $j),
            ])->all(), []);
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
