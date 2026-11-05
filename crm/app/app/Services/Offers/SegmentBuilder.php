<?php

namespace App\Services\Offers;

use App\Models\Customer;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the owner's simple filters ("Creta owners in Hadapsar who haven't visited in 90 days")
 * into a customer query. Opted-out customers are ALWAYS excluded from the audience
 * (CRM-PLAN.md §6 privacy); archived customers too.
 *
 * Filters (all optional, all combined with AND; values inside one list are OR):
 *   makes[], models[], areas[], sources[], tags[]
 *   visit: any | before | after | not_in_days | never, visit_date (Y-m-d), visit_days (int)
 *   min_spent (rupees)
 *   bought_products[], bought_categories[]   (only when the billing tables exist)
 *   added_from, added_to (Y-m-d)
 */
class SegmentBuilder
{
    public const LIST_KEYS = ['makes', 'models', 'areas', 'sources', 'tags', 'bought_products', 'bought_categories'];

    public const VISIT_MODES = [
        'any' => 'Any time',
        'not_in_days' => 'Not visited in the last … days',
        'before' => 'Last visit before a date',
        'after' => 'Visited on or after a date',
        'never' => 'Never visited (no visit recorded)',
    ];

    private const TZ = 'Asia/Kolkata';

    /** Clean raw request input into the canonical filter array that we store and snapshot. */
    public function normalize(array $input): array
    {
        $f = [];
        foreach (self::LIST_KEYS as $key) {
            $values = array_values(array_unique(array_filter(
                array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', (array) ($input[$key] ?? [])),
                fn ($v) => $v !== '',
            )));
            if (in_array($key, ['bought_products', 'bought_categories'], true)) {
                $values = array_values(array_filter(array_map('intval', $values)));
            }
            if ($values) {
                $f[$key] = $values;
            }
        }

        $visit = (string) ($input['visit'] ?? 'any');
        if (! array_key_exists($visit, self::VISIT_MODES)) {
            $visit = 'any';
        }
        if ($visit === 'not_in_days' && (int) ($input['visit_days'] ?? 0) > 0) {
            $f['visit'] = $visit;
            $f['visit_days'] = min(3650, (int) $input['visit_days']);
        } elseif (in_array($visit, ['before', 'after'], true) && $this->date($input['visit_date'] ?? null)) {
            $f['visit'] = $visit;
            $f['visit_date'] = $this->date($input['visit_date'])->toDateString();
        } elseif ($visit === 'never') {
            $f['visit'] = 'never';
        }

        $min = preg_replace('/[^\d]/', '', (string) ($input['min_spent'] ?? ''));
        if ($min !== '' && (int) $min > 0) {
            $f['min_spent'] = (int) $min;
        }

        foreach (['added_from', 'added_to'] as $key) {
            if ($d = $this->date($input[$key] ?? null)) {
                $f[$key] = $d->toDateString();
            }
        }

        return $f;
    }

    /** Customers matching the filters, opt-outs excluded. */
    public function query(array $filters): Builder
    {
        return $this->matching($filters)
            ->where('whatsapp_opt_in', true)
            ->whereNull('opted_out_at');
    }

    /** Matching customers who have opted out (shown so the owner knows they were excluded). */
    public function optedOutQuery(array $filters): Builder
    {
        return $this->matching($filters)->where(function (Builder $w) {
            $w->where('whatsapp_opt_in', false)->orWhereNotNull('opted_out_at');
        });
    }

    /** @return array{eligible:int, opted_out:int} */
    public function count(array $filters): array
    {
        return [
            'eligible' => $this->query($filters)->count(),
            'opted_out' => $this->optedOutQuery($filters)->count(),
        ];
    }

    /** "142 customers · 9 opted out, excluded" */
    public static function countLine(array $count): string
    {
        $n = $count['eligible'];
        $line = number_format($n).' '.($n === 1 ? 'customer' : 'customers');

        return $line.' · '.number_format($count['opted_out']).' opted out, excluded';
    }

