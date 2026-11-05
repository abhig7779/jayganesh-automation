<?php

namespace App\Support\Activity;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\StockMove;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Turns spatie activity_log rows into plain words for the Activity page: "Updated product
 * Gleam 2.0", with each change as "Price: ₹4,999 → ₹4,499". Money columns (*_paise) show as ₹,
 * ids show as names where we know the table, and nothing is ever dumped as raw JSON.
 */
class ActivityPresenter
{
    /** log_name → what the row is about. Unknown names are humanised. */
    public const TYPES = [
        'products' => 'Product', 'stock' => 'Stock', 'staff' => 'Staff', 'customers' => 'Customer',
        'quotations' => 'Quotation', 'leads' => 'Lead', 'jobcards' => 'Job card', 'expenses' => 'Expense',
        'cash_close' => 'Cash close', 'offers' => 'Offer', 'settings' => 'Setting', 'branches' => 'Branch',
        'purchases' => 'Purchase', 'suppliers' => 'Supplier', 'supplier_payments' => 'Supplier payment',
        'invoices' => 'Bill', 'bills' => 'Bill', 'payments' => 'Payment', 'default' => 'Other',
    ];

    private const FIELD_LABELS = [
        'price_paise' => 'Our price', 'mrp_paise' => 'Normal price (MRP)', 'cost_paise' => 'Cost price',
        'gst_rate' => 'GST %', 'is_active' => 'Active', 'on_hand_after' => 'Stock after', 'qty' => 'Qty',
        'total_paise' => 'Total', 'gst_paise' => 'GST', 'amount_paise' => 'Amount', 'bill_no' => 'Supplier bill no.',
        'opening_balance_paise' => 'Opening balance', 'gstin' => 'GSTIN', 'sku' => 'Code', 'reg_no' => 'Car number',
        'mobile' => 'Mobile', 'phone' => 'Mobile', 'cancelled_at' => 'Cancelled on', 'type' => 'Type',
    ];

    /** *_id columns we can turn into a name: column → model class, label column. */
    private const LOOKUPS = [
        'supplier_id' => [Supplier::class, 'name'],
        'brand_id' => [Brand::class, 'name'],
        'category_id' => [Category::class, 'name'],
        'expense_category_id' => [ExpenseCategory::class, 'name'],
        'customer_id' => [Customer::class, 'name'],
        'branch_id' => [Branch::class, 'name'],
        'assigned_to' => [User::class, 'name'],
        'technician_id' => [User::class, 'name'],
    ];

    private const HIDDEN = ['move_id', 'id', 'created_at', 'updated_at', 'search_text', 'remember_token'];

    private array $cache = [];

    public static function typeLabel(?string $logName): string
    {
        return self::TYPES[$logName ?? 'default'] ?? Str::headline((string) $logName);
    }

    /** One line: "Updated product · Gleam 2.0 LED headlight". */
    public function what(Activity $a): string
    {
        $type = mb_strtolower(self::typeLabel($a->log_name));
        $action = match ($a->event ?? $a->description) {
            'created' => 'Added '.$type,
            'updated' => 'Changed '.$type,
            'deleted' => 'Deleted '.$type,
            default => Str::ucfirst((string) $a->description),
        };
        $subject = $this->subjectLabel($a);

        return $subject !== null ? "{$action} · {$subject}" : $action;
    }

    public function subjectLabel(Activity $a): ?string
    {
        $s = $a->subject;
        if (! $s instanceof Model) {
            return $a->subject_id ? Str::headline(class_basename((string) $a->subject_type)).' #'.$a->subject_id : null;
        }
        if ($s->getTable() === 'supplier_payments') {
            return Money::format((int) $s->getAttribute('amount_paise')).' to '.($this->lookup('supplier_id', (int) $s->getAttribute('supplier_id')));
        }
        if (method_exists($s, 'ref') && $s->getTable() === 'purchases') {
            return $s->ref().($s->bill_no ? ' (bill '.$s->bill_no.')' : '');
        }
        foreach (['name', 'number', 'title', 'key', 'reg_no', 'bill_no'] as $col) {
            $v = $s->getAttribute($col);
            if (is_scalar($v) && $v !== '') {
                return Str::limit((string) $v, 60);
            }
        }

        return Str::headline(class_basename($s)).' #'.$s->getKey();
    }

