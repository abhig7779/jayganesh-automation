<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Support\Money;

/**
 * Turns the queries into plain tables that the screen, the print view and the Excel export all share.
 *
 * A report is ['key', 'title', 'stats' => [[label, value, hint?]], 'notes' => [string], 'sections' => [section]].
 * A section is ['title', 'columns' => [key => ['label', 'type' => money|int|qty|pct|text|date, 'bar' => bool]],
 * 'rows' => [[key => value]], 'totals' => ?[key => value], 'empty' => string].
 */
final class ReportBuilder
{
    public const REPORTS = [
        'daily' => 'Daily sales',
        'staff' => 'Sales by staff',
        'products' => 'Top products',
        'gst' => 'GST summary',
        'pnl' => 'Monthly P&L',
        'ca' => 'CA / Tally export',
    ];

    /** Which reports this person may open. */
    public static function available(User $u): array
    {
        return array_filter(self::REPORTS, fn ($label, $key) => match ($key) {
            'staff' => $u->can('reports.all_staff'),
            'pnl' => $u->can('reports.margins'),       // owner only by default
            default => true,
        }, ARRAY_FILTER_USE_BOTH);
    }

    public static function needsBilling(string $key): bool
    {
        return $key !== 'ca';
    }

    public function build(string $key, Period $p, User $u, ?int $branchId = null): array
    {
        $report = match ($key) {
            'staff' => $this->staff($p, $branchId),
            'products' => $this->products($p, $branchId, $u->can('reports.margins')),
            'gst' => $this->gst($p, $branchId),
            'pnl' => $this->pnl($p, $branchId),
            'ca' => $this->ca($p, $branchId),
            default => $this->daily($p, $branchId),
        };

        return $report + ['key' => $key, 'title' => self::REPORTS[$key] ?? 'Report', 'period' => $p->label(), 'stats' => [], 'notes' => []];
    }

    // ---------------------------------------------------------------- reports

    private function daily(Period $p, ?int $b): array
    {
        $s = SalesQueries::summary($p, $b);
        $rows = SalesQueries::dailySales($p, $b);
        $rows = array_map(fn ($r) => ['date' => $r['date']] + $r, $rows);
        $sumKeys = ['bills', 'sales_paise', 'cash_paise', 'upi_paise', 'card_paise', 'bank_paise', 'cancelled_count', 'cancelled_paise'];
        $totals = ['date' => 'Total'] + $this->sum($rows, $sumKeys);
        $totals['avg_bill_paise'] = $s['avg_bill_paise'];

        return [
            'stats' => [
                ['Sales', Money::format($s['sales_paise']), $s['bills'].' '.($s['bills'] === 1 ? 'bill' : 'bills')],
                ['Average bill', Money::format($s['avg_bill_paise']), null],
                ['Collected', Money::format($s['collected_paise']), 'Cash '.Money::format($s['modes']['cash']).' · UPI '.Money::format($s['modes']['upi']).' · Card '.Money::format($s['modes']['card'])],
                ['Dues collected', Money::format($s['dues_collected_paise']), 'from older bills'],
                ['Cancelled', (string) $s['cancelled_count'], Money::format($s['cancelled_paise']).' not counted in sales'],
            ],
            'notes' => ['Sales count bills made in the period (cancelled bills left out). Cash / UPI / Card / Bank is money received in the period, including dues paid on older bills.'],
            'sections' => [[
                'title' => 'Day by day',
                'columns' => [
                    'date' => ['label' => 'Date', 'type' => 'date'],
                    'bills' => ['label' => 'Bills', 'type' => 'int'],
                    'sales_paise' => ['label' => 'Sales', 'type' => 'money', 'bar' => true],
                    'cash_paise' => ['label' => 'Cash', 'type' => 'money'],
                    'upi_paise' => ['label' => 'UPI', 'type' => 'money'],
                    'card_paise' => ['label' => 'Card', 'type' => 'money'],
                    'bank_paise' => ['label' => 'Bank', 'type' => 'money'],
                    'avg_bill_paise' => ['label' => 'Avg bill', 'type' => 'money'],
                    'cancelled_count' => ['label' => 'Cancelled', 'type' => 'int'],
                    'cancelled_paise' => ['label' => 'Cancelled ₹', 'type' => 'money'],
                ],
                'rows' => array_reverse($rows), // newest day first
                'totals' => count($rows) > 1 ? $totals : null,
                'empty' => 'No bills in this period.',
            ]],
        ];
    }