    /** Whether "bought product / category" can be used (billing module merged). */
    public static function supportsPurchases(): bool
    {
        return Schema::hasTable('invoice_items') && Schema::hasTable('invoices');
    }

    /** Plain-words summary chips for a filter set ("Creta, Seltos", "Not visited in 90 days"). */
    public function describe(array $filters): array
    {
        $out = [];
        if (! empty($filters['makes'])) {
            $out[] = 'Make: '.implode(', ', $filters['makes']);
        }
        if (! empty($filters['models'])) {
            $out[] = 'Car: '.implode(', ', $filters['models']);
        }
        if (! empty($filters['areas'])) {
            $out[] = 'Area: '.implode(', ', $filters['areas']);
        }
        if (! empty($filters['sources'])) {
            $out[] = 'Came from: '.implode(', ', array_map(fn ($s) => Customer::SOURCE_LABELS[$s] ?? $s, $filters['sources']));
        }
        if (! empty($filters['tags'])) {
            $out[] = 'Tag: '.implode(', ', $filters['tags']);
        }
        $visit = $filters['visit'] ?? 'any';
        if ($visit === 'not_in_days') {
            $out[] = 'Not visited in '.$filters['visit_days'].' days';
        } elseif ($visit === 'before') {
            $out[] = 'Last visit before '.Carbon::parse($filters['visit_date'])->format('d M Y');
        } elseif ($visit === 'after') {
            $out[] = 'Visited since '.Carbon::parse($filters['visit_date'])->format('d M Y');
        } elseif ($visit === 'never') {
            $out[] = 'No visit recorded';
        }
        if (! empty($filters['min_spent'])) {
            $out[] = 'Spent '.Money::format($filters['min_spent'] * 100).' or more';
        }
        if (! empty($filters['bought_products']) && Schema::hasTable('products')) {
            $out[] = 'Bought: '.DB::table('products')->whereIn('id', $filters['bought_products'])->pluck('name')->implode(', ');
        }
        if (! empty($filters['bought_categories']) && Schema::hasTable('categories')) {
            $out[] = 'Bought from: '.DB::table('categories')->whereIn('id', $filters['bought_categories'])->pluck('name')->implode(', ');
        }
        if (! empty($filters['added_from']) || ! empty($filters['added_to'])) {
            $from = ! empty($filters['added_from']) ? Carbon::parse($filters['added_from'])->format('d M Y') : 'the start';
            $to = ! empty($filters['added_to']) ? Carbon::parse($filters['added_to'])->format('d M Y') : 'today';
            $out[] = "Added between $from and $to";
        }

        return $out ?: ['All customers'];
    }

    /** Choices for the filter form, built from the real data so nothing is typed by hand. */
    public function options(): array
    {
        $cars = DB::table('customer_cars')
            ->join('customers', 'customers.id', '=', 'customer_cars.customer_id')
            ->whereNull('customers.archived_at')
            ->selectRaw('customer_cars.make, customer_cars.model, count(distinct customer_cars.customer_id) as n')
            ->groupBy('customer_cars.make', 'customer_cars.model')
            ->orderBy('customer_cars.make')->orderBy('customer_cars.model')
            ->get();

        $tags = Customer::query()->whereNull('archived_at')->whereNotNull('tags')->pluck('tags')
            ->flatten()->filter()->map(fn ($t) => (string) $t)->countBy()->sortKeys();

        $options = [
            'makes' => $cars->groupBy('make')->map(fn ($rows) => (int) $rows->sum('n'))->all(),
            'models' => $cars->groupBy('make')->map(fn ($rows) => $rows->pluck('n', 'model')->map(fn ($n) => (int) $n)->all())->all(),
            'areas' => Customer::query()->whereNull('archived_at')->whereNotNull('area')->where('area', '!=', '')
                ->selectRaw('area, count(*) as n')->groupBy('area')->orderBy('area')->pluck('n', 'area')->map(fn ($n) => (int) $n)->all(),
            'sources' => Customer::SOURCE_LABELS,
            'tags' => $tags->all(),
            'products' => [],
            'categories' => [],
        ];

        if (self::supportsPurchases() && Schema::hasTable('products')) {
            $options['products'] = DB::table('products')->where('is_active', true)->orderBy('name')->limit(500)->pluck('name', 'id')->all();
            if (Schema::hasTable('categories')) {
                $options['categories'] = DB::table('categories')->orderBy('name')->pluck('name', 'id')->all();
            }
        }

        return $options;
    }