    /**
     * The changes as rows: [label, before|null, after]. "before" is null for new records and for
     * one-off events such as a stock move.
     *
     * @return list<array{label:string, before:?string, after:string}>
     */
    public function changes(Activity $a): array
    {
        $props = $a->properties?->toArray() ?? [];
        $new = is_array($props['attributes'] ?? null) ? $props['attributes'] : null;
        $old = is_array($props['old'] ?? null) ? $props['old'] : [];
        $rows = [];

        if ($new !== null) {
            foreach ($new as $key => $value) {
                if ($this->hidden($key)) {
                    continue;
                }
                $before = array_key_exists($key, $old) ? $this->value($key, $old[$key]) : null;
                $after = $this->value($key, $value);
                if ($before !== null && $before === $after) {
                    continue;
                }
                if ($before === null && $after === '—') {
                    continue; // empty field on a new record
                }
                $rows[] = ['label' => $this->label($key), 'before' => $before, 'after' => $after];
            }

            return $rows;
        }

        foreach ($props as $key => $value) {
            if ($this->hidden($key) || $value === null || $value === '' || is_array($value) && $value === []) {
                continue;
            }
            $rows[] = ['label' => $this->label($key), 'before' => null, 'after' => $this->value($key, $value)];
        }

        return $rows;
    }

    public function label(string $key): string
    {
        if (isset(self::FIELD_LABELS[$key])) {
            return self::FIELD_LABELS[$key];
        }
        $key = preg_replace('/(_paise|_id)$/', '', $key);

        return Str::ucfirst(str_replace('_', ' ', $key));
    }

    public function value(string $key, mixed $v): string
    {
        if (preg_match('/password|token|secret/i', $key)) {
            return '(hidden)';
        }
        if ($v === null || $v === '') {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? 'Yes' : 'No';
        }
        if (str_ends_with($key, '_paise') && is_numeric($v)) {
            return Money::format((int) $v);
        }
        if (in_array($key, ['is_active', 'track_stock', 'price_includes_gst', 'opted_out'], true)) {
            return $v ? 'Yes' : 'No';
        }
        if ($key === 'purchase_id' && is_numeric($v)) {
            return 'PB-'.str_pad((string) $v, 4, '0', STR_PAD_LEFT);
        }
        if (isset(self::LOOKUPS[$key]) && is_numeric($v)) {
            return $this->lookup($key, (int) $v);
        }
        if (is_array($v)) {
            $flat = array_map(fn ($x) => is_scalar($x) ? (string) $x : (is_array($x) ? implode(' ', array_filter($x, 'is_scalar')) : ''), $v);

            return Str::limit(implode(', ', array_filter($flat, fn ($x) => $x !== '')), 120) ?: '—';
        }
        if (is_string($v) && (str_ends_with($key, '_at') || str_ends_with($key, 'date')) && strtotime($v) !== false) {
            $d = Carbon::parse($v);

            return str_ends_with($key, '_at')
                ? $d->setTimezone('Asia/Kolkata')->format('j M Y, g:i a')
                : $d->format('j M Y');
        }
        if ($key === 'gst_rate' && is_numeric($v)) {
            return rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%';
        }
        if ($key === 'qty' && is_numeric($v)) {
            return ($v > 0 ? '+' : '').$v;
        }
        if ($key === 'type' && isset(StockMove::LABELS[$v])) {
            return StockMove::LABELS[$v];
        }
        if ($key === 'type' || $key === 'status' || $key === 'mode') {
            return Str::ucfirst(str_replace('_', ' ', (string) $v));
        }

        return Str::limit((string) $v, 120);
    }

    private function hidden(string $key): bool
    {
        return in_array($key, self::HIDDEN, true);
    }

    private function lookup(string $key, int $id): string
    {
        [$class, $col] = self::LOOKUPS[$key];
        if (! class_exists($class)) {
            return '#'.$id;
        }
        $this->cache[$class][$id] ??= $class::query()->whereKey($id)->value($col);
        $label = $this->cache[$class][$id];

        return $label !== null && $label !== '' ? (string) $label : '#'.$id;
    }
}
