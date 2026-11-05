<?php

namespace App\Services\Finance;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every read of the billing module's tables lives here (invoices, invoice_items, payments,
 * credit_note_items; see database/migrations/2026_11_07_200000_create_billing_tables.php).
 *
 * Rules used throughout:
 * - A bill counts on the day it was made (invoices.created_at, IST). Cancelled bills are left out
 *   of sales and counted separately. Sales are net of part returns (invoices.credited_paise).
 * - Money in = payments.created_at, net: receipts are positive, refunds (kind = refund) negative,
 *   reversed payments (reversed_at set) are ignored. A cancelled bill nets to zero because
 *   cancelling records a refund for what was paid.
 * - "Dues collected" = receipts on bills made before the period, plus receipts not tied to a bill.
 */
final class SalesQueries
{
    public const MODES = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank' => 'Bank'];

    public const AGEING = ['0_15' => '0–15 days', '16_30' => '16–30 days', '31_60' => '31–60 days', '60_plus' => '60+ days'];

    public static function installed(): bool
    {
        return Schema::hasTable('invoices');
    }

    public static function paymentsInstalled(): bool
    {
        return Schema::hasTable('payments') && Schema::hasTable('invoices');
    }

    public static function itemsInstalled(): bool
    {
        return Schema::hasTable('invoice_items') && Schema::hasTable('invoices');
    }

    // ---------------------------------------------------------------- base queries