    private function staff(Period $p, ?int $b): array
    {
        $rows = SalesQueries::salesByStaff($p, $b);
        $totals = ['name' => 'Total'] + $this->sum($rows, ['bills', 'sales_paise', 'cancelled_count']);
        $totals['avg_bill_paise'] = $totals['bills'] ? intdiv($totals['sales_paise'] + intdiv($totals['bills'], 2), $totals['bills']) : 0;

        return [
            'sections' => [[
                'title' => 'Bills made by each person',
                'columns' => [
                    'name' => ['label' => 'Staff', 'type' => 'text'],
                    'bills' => ['label' => 'Bills', 'type' => 'int'],
                    'sales_paise' => ['label' => 'Sales', 'type' => 'money', 'bar' => true],
                    'avg_bill_paise' => ['label' => 'Avg bill', 'type' => 'money'],
                    'cancelled_count' => ['label' => 'Cancelled', 'type' => 'int'],
                ],
                'rows' => $rows,
                'totals' => $rows ? $totals : null,
                'empty' => 'No bills in this period.',
            ]],
        ];
    }

    private function products(Period $p, ?int $b, bool $margins): array
    {
        $cols = [
            'name' => ['label' => 'Product', 'type' => 'text'],
            'qty' => ['label' => 'Qty', 'type' => 'qty'],
            'sales_paise' => ['label' => 'Sales (incl. GST)', 'type' => 'money', 'bar' => true],
            'taxable_paise' => ['label' => 'Before GST', 'type' => 'money'],
        ];
        if ($margins) {
            $cols += [
                'cost_paise' => ['label' => 'Cost', 'type' => 'money'],
                'margin_paise' => ['label' => 'Margin', 'type' => 'money'],
                'margin_pct' => ['label' => 'Margin %', 'type' => 'pct'],
            ];
        }
        $strip = fn (array $rows) => $margins ? $rows : array_map(
            fn ($r) => array_diff_key($r, ['cost_paise' => 1, 'margin_paise' => 1, 'margin_pct' => 1]), $rows);

        $products = $strip(SalesQueries::topProducts($p, $b));
        $categories = $strip(SalesQueries::topCategories($p, $b));
        $catCols = ['name' => ['label' => 'Category', 'type' => 'text']] + array_slice($cols, 1, null, true);

        return [
            'notes' => $margins ? ['Margin = price before GST − cost (units × the product\'s cost price), counted only on items that have a cost price. Blank where no cost price is filled in.'] : [],
            'sections' => [
                ['title' => 'Top 50 products and services', 'columns' => $cols, 'rows' => $products, 'totals' => null, 'empty' => 'Nothing sold in this period.'],
                ['title' => 'By category', 'columns' => $catCols, 'rows' => $categories, 'totals' => null, 'empty' => 'Nothing sold in this period.'],
            ],
        ];
    }

    private function gst(Period $p, ?int $b): array
    {
        $rates = array_map(fn ($r) => ['rate_label' => $r['rate'].'%'] + $r, SalesQueries::gstByRate($p, $b));
        $hsn = array_map(fn ($r) => ['rate_label' => $r['rate'].'%'] + $r, SalesQueries::hsnSummary($p, $b));
        $money = ['taxable_paise', 'cgst_paise', 'sgst_paise', 'igst_paise', 'tax_paise', 'total_paise'];
        $rateTotals = ['rate_label' => 'Total'] + $this->sum($rates, array_merge(['bills'], $money));
        $rateTotals['bills'] = null; // a bill can have lines at two rates, so a sum would double count
        $hsnTotals = ['hsn' => 'Total'] + $this->sum($hsn, ['qty', 'taxable_paise', 'cgst_paise', 'sgst_paise', 'igst_paise', 'total_paise']);
        $t = $this->sum($rates, $money);

        return [
            'stats' => [
                ['Taxable value', Money::format($t['taxable_paise']), null],
                ['CGST', Money::format($t['cgst_paise']), null],
                ['SGST', Money::format($t['sgst_paise']), null],
                ['IGST', Money::format($t['igst_paise']), null],
            ],
            'notes' => ['From the bill lines of bills made in the period. Cancelled bills are left out.'],
            'sections' => [
                [
                    'title' => 'By GST rate',
                    'columns' => [
                        'rate_label' => ['label' => 'GST rate', 'type' => 'text'],
                        'bills' => ['label' => 'Bills', 'type' => 'int'],
                        'taxable_paise' => ['label' => 'Taxable value', 'type' => 'money', 'bar' => true],
                        'cgst_paise' => ['label' => 'CGST', 'type' => 'money'],
                        'sgst_paise' => ['label' => 'SGST', 'type' => 'money'],
                        'igst_paise' => ['label' => 'IGST', 'type' => 'money'],
                        'tax_paise' => ['label' => 'Total tax', 'type' => 'money'],
                        'total_paise' => ['label' => 'Value incl. GST', 'type' => 'money'],
                    ],
                    'rows' => $rates,
                    'totals' => $rates ? $rateTotals : null,
                    'empty' => 'No bills in this period.',
                ],
                [
                    'title' => 'HSN summary',
                    'columns' => [
                        'hsn' => ['label' => 'HSN / SAC', 'type' => 'text'],
                        'rate_label' => ['label' => 'Rate', 'type' => 'text'],
                        'qty' => ['label' => 'Qty', 'type' => 'qty'],
                        'taxable_paise' => ['label' => 'Taxable value', 'type' => 'money'],
                        'cgst_paise' => ['label' => 'CGST', 'type' => 'money'],
                        'sgst_paise' => ['label' => 'SGST', 'type' => 'money'],
                        'igst_paise' => ['label' => 'IGST', 'type' => 'money'],
                        'total_paise' => ['label' => 'Value incl. GST', 'type' => 'money'],
                    ],
                    'rows' => $hsn,
                    'totals' => $hsn ? $hsnTotals : null,
                    'empty' => 'No bills in this period.',
                ],
            ],
        ];
    }

