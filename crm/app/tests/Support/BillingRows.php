<?php

namespace Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Test fixture: writes bills and payments straight into billing's real tables (real migrations)
 * with hand-picked amounts, so report tests can assert numbers worked out on paper. Flows that
 * go through the billing service use InvoiceService::create instead.
 */
final class BillingRows
{
    /**
     * Insert one bill with its lines and payments. $at is India time ("2026-11-05 11:30").
     * Header money is summed from the lines unless given.
     *
     * @param  list<array<string,mixed>>  $items
     * @param  list<array{0:string,1:int,2?:string}>  $payments  [mode, paise, at (IST, default = bill time)]
     */
    public static function bill(array $attrs, array $items = [], array $payments = []): int
    {
        static $n = 0;
        $n++;
        $at = CarbonImmutable::parse($attrs['at'] ?? 'now', 'Asia/Kolkata');
        unset($attrs['at']);
        $sum = fn (string $k) => array_sum(array_column($items, $k));
        $total = $attrs['total_paise'] ?? $sum('line_total_paise');
        $paid = $attrs['paid_paise'] ?? array_sum(array_map(fn ($p) => $p[1], $payments));

        $id = DB::table('invoices')->insertGetId(array_merge([
            'branch_id' => 1,
            'number' => sprintf('JG-2627-%04d', $n),
            'financial_year' => '2627',
            'place_of_supply' => '27',
            'customer_id' => null,
            'status' => $paid >= $total ? 'paid' : ($paid > 0 ? 'partial' : 'due'),
            'subtotal_paise' => $sum('taxable_paise'),
            'cgst_paise' => $sum('cgst_paise'),
            'sgst_paise' => $sum('sgst_paise'),
            'igst_paise' => $sum('igst_paise'),
            'discount_paise' => 0,
            'total_paise' => $total,
            'paid_paise' => $paid,
            'due_paise' => max(0, $total - $paid),
            'created_by' => null,
            'created_at' => $at->utc()->toDateTimeString(),
            'updated_at' => $at->utc()->toDateTimeString(),
            'cancelled_at' => null,
        ], $attrs));
        $branchId = $attrs['branch_id'] ?? 1;

        foreach ($items as $item) {
            DB::table('invoice_items')->insert(array_merge([
                'invoice_id' => $id, 'product_id' => null, 'hsn' => null, 'qty' => 1, 'created_at' => now(), 'updated_at' => now(),
                'cgst_paise' => 0, 'sgst_paise' => 0, 'igst_paise' => 0,
                'unit_price_paise' => intdiv($item['line_total_paise'], (int) ($item['qty'] ?? 1)),
            ], $item));
        }
        foreach ($payments as $p) {
            self::payment($p[0], $p[1], $p[2] ?? $at->format('Y-m-d H:i'), $id, $attrs['customer_id'] ?? null, $branchId);
        }

        return $id;
    }

    /** A negative $paise is stored as a refund, like billing does. */
    public static function payment(string $mode, int $paise, string $atIst, ?int $invoiceId = null, ?int $customerId = null, int $branchId = 1): void
    {
        $at = CarbonImmutable::parse($atIst, 'Asia/Kolkata')->utc()->toDateTimeString();
        DB::table('payments')->insert([
            'branch_id' => $branchId, 'kind' => $paise < 0 ? 'refund' : 'receipt', 'invoice_id' => $invoiceId, 'customer_id' => $customerId, 'mode' => $mode, 'amount_paise' => $paise,
            'received_by' => null, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }
}
