<?php

namespace Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only stand-in for the billing module's tables (crm/CONTRACTS.md). Same table and column
 * names the billing branch creates; the finance reports read nothing else. Remove once billing
 * is merged and its real migrations run in tests.
 */
final class CreateBillingTables
{
    public static function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('branch_id');
                $t->string('number', 30);
                $t->unsignedBigInteger('customer_id')->nullable();
                $t->string('status', 12);
                $t->bigInteger('subtotal_paise')->default(0);
                $t->bigInteger('cgst_paise')->default(0);
                $t->bigInteger('sgst_paise')->default(0);
                $t->bigInteger('igst_paise')->default(0);
                $t->bigInteger('discount_paise')->default(0);
                $t->bigInteger('total_paise')->default(0);
                $t->bigInteger('paid_paise')->default(0);
                $t->bigInteger('due_paise')->default(0);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();
            });
        }
        if (! Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('invoice_id');
                $t->unsignedBigInteger('product_id')->nullable();
                $t->string('name', 160);
                $t->string('hsn', 10)->nullable();
                $t->decimal('qty', 10, 2);
                $t->bigInteger('unit_price_paise');
                $t->decimal('gst_rate', 5, 2);
                $t->bigInteger('taxable_paise');
                $t->bigInteger('cgst_paise')->default(0);
                $t->bigInteger('sgst_paise')->default(0);
                $t->bigInteger('igst_paise')->default(0);
                $t->bigInteger('line_total_paise');
            });
        }
        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('invoice_id')->nullable();
                $t->unsignedBigInteger('customer_id')->nullable();
                $t->string('mode', 10);
                $t->bigInteger('amount_paise');
                $t->unsignedBigInteger('received_by')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->timestamp('updated_at')->nullable();
            });
        }
    }

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
            'number' => sprintf('JG-2526-%04d', $n),
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

        foreach ($items as $item) {
            DB::table('invoice_items')->insert(array_merge([
                'invoice_id' => $id, 'product_id' => null, 'hsn' => null, 'qty' => 1,
                'cgst_paise' => 0, 'sgst_paise' => 0, 'igst_paise' => 0,
                'unit_price_paise' => intdiv($item['line_total_paise'], (int) ($item['qty'] ?? 1)),
            ], $item));
        }
        foreach ($payments as $p) {
            self::payment($p[0], $p[1], $p[2] ?? $at->format('Y-m-d H:i'), $id, $attrs['customer_id'] ?? null);
        }

        return $id;
    }

    public static function payment(string $mode, int $paise, string $atIst, ?int $invoiceId = null, ?int $customerId = null): void
    {
        $at = CarbonImmutable::parse($atIst, 'Asia/Kolkata')->utc()->toDateTimeString();
        DB::table('payments')->insert([
            'invoice_id' => $invoiceId, 'customer_id' => $customerId, 'mode' => $mode, 'amount_paise' => $paise,
            'received_by' => null, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }
}