    private function pnl(Period $p, ?int $b): array
    {
        $rows = [];
        foreach ($p->months() as $ym) {
            $start = max($p->fromDate(), $ym.'-01');
            $end = min($p->toDate(), date('Y-m-t', strtotime($ym.'-01')));
            $mp = Period::between($start, $end);
            $sc = SalesQueries::salesAndCost($mp, $b);
            $exp = ExpenseQueries::total($mp, $b);
            $gross = $sc['taxable_paise'] - $sc['cost_paise'];
            $rows[] = [
                'month' => \DateTime::createFromFormat('Y-m-d', $ym.'-01')->format('M Y'),
                'sales_paise' => $sc['sales_paise'],
                'gst_paise' => $sc['gst_paise'],
                'taxable_paise' => $sc['taxable_paise'],
                'cost_paise' => $sc['cost_paise'],
                'gross_paise' => $gross,
                'expenses_paise' => $exp,
                'net_paise' => $gross - $exp,
                'uncosted_paise' => $sc['uncosted_taxable_paise'],
            ];
        }
        $t = $this->sum($rows, ['sales_paise', 'gst_paise', 'taxable_paise', 'cost_paise', 'gross_paise', 'expenses_paise', 'net_paise', 'uncosted_paise']);
        $cats = ExpenseQueries::byCategory($p, $b);

        $notes = ['Simple P&L: sales before GST − cost of goods − expenses. Cost of goods uses each product\'s cost price, so it is only as good as the cost prices in Stock.'];
        if ($t['uncosted_paise'] > 0) {
            $notes[] = Money::format($t['uncosted_paise']).' of sales (before GST) is on items with no cost price, so their cost is counted as ₹0 and profit looks higher than it is.';
        }

        return [
            'stats' => [
                ['Sales before GST', Money::format($t['taxable_paise']), 'GST collected '.Money::format($t['gst_paise'])],
                ['Cost of goods', Money::format($t['cost_paise']), null],
                ['Expenses', Money::format($t['expenses_paise']), null],
                ['Net profit', CashCloseService::signedMoney($t['net_paise']), null],
            ],
            'notes' => $notes,
            'sections' => [
                [
                    'title' => 'Month by month',
                    'columns' => [
                        'month' => ['label' => 'Month', 'type' => 'text'],
                        'sales_paise' => ['label' => 'Sales incl. GST', 'type' => 'money'],
                        'gst_paise' => ['label' => 'GST', 'type' => 'money'],
                        'taxable_paise' => ['label' => 'Sales before GST', 'type' => 'money'],
                        'cost_paise' => ['label' => 'Cost of goods', 'type' => 'money'],
                        'gross_paise' => ['label' => 'Gross profit', 'type' => 'money'],
                        'expenses_paise' => ['label' => 'Expenses', 'type' => 'money'],
                        'net_paise' => ['label' => 'Net profit', 'type' => 'money', 'bar' => true],
                    ],
                    'rows' => $rows,
                    'totals' => count($rows) > 1 ? ['month' => 'Total'] + $t : null,
                    'empty' => 'Nothing in this period.',
                ],
                [
                    'title' => 'Expenses by category',
                    'columns' => [
                        'name' => ['label' => 'Category', 'type' => 'text'],
                        'count' => ['label' => 'Entries', 'type' => 'int'],
                        'total_paise' => ['label' => 'Amount', 'type' => 'money', 'bar' => true],
                    ],
                    'rows' => $cats,
                    'totals' => $cats ? ['name' => 'Total'] + $this->sum($cats, ['count', 'total_paise']) : null,
                    'empty' => 'No expenses in this period.',
                ],
            ],
        ];
    }

