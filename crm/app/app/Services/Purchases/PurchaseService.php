<?php

namespace App\Services\Purchases;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Stock\StockService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Stock in from a supplier (UX-FLOWS §3.6). Everything that changes purchases, stock, product
 * cost and supplier money goes through here, in one transaction per action.
 *
 * Bill "paid" amounts are worked out, not typed: payments made against a bill go to that bill
 * first, everything else (and any overflow) pays the opening balance and then the oldest bills.
 */
class PurchaseService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array{supplier_id:int, bill_no:?string, bill_date:string, note:?string, photo_path:?string,
     *               items: list<array{product_id:int, qty:int, unit_cost_paise:int, gst_rate:float|int|string}>,
     *               paid_paise?:int, paid_mode?:string}  $data
     */
    public function create(array $data, User $by): Purchase
    {
        if (empty($data['items'])) {
            throw new InvalidArgumentException('A purchase needs at least one item.');
        }

        return DB::transaction(function () use ($data, $by) {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $products = Product::query()->whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');

            // Work out every line first, so the bill is saved (and logged) once with its real totals.
            $lines = [];
            foreach ($data['items'] as $line) {
                $product = $products[$line['product_id']] ?? throw new InvalidArgumentException('Unknown product.');
                $qty = (int) $line['qty'];
                $unit = (int) $line['unit_cost_paise'];
                $rate = (float) $line['gst_rate'];
                $taxable = $qty * $unit;
                $lineGst = Money::percent($taxable, $rate);
                $lines[] = compact('product', 'qty', 'unit', 'rate', 'taxable', 'lineGst');
            }
            $subtotal = array_sum(array_column($lines, 'taxable'));
            $gst = array_sum(array_column($lines, 'lineGst'));

            $purchase = Purchase::query()->create([
                'branch_id' => $by->branch_id ?? $supplier->branch_id,
                'supplier_id' => $supplier->id,
                'bill_no' => $data['bill_no'] ?? null,
                'bill_date' => $data['bill_date'],
                'photo_path' => $data['photo_path'] ?? null,
                'note' => $data['note'] ?? null,
                'subtotal_paise' => $subtotal,
                'gst_paise' => $gst,
                'total_paise' => $subtotal + $gst,
                'created_by' => $by->id,
            ]);

            foreach ($lines as ['product' => $product, 'qty' => $qty, 'unit' => $unit, 'rate' => $rate, 'taxable' => $taxable, 'lineGst' => $lineGst]) {
                $purchase->items()->create([
                    'product_id' => $product->id, 'name' => $product->name, 'qty' => $qty,
                    'unit_cost_paise' => $unit, 'gst_rate' => $rate,
                    'taxable_paise' => $taxable, 'gst_paise' => $lineGst, 'total_paise' => $taxable + $lineGst,
                ]);

                $this->stock->move($product, $qty, 'purchase', $purchase, $by, $unit, 'Stock in '.$purchase->ref());

                // Latest cost wins (cost is kept before GST: GST on purchases is input credit).
                if ($product->cost_paise !== $unit) {
                    $product->cost_paise = $unit;
                    $product->save();
                }
            }

            $paid = (int) ($data['paid_paise'] ?? 0);
            if ($paid > 0) {
                SupplierPayment::query()->create([
                    'branch_id' => $purchase->branch_id, 'supplier_id' => $supplier->id, 'purchase_id' => $purchase->id,
                    'date' => $purchase->bill_date, 'amount_paise' => $paid, 'mode' => $data['paid_mode'] ?? 'cash',
                    'note' => 'Paid with bill '.($purchase->bill_no ?: $purchase->ref()), 'created_by' => $by->id,
                ]);
            }

            $this->reallocate($supplier);

            return $purchase->refresh();
        });
    }

    /** Cancel a bill: stock goes back out at the same cost, the bill stops counting. Payments stay (they become an advance). */
    public function cancel(Purchase $purchase, string $reason, User $by): Purchase
    {
        return DB::transaction(function () use ($purchase, $reason, $by) {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($locked->isCancelled()) {
                return $locked;
            }

            foreach ($locked->items()->with('product')->get() as $item) {
                $this->stock->move($item->product, -$item->qty, 'purchase', $locked, $by, $item->unit_cost_paise,
                    'Cancelled '.$locked->ref().': '.$reason);
            }

            $locked->forceFill([
                'status' => Purchase::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $by->id,
                'cancel_reason' => mb_substr($reason, 0, 200),
            ])->save();

            $this->reallocate($locked->supplier);

            return $locked->refresh();
        });
    }

    /** @param  array{amount_paise:int, date:string, mode:string, reference:?string, note:?string, purchase_id:?int}  $data */
    public function pay(Supplier $supplier, array $data, User $by): SupplierPayment
    {
        return DB::transaction(function () use ($supplier, $data, $by) {
            $purchaseId = $data['purchase_id'] ?? null;
            if ($purchaseId && ! $supplier->purchases()->live()->whereKey($purchaseId)->exists()) {
                throw new InvalidArgumentException('That bill is not an open bill of this supplier.');
            }

            $payment = SupplierPayment::query()->create([
                'branch_id' => $by->branch_id ?? $supplier->branch_id,
                'supplier_id' => $supplier->id,
                'purchase_id' => $purchaseId,
                'date' => $data['date'],
                'amount_paise' => (int) $data['amount_paise'],
                'mode' => $data['mode'],
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $by->id,
            ]);

            $this->reallocate($supplier);

            return $payment;
        });
    }

    /** Recompute purchases.paid_paise for one supplier from its live payments. */
    public function reallocate(Supplier $supplier): void
    {
        $bills = $supplier->purchases()->orderBy('bill_date')->orderBy('id')->get();
        $live = $bills->filter(fn (Purchase $p) => ! $p->isCancelled());
        $paid = $live->mapWithKeys(fn (Purchase $p) => [$p->id => 0])->all();
        $pool = 0;

        foreach ($supplier->payments()->live()->orderBy('date')->orderBy('id')->get() as $pay) {
            $amount = (int) $pay->amount_paise;
            if ($pay->purchase_id && array_key_exists($pay->purchase_id, $paid)) {
                $bill = $live[$live->search(fn ($p) => $p->id === $pay->purchase_id)];
                $use = min($amount, $bill->total_paise - $paid[$bill->id]);
                $paid[$bill->id] += $use;
                $amount -= $use;
            }
            $pool += $amount;
        }

        // Opening balance first (an opening advance adds to the pool).
        $opening = (int) $supplier->opening_balance_paise;
        $pool = $opening > 0 ? max(0, $pool - $opening) : $pool - $opening;

        foreach ($live as $bill) {
            $use = min($pool, $bill->total_paise - $paid[$bill->id]);
            $paid[$bill->id] += $use;
            $pool -= $use;
        }

        foreach ($bills as $bill) {
            $value = $paid[$bill->id] ?? 0;
            if ($bill->paid_paise !== $value) {
                Purchase::query()->whereKey($bill->id)->update(['paid_paise' => $value]);
            }
        }
    }
}