    private static function invoices(?int $branchId): Builder
    {
        return DB::table('invoices')->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId));
    }

    private static function liveInvoices(Period $p, ?int $branchId): Builder
    {
        return self::invoices($branchId)
            ->where('invoices.status', '!=', 'cancelled')
            ->where('invoices.created_at', '>=', $p->utcStart())
            ->where('invoices.created_at', '<', $p->utcEnd());
    }

    /** Payments in the period, joined to their bill (alias inv). Net of refunds; reversed payments left out. */
    private static function payments(Period $p, ?int $branchId): Builder
    {
        return DB::table('payments')
            ->leftJoin('invoices as inv', 'inv.id', '=', 'payments.invoice_id')
            ->whereNull('payments.reversed_at')
            ->when($branchId, fn ($q) => $q->where('payments.branch_id', $branchId))
            ->where('payments.created_at', '>=', $p->utcStart())
            ->where('payments.created_at', '<', $p->utcEnd());
    }

    private static function items(Period $p, ?int $branchId): Builder
    {
        return DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', '!=', 'cancelled')
            ->where('invoices.created_at', '>=', $p->utcStart())
            ->where('invoices.created_at', '<', $p->utcEnd())
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId));
    }

    /** Part returns (credit note lines) against live bills made in the period, so GST nets out like sales do. */
    private static function creditLines(Period $p, ?int $branchId): Builder
    {
        return DB::table('credit_note_items')
            ->join('invoice_items', 'invoice_items.id', '=', 'credit_note_items.invoice_item_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', '!=', 'cancelled')
            ->where('invoices.created_at', '>=', $p->utcStart())
            ->where('invoices.created_at', '<', $p->utcEnd())
            ->when($branchId, fn ($q) => $q->where('invoices.branch_id', $branchId));
    }

    private static function istDay(mixed $utc): string
    {
        return CarbonImmutable::parse($utc, 'UTC')->setTimezone(Period::TZ)->toDateString();
    }

    private static function emptyModes(): array
    {
        return array_fill_keys(array_keys(self::MODES), 0);
    }

    // ---------------------------------------------------------------- totals

    /**
     * Headline numbers for a period.
     *
     * @return array{bills:int, sales_paise:int, discount_paise:int, avg_bill_paise:int, cancelled_count:int,
     *               cancelled_paise:int, modes:array<string,int>, collected_paise:int, dues_collected_paise:int}
     */
    public static function summary(Period $p, ?int $branchId = null): array
    {
        $out = [
            'bills' => 0, 'sales_paise' => 0, 'discount_paise' => 0, 'avg_bill_paise' => 0,
            'cancelled_count' => 0, 'cancelled_paise' => 0,
            'modes' => self::emptyModes(), 'collected_paise' => 0, 'dues_collected_paise' => 0,
        ];
        if (! self::installed()) {
            return $out;
        }

        $live = self::liveInvoices($p, $branchId)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total_paise - credited_paise),0) as total, COALESCE(SUM(discount_paise),0) as disc')
            ->first();
        $cancelled = self::invoices($branchId)
            ->where('status', 'cancelled')
            ->where('created_at', '>=', $p->utcStart())->where('created_at', '<', $p->utcEnd())
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total_paise),0) as total')
            ->first();

        $out['bills'] = (int) $live->n;
        $out['sales_paise'] = (int) $live->total;
        $out['discount_paise'] = (int) $live->disc;
        $out['avg_bill_paise'] = $out['bills'] ? intdiv($out['sales_paise'] + intdiv($out['bills'], 2), $out['bills']) : 0;
        $out['cancelled_count'] = (int) $cancelled->n;
        $out['cancelled_paise'] = (int) $cancelled->total;
        $out['modes'] = self::collectionsByMode($p, $branchId);
        $out['collected_paise'] = array_sum($out['modes']);
        $out['dues_collected_paise'] = self::duesCollected($p, $branchId);

        return $out;
    }

    /** @return array<string,int> cash/upi/card/bank → paise received in the period */
    public static function collectionsByMode(Period $p, ?int $branchId = null): array
    {
        $out = self::emptyModes();
        if (! self::paymentsInstalled()) {
            return $out;
        }
        $rows = self::payments($p, $branchId)
            ->groupBy('payments.mode')
            ->selectRaw('payments.mode as mode, COALESCE(SUM(payments.amount_paise),0) as total')
            ->get();
        foreach ($rows as $r) {
            $out[$r->mode] = ($out[$r->mode] ?? 0) + (int) $r->total;
        }

        return $out;
    }

    public static function cashReceived(Period $p, ?int $branchId = null): int
    {
        return self::collectionsByMode($p, $branchId)['cash'];
    }

    /** Payments in the period for bills made before it (or not tied to a bill). */
    public static function duesCollected(Period $p, ?int $branchId = null): int
    {
        if (! self::paymentsInstalled()) {
            return 0;
        }

        return (int) self::payments($p, $branchId)
            ->where('payments.kind', 'receipt')
            ->where(fn ($q) => $q->whereNull('payments.invoice_id')->orWhere('inv.created_at', '<', $p->utcStart()))
            ->sum('payments.amount_paise');
    }

    /** Was there any bill or payment in the period? (for the missing cash close alert) */
    public static function hadActivity(Period $p, ?int $branchId = null): bool
    {
        if (! self::installed()) {
            return false;
        }
        if (self::invoices($branchId)->where('created_at', '>=', $p->utcStart())->where('created_at', '<', $p->utcEnd())->exists()) {
            return true;
        }

        return self::paymentsInstalled() && self::payments($p, $branchId)->exists();
    }

    // ---------------------------------------------------------------- reports

    /**
     * One row per day: bills, sales, collections by mode, average bill, cancelled.
     *
     * @return list<array<string,mixed>>
     */
    public static function dailySales(Period $p, ?int $branchId = null): array
    {
        $days = [];
        foreach ($p->days() as $d) {
            $days[$d] = ['date' => $d, 'bills' => 0, 'sales_paise' => 0, 'cash_paise' => 0, 'upi_paise' => 0,
                'card_paise' => 0, 'bank_paise' => 0, 'avg_bill_paise' => 0, 'cancelled_count' => 0, 'cancelled_paise' => 0];
        }
        if (! self::installed()) {
            return array_values($days);
        }

        $bills = self::invoices($branchId)
            ->where('created_at', '>=', $p->utcStart())->where('created_at', '<', $p->utcEnd())
            ->get(['created_at', 'status', 'total_paise', 'credited_paise']);
        foreach ($bills as $b) {
            $d = self::istDay($b->created_at);
            if (! isset($days[$d])) {
                continue;
            }
            if ($b->status === 'cancelled') {
                $days[$d]['cancelled_count']++;
                $days[$d]['cancelled_paise'] += (int) $b->total_paise;
            } else {
                $days[$d]['bills']++;
                $days[$d]['sales_paise'] += (int) $b->total_paise - (int) $b->credited_paise;
            }
        }

        if (self::paymentsInstalled()) {
            $pays = self::payments($p, $branchId)->get(['payments.created_at', 'payments.mode', 'payments.amount_paise']);
            foreach ($pays as $pay) {
                $d = self::istDay($pay->created_at);
                $key = $pay->mode.'_paise';
                if (isset($days[$d]) && array_key_exists($key, $days[$d])) {
                    $days[$d][$key] += (int) $pay->amount_paise;
                }
            }
        }

        foreach ($days as &$row) {
            $row['avg_bill_paise'] = $row['bills'] ? intdiv($row['sales_paise'] + intdiv($row['bills'], 2), $row['bills']) : 0;
        }

        return array_values($days);
    }

    /** @return list<array{user_id:?int, name:string, bills:int, sales_paise:int, avg_bill_paise:int, cancelled_count:int}> */
    public static function salesByStaff(Period $p, ?int $branchId = null): array
    {
        if (! self::installed()) {
            return [];
        }
        $rows = self::invoices($branchId)
            ->leftJoin('users', 'users.id', '=', 'invoices.created_by')
            ->where('invoices.created_at', '>=', $p->utcStart())->where('invoices.created_at', '<', $p->utcEnd())
            ->groupBy('invoices.created_by', 'users.name')
            ->selectRaw("invoices.created_by as user_id, users.name as name,
                SUM(CASE WHEN invoices.status != 'cancelled' THEN 1 ELSE 0 END) as bills,
                COALESCE(SUM(CASE WHEN invoices.status != 'cancelled' THEN invoices.total_paise - invoices.credited_paise ELSE 0 END),0) as sales,
                SUM(CASE WHEN invoices.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->get();

        return $rows->map(fn ($r) => [
            'user_id' => $r->user_id ? (int) $r->user_id : null,
            'name' => $r->name ?? 'Unknown',
            'bills' => (int) $r->bills,
            'sales_paise' => (int) $r->sales,
            'avg_bill_paise' => (int) $r->bills ? intdiv((int) $r->sales + intdiv((int) $r->bills, 2), (int) $r->bills) : 0,
            'cancelled_count' => (int) $r->cancelled,
        ])->sortByDesc('sales_paise')->values()->all();
    }

    /**
     * Products by sales value. cost_paise is units × products.cost_paise (null when the cost is unknown);
     * margin is taxable value − cost, so GST never inflates it.
     *
     * @return list<array<string,mixed>>
     */
    public static function topProducts(Period $p, ?int $branchId = null, int $limit = 50): array
    {
        if (! self::itemsInstalled()) {
            return [];
        }
        $rows = self::items($p, $branchId)
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->groupBy('invoice_items.product_id', 'invoice_items.name')
            ->selectRaw('invoice_items.product_id as product_id, invoice_items.name as name,
                COALESCE(SUM(invoice_items.qty),0) as qty,
                COALESCE(SUM(invoice_items.taxable_paise),0) as taxable,
                COALESCE(SUM(invoice_items.line_total_paise),0) as sales,
                SUM(CASE WHEN products.cost_paise IS NULL THEN NULL ELSE invoice_items.qty * products.cost_paise END) as cost,
                COALESCE(SUM(CASE WHEN products.cost_paise IS NULL THEN 0 ELSE invoice_items.taxable_paise END),0) as costed')
            ->orderByDesc('sales')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => self::withMargin([
            'product_id' => $r->product_id ? (int) $r->product_id : null,
            'name' => $r->name,
            'qty' => self::qty($r->qty),
            'taxable_paise' => (int) $r->taxable,
            'sales_paise' => (int) $r->sales,
            'cost_paise' => $r->cost === null ? null : (int) round((float) $r->cost),
            'costed_taxable_paise' => (int) $r->costed,
        ]))->all();
    }

    /** @return list<array<string,mixed>> */
    public static function topCategories(Period $p, ?int $branchId = null): array
    {
        if (! self::itemsInstalled()) {
            return [];
        }
        $rows = self::items($p, $branchId)
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.name as name,
                COALESCE(SUM(invoice_items.qty),0) as qty,
                COALESCE(SUM(invoice_items.taxable_paise),0) as taxable,
                COALESCE(SUM(invoice_items.line_total_paise),0) as sales,
                SUM(CASE WHEN products.cost_paise IS NULL THEN NULL ELSE invoice_items.qty * products.cost_paise END) as cost,
                COALESCE(SUM(CASE WHEN products.cost_paise IS NULL THEN 0 ELSE invoice_items.taxable_paise END),0) as costed')
            ->orderByDesc('sales')
            ->get();

        return $rows->map(fn ($r) => self::withMargin([
            'name' => $r->name ?? 'No category',
            'qty' => self::qty($r->qty),
            'taxable_paise' => (int) $r->taxable,
            'sales_paise' => (int) $r->sales,
            'cost_paise' => $r->cost === null ? null : (int) round((float) $r->cost),
            'costed_taxable_paise' => (int) $r->costed,
        ]))->all();
    }

    /** Margin only over the lines whose cost is known, so a missing cost price never shows as profit. */
    private static function withMargin(array $row): array
    {
        $costed = $row['costed_taxable_paise'];
        unset($row['costed_taxable_paise']);
        $row['margin_paise'] = $row['cost_paise'] === null ? null : $costed - $row['cost_paise'];
        $row['margin_pct'] = ($row['margin_paise'] === null || $costed === 0)
            ? null
            : round($row['margin_paise'] * 100 / $costed, 1);

        return $row;
    }

    private static function qty(mixed $q): int|float
    {
        $f = (float) $q;

        return floor($f) === $f ? (int) $f : round($f, 2);
    }

    /** GST by rate, from the bill lines. @return list<array<string,mixed>> */
    public static function gstByRate(Period $p, ?int $branchId = null): array
    {
        if (! self::itemsInstalled()) {
            return [];
        }

        $rows = [];
        $add = function ($r, int $sign) use (&$rows) {
            $k = (string) self::rate($r->rate);
            $rows[$k] ??= ['rate' => self::rate($r->rate), 'bills' => 0, 'taxable_paise' => 0, 'cgst_paise' => 0,
                'sgst_paise' => 0, 'igst_paise' => 0, 'tax_paise' => 0, 'total_paise' => 0];
            $rows[$k]['bills'] += $sign > 0 ? (int) $r->bills : 0;
            $rows[$k]['taxable_paise'] += $sign * (int) $r->taxable;
            $rows[$k]['cgst_paise'] += $sign * (int) $r->cgst;
            $rows[$k]['sgst_paise'] += $sign * (int) $r->sgst;
            $rows[$k]['igst_paise'] += $sign * (int) $r->igst;
            $rows[$k]['tax_paise'] += $sign * ((int) $r->cgst + (int) $r->sgst + (int) $r->igst);
            $rows[$k]['total_paise'] += $sign * (int) $r->total;
        };
        foreach (self::items($p, $branchId)->groupBy('invoice_items.gst_rate')
            ->selectRaw('invoice_items.gst_rate as rate, COUNT(DISTINCT invoice_items.invoice_id) as bills,
                COALESCE(SUM(invoice_items.taxable_paise),0) as taxable, COALESCE(SUM(invoice_items.cgst_paise),0) as cgst,
                COALESCE(SUM(invoice_items.sgst_paise),0) as sgst, COALESCE(SUM(invoice_items.igst_paise),0) as igst,
                COALESCE(SUM(invoice_items.line_total_paise),0) as total')->get() as $r) {
            $add($r, 1);
        }
        foreach (self::creditLines($p, $branchId)->groupBy('invoice_items.gst_rate')
            ->selectRaw('invoice_items.gst_rate as rate, 0 as bills,
                COALESCE(SUM(credit_note_items.taxable_paise),0) as taxable, COALESCE(SUM(credit_note_items.cgst_paise),0) as cgst,
                COALESCE(SUM(credit_note_items.sgst_paise),0) as sgst, COALESCE(SUM(credit_note_items.igst_paise),0) as igst,
                COALESCE(SUM(credit_note_items.line_total_paise),0) as total')->get() as $r) {
            $add($r, -1);
        }
        ksort($rows, SORT_NUMERIC);

        return array_values($rows);
    }

    /** HSN-wise summary (GSTR-1 table 12 style). @return list<array<string,mixed>> */
    public static function hsnSummary(Period $p, ?int $branchId = null): array
    {
        if (! self::itemsInstalled()) {
            return [];
        }

        $rows = [];
        $add = function ($r, int $sign) use (&$rows) {
            $hsn = $r->hsn ?: 'No HSN';
            $k = $hsn.'|'.self::rate($r->rate);
            $rows[$k] ??= ['hsn' => $hsn, 'rate' => self::rate($r->rate), 'qty' => 0, 'taxable_paise' => 0,
                'cgst_paise' => 0, 'sgst_paise' => 0, 'igst_paise' => 0, 'total_paise' => 0];
            $rows[$k]['qty'] += $sign * (float) $r->qty;
            $rows[$k]['taxable_paise'] += $sign * (int) $r->taxable;
            $rows[$k]['cgst_paise'] += $sign * (int) $r->cgst;
            $rows[$k]['sgst_paise'] += $sign * (int) $r->sgst;
            $rows[$k]['igst_paise'] += $sign * (int) $r->igst;
            $rows[$k]['total_paise'] += $sign * (int) $r->total;
        };
        foreach (self::items($p, $branchId)->groupBy('invoice_items.hsn', 'invoice_items.gst_rate')
            ->selectRaw('invoice_items.hsn as hsn, invoice_items.gst_rate as rate, COALESCE(SUM(invoice_items.qty),0) as qty,
                COALESCE(SUM(invoice_items.taxable_paise),0) as taxable, COALESCE(SUM(invoice_items.cgst_paise),0) as cgst,
                COALESCE(SUM(invoice_items.sgst_paise),0) as sgst, COALESCE(SUM(invoice_items.igst_paise),0) as igst,
                COALESCE(SUM(invoice_items.line_total_paise),0) as total')->get() as $r) {
            $add($r, 1);
        }
        foreach (self::creditLines($p, $branchId)->groupBy('invoice_items.hsn', 'invoice_items.gst_rate')
            ->selectRaw('invoice_items.hsn as hsn, invoice_items.gst_rate as rate, COALESCE(SUM(credit_note_items.qty),0) as qty,
                COALESCE(SUM(credit_note_items.taxable_paise),0) as taxable, COALESCE(SUM(credit_note_items.cgst_paise),0) as cgst,
                COALESCE(SUM(credit_note_items.sgst_paise),0) as sgst, COALESCE(SUM(credit_note_items.igst_paise),0) as igst,
                COALESCE(SUM(credit_note_items.line_total_paise),0) as total')->get() as $r) {
            $add($r, -1);
        }
        ksort($rows);

        return array_map(function ($r) {
            $r['qty'] = self::qty($r['qty']);

            return $r;
        }, array_values($rows));
    }

    private static function rate(mixed $r): int|float
    {
        $f = (float) $r;

        return floor($f) === $f ? (int) $f : $f;
    }

    /**
     * Sales and cost of goods for a period, for the simple P&L.
     *
     * @return array{sales_paise:int, taxable_paise:int, gst_paise:int, cost_paise:int, uncosted_taxable_paise:int}
     */
    public static function salesAndCost(Period $p, ?int $branchId = null): array
    {
        $out = ['sales_paise' => 0, 'taxable_paise' => 0, 'gst_paise' => 0, 'cost_paise' => 0, 'uncosted_taxable_paise' => 0];
        if (! self::itemsInstalled()) {
            return $out;
        }
        $r = self::items($p, $branchId)
            ->leftJoin('products', 'products.id', '=', 'invoice_items.product_id')
            ->selectRaw('COALESCE(SUM(invoice_items.line_total_paise),0) as sales,
                COALESCE(SUM(invoice_items.taxable_paise),0) as taxable,
                COALESCE(SUM(invoice_items.cgst_paise + invoice_items.sgst_paise + invoice_items.igst_paise),0) as gst,
                COALESCE(SUM(CASE WHEN products.cost_paise IS NULL THEN 0 ELSE invoice_items.qty * products.cost_paise END),0) as cost,
                COALESCE(SUM(CASE WHEN products.cost_paise IS NULL THEN invoice_items.taxable_paise ELSE 0 END),0) as uncosted')
            ->first();

        $c = self::creditLines($p, $branchId)->selectRaw('COALESCE(SUM(credit_note_items.line_total_paise),0) as sales,
            COALESCE(SUM(credit_note_items.taxable_paise),0) as taxable,
            COALESCE(SUM(credit_note_items.cgst_paise + credit_note_items.sgst_paise + credit_note_items.igst_paise),0) as gst')->first();

        return [
            'sales_paise' => (int) $r->sales - (int) $c->sales,
            'taxable_paise' => (int) $r->taxable - (int) $c->taxable,
            'gst_paise' => (int) $r->gst - (int) $c->gst,
            'cost_paise' => (int) round((float) $r->cost),
            'uncosted_taxable_paise' => (int) $r->uncosted,
        ];
    }

    /** One row per bill for the CA / Tally. @return list<array<string,mixed>> */
    public static function salesRegister(Period $p, ?int $branchId = null): array
    {
        if (! self::installed()) {
            return [];
        }

        return self::invoices($branchId)
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->where('invoices.created_at', '>=', $p->utcStart())->where('invoices.created_at', '<', $p->utcEnd())
            ->orderBy('invoices.created_at')->orderBy('invoices.id')
            ->get([
                'invoices.number', 'invoices.created_at', 'invoices.status', 'invoices.subtotal_paise',
                'invoices.discount_paise', 'invoices.cgst_paise', 'invoices.sgst_paise', 'invoices.igst_paise',
                'invoices.total_paise', 'invoices.credited_paise', 'invoices.paid_paise', 'invoices.due_paise',
                'invoices.customer_name', 'invoices.customer_mobile',
                'customers.name as customer', 'customers.mobile as mobile',
            ])
            ->map(fn ($r) => [
                'date' => self::istDay($r->created_at),
                'number' => $r->number,
                'customer' => $r->customer ?? $r->customer_name ?? 'Walk-in',
                'mobile' => $r->mobile ?? $r->customer_mobile,
                'status' => $r->status,
                'subtotal_paise' => (int) $r->subtotal_paise,
                'discount_paise' => (int) $r->discount_paise,
                'cgst_paise' => (int) $r->cgst_paise,
                'sgst_paise' => (int) $r->sgst_paise,
                'igst_paise' => (int) $r->igst_paise,
                'total_paise' => (int) $r->total_paise,
                'paid_paise' => (int) $r->paid_paise,
                'due_paise' => (int) $r->due_paise,
            ])->all();
    }

    // ---------------------------------------------------------------- dues

    /**
     * Open bills (due > 0, not cancelled), oldest first, with the customer and age in days.
     *
     * @return list<array<string,mixed>>
     */
    public static function openDues(?int $branchId = null): array
    {
        if (! self::installed()) {
            return [];
        }
        $today = Period::today();

        return self::invoices($branchId)
            ->leftJoin('customers', 'customers.id', '=', 'invoices.customer_id')
            ->where('invoices.status', '!=', 'cancelled')
            ->where('invoices.due_paise', '>', 0)
            ->orderBy('invoices.created_at')
            ->get([
                'invoices.id', 'invoices.number', 'invoices.customer_id', 'invoices.created_at',
                'invoices.total_paise', 'invoices.paid_paise', 'invoices.due_paise',
                'customers.name as customer', 'customers.mobile as mobile',
            ])
            ->map(function ($r) use ($today) {
                $day = CarbonImmutable::parse(self::istDay($r->created_at), Period::TZ);
                $age = (int) $day->diffInDays($today);

                return [
                    'invoice_id' => (int) $r->id,
                    'number' => $r->number,
                    'customer_id' => $r->customer_id ? (int) $r->customer_id : null,
                    'customer' => $r->customer ?? 'Walk-in',
                    'mobile' => $r->mobile,
                    'date' => $day->toDateString(),
                    'age_days' => $age,
                    'bucket' => self::bucket($age),
                    'total_paise' => (int) $r->total_paise,
                    'paid_paise' => (int) $r->paid_paise,
                    'due_paise' => (int) $r->due_paise,
                ];
            })->all();
    }

    public static function bucket(int $ageDays): string
    {
        return match (true) {
            $ageDays <= 15 => '0_15',
            $ageDays <= 30 => '16_30',
            $ageDays <= 60 => '31_60',
            default => '60_plus',
        };
    }

    /** @return array{buckets:array<string,int>, counts:array<string,int>, total_paise:int, customers:int} */
    public static function duesAgeing(?int $branchId = null): array
    {
        $buckets = array_fill_keys(array_keys(self::AGEING), 0);
        $counts = $buckets;
        $customers = [];
        foreach (self::openDues($branchId) as $d) {
            $buckets[$d['bucket']] += $d['due_paise'];
            $counts[$d['bucket']]++;
            $customers[$d['customer_id'] ?? 'walkin-'.$d['invoice_id']] = true;
        }

        return ['buckets' => $buckets, 'counts' => $counts, 'total_paise' => array_sum($buckets), 'customers' => count($customers)];
    }
}