    private function ca(Period $p, ?int $b): array
    {
        $sales = SalesQueries::installed() ? SalesQueries::summary($p, $b) : null;
        $expTotal = ExpenseQueries::total($p, $b);

        return [
            'stats' => array_values(array_filter([
                $sales ? ['Sales register', $sales['bills'] + $sales['cancelled_count'].' bills', Money::format($sales['sales_paise']).' sales'] : null,
                ['Expense register', ExpenseQueries::count($p, $b).' entries', Money::format($expTotal)],
            ])),
            'notes' => ['Both registers download as Excel (.xlsx) files that Tally and most CA software can import. Cancelled bills stay in the sales register with status "cancelled" so bill numbers have no gaps.'],
            'sections' => [],
        ];
    }

    /** Sales register and expense register as report sections (for the CA export). */
    public function register(string $type, Period $p, ?int $b): array
    {
        if ($type === 'expenses') {
            $rows = ExpenseQueries::register($p, $b);

            return [
                'key' => 'expense-register', 'title' => 'Expense register', 'period' => $p->label(), 'stats' => [], 'notes' => [],
                'sections' => [[
                    'title' => 'Expenses',
                    'columns' => [
                        'date' => ['label' => 'Date', 'type' => 'date'],
                        'category' => ['label' => 'Category', 'type' => 'text'],
                        'paid_to' => ['label' => 'Paid to', 'type' => 'text'],
                        'mode' => ['label' => 'Mode', 'type' => 'text'],
                        'amount_paise' => ['label' => 'Amount', 'type' => 'money'],
                        'note' => ['label' => 'Note', 'type' => 'text'],
                        'receipt' => ['label' => 'Receipt', 'type' => 'text'],
                        'entered_by' => ['label' => 'Entered by', 'type' => 'text'],
                    ],
                    'rows' => $rows,
                    'totals' => ['date' => 'Total', 'amount_paise' => array_sum(array_column($rows, 'amount_paise'))],
                    'empty' => 'No expenses in this period.',
                ]],
            ];
        }

        $rows = SalesQueries::salesRegister($p, $b);
        $live = array_filter($rows, fn ($r) => $r['status'] !== 'cancelled');
        $money = ['subtotal_paise', 'discount_paise', 'cgst_paise', 'sgst_paise', 'igst_paise', 'total_paise', 'paid_paise', 'due_paise'];

        return [
            'key' => 'sales-register', 'title' => 'Sales register', 'period' => $p->label(), 'stats' => [], 'notes' => [],
            'sections' => [[
                'title' => 'Bills',
                'columns' => [
                    'date' => ['label' => 'Date', 'type' => 'date'],
                    'number' => ['label' => 'Bill no.', 'type' => 'text'],
                    'customer' => ['label' => 'Customer', 'type' => 'text'],
                    'mobile' => ['label' => 'Mobile', 'type' => 'text'],
                    'status' => ['label' => 'Status', 'type' => 'text'],
                    'subtotal_paise' => ['label' => 'Subtotal', 'type' => 'money'],
                    'discount_paise' => ['label' => 'Discount', 'type' => 'money'],
                    'cgst_paise' => ['label' => 'CGST', 'type' => 'money'],
                    'sgst_paise' => ['label' => 'SGST', 'type' => 'money'],
                    'igst_paise' => ['label' => 'IGST', 'type' => 'money'],
                    'total_paise' => ['label' => 'Bill total', 'type' => 'money'],
                    'paid_paise' => ['label' => 'Paid', 'type' => 'money'],
                    'due_paise' => ['label' => 'Due', 'type' => 'money'],
                ],
                'rows' => $rows,
                'totals' => ['date' => 'Total (not cancelled)'] + $this->sum(array_values($live), $money),
                'empty' => 'No bills in this period.',
            ]],
        ];
    }

    private function sum(array $rows, array $keys): array
    {
        $out = array_fill_keys($keys, 0);
        foreach ($rows as $r) {
            foreach ($keys as $k) {
                $out[$k] += $r[$k] ?? 0;
            }
        }

        return $out;
    }
}