    /** All the filters except the opt-out rule. */
    private function matching(array $f): Builder
    {
        $q = Customer::query()->whereNull('archived_at');

        if (! empty($f['makes'])) {
            $q->whereHas('cars', fn (Builder $c) => $c->whereIn('make', $f['makes']));
        }
        if (! empty($f['models'])) {
            $q->whereHas('cars', fn (Builder $c) => $c->whereIn('model', $f['models']));
        }
        if (! empty($f['areas'])) {
            $q->whereIn('area', $f['areas']);
        }
        if (! empty($f['sources'])) {
            $q->whereIn('source', $f['sources']);
        }
        if (! empty($f['tags'])) {
            // Tags are a JSON array; a quoted LIKE works the same on MySQL and SQLite.
            $q->where(function (Builder $w) use ($f) {
                foreach ($f['tags'] as $tag) {
                    $w->orWhere('tags', 'like', '%'.json_encode((string) $tag).'%');
                }
            });
        }

        switch ($f['visit'] ?? 'any') {
            case 'not_in_days':
                $cutoff = now()->subDays((int) $f['visit_days']);
                $q->where(function (Builder $w) use ($cutoff) {
                    $w->where('last_visit_at', '<', $cutoff)
                        ->orWhere(fn (Builder $n) => $n->whereNull('last_visit_at')->where('created_at', '<', $cutoff));
                });
                break;
            case 'before':
                $q->where('last_visit_at', '<', $this->startOfDay($f['visit_date']));
                break;
            case 'after':
                $q->where('last_visit_at', '>=', $this->startOfDay($f['visit_date']));
                break;
            case 'never':
                $q->whereNull('last_visit_at');
                break;
        }

        if (! empty($f['min_spent'])) {
            $q->where('total_spent_paise', '>=', (int) $f['min_spent'] * 100);
        }

        if ((! empty($f['bought_products']) || ! empty($f['bought_categories'])) && self::supportsPurchases()) {
            $q->whereExists(function (QueryBuilder $sub) use ($f) {
                $sub->selectRaw('1')->from('invoice_items')
                    ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                    ->whereColumn('invoices.customer_id', 'customers.id');
                if (Schema::hasColumn('invoices', 'cancelled_at')) {
                    $sub->whereNull('invoices.cancelled_at');
                }
                if (Schema::hasColumn('invoices', 'status')) {
                    $sub->where('invoices.status', '!=', 'cancelled');
                }
                $sub->where(function (QueryBuilder $w) use ($f) {
                    if (! empty($f['bought_products'])) {
                        $w->orWhereIn('invoice_items.product_id', $f['bought_products']);
                    }
                    if (! empty($f['bought_categories'])) {
                        $w->orWhereIn('invoice_items.product_id', DB::table('products')->select('id')->whereIn('category_id', $f['bought_categories']));
                    }
                });
            });
        }

        if (! empty($f['added_from'])) {
            $q->where('created_at', '>=', $this->startOfDay($f['added_from']));
        }
        if (! empty($f['added_to'])) {
            $q->where('created_at', '<', $this->startOfDay($f['added_to'])->addDay());
        }

        return $q;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $value, self::TZ)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** A shop-local (IST) calendar day, converted to the app's storage timezone. */
    private function startOfDay(string $ymd): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $ymd, self::TZ)->startOfDay()->setTimezone(config('app.timezone'));
    }
}
