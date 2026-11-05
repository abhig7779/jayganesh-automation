<?php

namespace App\Services\Billing;

use App\Events\InvoiceCancelled;
use App\Events\InvoiceSaved;
use App\Models\BillDraft;
use App\Models\Branch;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Customers\CustomerService;
use App\Services\Stock\StockService;
use App\Support\Money;
use App\Support\RegNo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves, cancels and part-returns bills. Everything a bill touches (number, items, payments,
 * stock, customer totals, audit) is written in ONE transaction, and InvoiceSaved fires only
 * after it commits (ARCHITECTURE.md §2, crm/CONTRACTS.md).
 */
class InvoiceService
{
    public function __construct(
        private readonly NumberSeries $series,
        private readonly StockService $stock,
        private readonly CustomerService $customers,
    ) {}

    /**
     * @param  array{
     *   draft_key?: ?string, customer_id?: ?int, customer?: ?array, customer_car_id?: ?int,
     *   quotation_id?: ?int, place_of_supply?: ?string, customer_gstin?: ?string,
     *   items: array<int, array{product_id?: ?int, name?: ?string, hsn?: ?string, qty: int, unit_price_paise?: ?int, gst_rate?: mixed, price_includes_gst?: ?bool, discount_paise?: ?int}>,
     *   bill_discount_paise?: ?int, discount_reason?: ?string,
     *   payments?: array<int, array{mode: string, amount_paise: int, reference?: ?string}>,
     *   send_whatsapp?: ?bool, notes?: ?string
     * }  $data
     *
     * @throws ValidationException
     */
    public function create(array $data, User $by): Invoice
    {
        $branchId = (int) ($by->branch_id ?? Branch::query()->value('id'));
        $draftKey = $data['draft_key'] ?? null;

        // A double tap or a network retry sends the same draft key: return the bill already made.
        if ($draftKey && ($existing = $this->findByDraftKey($branchId, $draftKey))) {
            return $existing;
        }

        $calc = GstCalculator::fromSettings($branchId);
        [$lines, $listValue] = $this->buildLines($data['items'] ?? [], $by, $calc);
        $totals = $calc->calculate($lines, (int) ($data['bill_discount_paise'] ?? 0), $data['place_of_supply'] ?? null);
        $mrpSavings = array_sum(array_map(fn ($l) => self::mrpSaving($l['mrp_paise'] ?? null, $l['qty'], $l['gross_paise']), $totals['lines']));

        $this->checkDiscount($totals, $listValue, $by);
        $payments = $this->cleanPayments($data['payments'] ?? []);
        $paid = array_sum(array_column($payments, 'amount_paise'));
        if ($paid > $totals['total_paise']) {
            throw ValidationException::withMessages(['payments' => 'Payments add up to more than the bill total ('.Money::format($totals['total_paise']).').']);
        }
        $due = $totals['total_paise'] - $paid;
        $walkIn = empty($data['customer_id']) && empty($data['customer']['mobile']);
        $this->checkDue($due, $walkIn, $by, $branchId);

        try {
            $invoice = DB::transaction(function () use ($data, $by, $branchId, $draftKey, $calc, $totals, $mrpSavings, $payments, $paid, $due) {
                [$customer, $car] = $this->resolveCustomer($data, $by);
                $num = $this->series->next($branchId, NumberSeries::TYPE_INVOICE);

                $invoice = Invoice::create([
                    'branch_id' => $branchId,
                    'number' => $num['number'],
                    'financial_year' => $num['financial_year'],
                    'kind' => $calc->isRegistered() ? Invoice::KIND_TAX_INVOICE : Invoice::KIND_BILL_OF_SUPPLY,
                    'customer_id' => $customer?->id,
                    'customer_car_id' => $car?->id,
                    'quotation_id' => $data['quotation_id'] ?? null,
                    'customer_name' => $customer?->name ?? 'Walk-in customer',
                    'customer_mobile' => $customer?->mobile,
                    'car_label' => $car ? trim($car->label().($car->reg_no ? ' · '.RegNo::pretty($car->reg_no) : '')) : null,
                    'seller_gstin' => $calc->isRegistered() ? Setting::get('tax.gstin', null, $branchId) : null,
                    'customer_gstin' => $data['customer_gstin'] ?? null,
                    'place_of_supply' => $totals['place_of_supply'],
                    'gross_paise' => $totals['gross_paise'],
                    'subtotal_paise' => $totals['subtotal_paise'],
                    'cgst_paise' => $totals['cgst_paise'],
                    'sgst_paise' => $totals['sgst_paise'],
                    'igst_paise' => $totals['igst_paise'],
                    'discount_paise' => $totals['discount_paise'],
                    'discount_reason' => $data['discount_reason'] ?? null,
                    'mrp_savings_paise' => $mrpSavings,
                    'round_off_paise' => $totals['round_off_paise'],
                    'total_paise' => $totals['total_paise'],
                    'paid_paise' => $paid,
                    'due_paise' => $due,
                    'status' => self::statusFor($paid, $due),
                    'send_whatsapp' => (bool) ($data['send_whatsapp'] ?? true) && $customer !== null && $customer->whatsapp_opt_in,
                    'draft_key' => $draftKey,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $by->id,
                ]);

                foreach ($totals['lines'] as $pos => $l) {
                    $invoice->items()->create([
                        'product_id' => $l['product_id'],
                        'name' => $l['name'],
                        'hsn' => $l['hsn'],
                        'unit' => $l['unit'],
                        'qty' => $l['qty'],
                        'unit_price_paise' => $l['unit_price_paise'],
                        'price_includes_gst' => $l['price_includes_gst'],
                        'list_price_paise' => $l['list_price_paise'],
                        'mrp_paise' => $l['mrp_paise'] ?? null,
                        'discount_paise' => $l['discount_paise'],
                        'gst_rate' => $l['gst_rate'],
                        'taxable_paise' => $l['taxable_paise'],
                        'cgst_paise' => $l['cgst_paise'],
                        'sgst_paise' => $l['sgst_paise'],
                        'igst_paise' => $l['igst_paise'],
                        'line_total_paise' => $l['line_total_paise'],
                        'position' => $pos,
                    ]);

                    if ($l['product_id']) {
                        /** @var Product $product */
                        $product = $l['product_model'];
                        if ($product->track_stock) {
                            $this->stock->move($product, -$l['qty'], 'sale', $invoice, $by);
                        }
                        Product::query()->whereKey($product->id)->increment('sold_count', $l['qty']);
                    }
                }

                foreach ($payments as $p) {
                    $payment = Payment::create([
                        'branch_id' => $branchId,
                        'invoice_id' => $invoice->id,
                        'customer_id' => $customer?->id,
                        'kind' => Payment::KIND_RECEIPT,
                        'mode' => $p['mode'],
                        'amount_paise' => $p['amount_paise'],
                        'reference' => $p['reference'],
                        'received_by' => $by->id,
                    ]);
                    $payment->allocations()->create(['invoice_id' => $invoice->id, 'amount_paise' => $p['amount_paise']]);
                }

                if ($customer) {
                    $customer->forceFill([
                        'last_visit_at' => now(),
                        'total_spent_paise' => $customer->total_spent_paise + $totals['total_paise'],
                    ])->saveQuietly();
                }

                BillDraft::query()->where('user_id', $by->id)->where('draft_key', $draftKey)->delete();

                activity('bills')->performedOn($invoice)->causedBy($by)->event('created')
                    ->withProperties(['number' => $invoice->number, 'total_paise' => $invoice->total_paise, 'paid_paise' => $paid, 'due_paise' => $due])
                    ->log("Bill {$invoice->number} saved");

                // ShouldDispatchAfterCommit: listeners run only once this transaction commits.
                event(new InvoiceSaved($invoice));

                return $invoice;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Two requests with the same draft key raced; the other one won. Its number stands
            // and ours rolled back with this transaction, so there is still no gap.
            if ($draftKey && ($existing = $this->findByDraftKey($branchId, $draftKey))) {
                return $existing;
            }
            throw $e;
        }

        return $invoice->load('items');
    }

    /** Owner/Admin only. Stock goes back, money is refunded, the number is kept (UX-FLOWS §3.7). */
    public function cancel(Invoice $invoice, string $reason, User $by): Invoice
    {
        if (! $by->can('invoices.cancel')) {
            throw new AuthorizationException('Only the Owner or an Admin can cancel a bill.');
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Give a reason for cancelling.']);
        }

        DB::transaction(function () use ($invoice, $reason, $by) {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->isCancelled()) {
                throw ValidationException::withMessages(['reason' => 'This bill is already cancelled.']);
            }

            foreach ($invoice->items()->with('product')->get() as $item) {
                $left = $item->returnableQty();
                if ($item->product && $left > 0) {
                    if ($item->product->track_stock) {
                        $this->stock->move($item->product, $left, 'sale_cancel', $invoice, $by);
                    }
                    Product::query()->whereKey($item->product_id)->where('sold_count', '>=', $left)->decrement('sold_count', $left);
                }
            }

            $alreadyRefunded = (int) $invoice->creditNotes()->sum('refund_paise');
            $refund = $invoice->paid_paise - $alreadyRefunded;
            if ($refund > 0) {
                $this->recordRefund($invoice, $refund, $by, "Bill {$invoice->number} cancelled");
            }

            if ($invoice->customer_id) {
                $customer = Customer::query()->whereKey($invoice->customer_id)->lockForUpdate()->first();
                $customer?->forceFill([
                    'total_spent_paise' => max(0, $customer->total_spent_paise - ($invoice->total_paise - $invoice->credited_paise)),
                ])->saveQuietly();
            }

            $invoice->forceFill([
                'status' => Invoice::STATUS_CANCELLED,
                'due_paise' => 0,
                'cancel_reason' => mb_substr(trim($reason), 0, 300),
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
            ])->save();

            activity('bills')->performedOn($invoice)->causedBy($by)->event('cancelled')
                ->withProperties(['number' => $invoice->number, 'reason' => $reason, 'refund_paise' => max(0, $refund)])
                ->log("Bill {$invoice->number} cancelled: {$reason}");

            event(new InvoiceCancelled($invoice));
        });

        return $invoice->refresh();
    }

    /**
     * Partial return: a credit note for the chosen items only. The credit first clears any due
     * on the bill; the rest is refunded in $refundMode.
     *
     * @param  array<int,int>  $qtyByItemId  invoice_item_id => qty returned
     */
    public function returnItems(Invoice $invoice, array $qtyByItemId, string $reason, User $by, string $refundMode = 'cash'): CreditNote
    {
        if (! $by->can('invoices.cancel')) {
            throw new AuthorizationException('Only the Owner or an Admin can take items back.');
        }
        $qtyByItemId = array_filter(array_map('intval', $qtyByItemId), fn ($q) => $q > 0);
        if ($qtyByItemId === []) {
            throw ValidationException::withMessages(['items' => 'Pick at least one item to take back.']);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Give a reason for the return.']);
        }
        if (! in_array($refundMode, Payment::MODES, true)) {
            $refundMode = 'cash';
        }

        return DB::transaction(function () use ($invoice, $qtyByItemId, $reason, $by, $refundMode) {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->isCancelled()) {
                throw ValidationException::withMessages(['items' => 'This bill is cancelled.']);
            }
            $items = $invoice->items()->with('product')->whereIn('id', array_keys($qtyByItemId))->get()->keyBy('id');
            if ($items->count() !== count($qtyByItemId)) {
                throw ValidationException::withMessages(['items' => 'Those items are not on this bill.']);
            }

            $num = $this->series->next($invoice->branch_id, NumberSeries::TYPE_CREDIT_NOTE);
            $note = CreditNote::create([
                'branch_id' => $invoice->branch_id,
                'number' => $num['number'],
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'reason' => mb_substr(trim($reason), 0, 300),
                'created_by' => $by->id,
            ]);

            $sum = ['taxable_paise' => 0, 'cgst_paise' => 0, 'sgst_paise' => 0, 'igst_paise' => 0, 'line_total_paise' => 0];
            foreach ($qtyByItemId as $itemId => $qty) {
                /** @var InvoiceItem $item */
                $item = $items[$itemId];
                if ($qty > $item->returnableQty()) {
                    throw ValidationException::withMessages(['items' => "Only {$item->returnableQty()} of {$item->name} can be returned."]);
                }
                $values = $this->creditValues($item, $qty);
                $note->items()->create(['invoice_item_id' => $item->id, 'qty' => $qty] + $values);
                foreach ($sum as $k => $v) {
                    $sum[$k] = $v + $values[$k];
                }
                $item->increment('returned_qty', $qty);
                if ($item->product) {
                    if ($item->product->track_stock) {
                        $this->stock->move($item->product, $qty, 'return', $invoice, $by, null, "Credit note {$note->number}");
                    }
                    Product::query()->whereKey($item->product_id)->where('sold_count', '>=', $qty)->decrement('sold_count', $qty);
                }
            }

            $credit = $sum['line_total_paise'];
            $clearsDue = min($invoice->due_paise, $credit);
            $refund = $credit - $clearsDue;
            if ($refund > 0) {
                $this->recordRefund($invoice, $refund, $by, "Credit note {$note->number}", $refundMode);
            }

            $note->update([
                'subtotal_paise' => $sum['taxable_paise'], 'cgst_paise' => $sum['cgst_paise'],
                'sgst_paise' => $sum['sgst_paise'], 'igst_paise' => $sum['igst_paise'],
                'total_paise' => $credit, 'refund_paise' => $refund,
            ]);

            $due = $invoice->due_paise - $clearsDue;
            $invoice->forceFill([
                'credited_paise' => $invoice->credited_paise + $credit,
                'due_paise' => $due,
                'status' => self::statusFor($invoice->paid_paise, $due),
            ])->save();

            if ($invoice->customer_id) {
                Customer::query()->whereKey($invoice->customer_id)->where('total_spent_paise', '>=', $credit)->decrement('total_spent_paise', $credit);
            }

            activity('bills')->performedOn($invoice)->causedBy($by)->event('returned')
                ->withProperties(['credit_note' => $note->number, 'credit_paise' => $credit, 'refund_paise' => $refund])
                ->log("Credit note {$note->number} on {$invoice->number}");

            return $note->load('items');
        });
    }

    /**
     * "You save" from MRP on one line: MRP × qty minus what the line costs before any discount
     * (GST-inclusive). Zero when there is no MRP or the MRP is not above our price. GST is
     * always charged on our selling price, never on the MRP.
     */
    public static function mrpSaving(?int $mrpPaise, int $qty, int $grossInclPaise): int
    {
        return $mrpPaise ? max(0, $mrpPaise * $qty - $grossInclPaise) : 0;
    }

    public static function statusFor(int $paid, int $due): string
    {
        if ($due <= 0) {
            return Invoice::STATUS_PAID;
        }

        return $paid > 0 ? Invoice::STATUS_PARTIAL : Invoice::STATUS_DUE;
    }

    /** Highest discount % this user may give without approval; null = no limit. */
    public static function discountLimitPercent(User $user): ?float
    {
        return $user->can('prices.manage') ? null : (float) (Setting::get('billing.max_discount_percent') ?? 10);
    }

    /** Biggest due this user may leave on a bill; null = no limit. */
    public static function dueLimitPaise(User $user, ?int $branchId = null): ?int
    {
        return $user->can('invoices.cancel') ? null : (int) (Setting::get('billing.due_limit_paise', null, $branchId) ?? 200000);
    }

    public static function canAddCustomItem(User $user): bool
    {
        return $user->can('prices.manage') || $user->can('invoices.cancel');
    }

    // ---------------------------------------------------------------------------------------

    private function findByDraftKey(int $branchId, string $key): ?Invoice
    {
        return Invoice::query()->where('branch_id', $branchId)->where('draft_key', $key)->with('items')->first();
    }

    /** @return array{0: array<int,array>, 1: int} lines for the calculator, and the list value incl. GST */
    private function buildLines(array $items, User $by, GstCalculator $calc): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one item.']);
        }
        $ids = array_filter(array_map(fn ($i) => $i['product_id'] ?? null, $items));
        $products = Product::query()->whereIn('id', $ids)->get()->keyBy('id');
        $defaultRate = Setting::get('tax.default_gst_rate', 18);

        $lines = [];
        $listValue = 0;
        foreach (array_values($items) as $i => $item) {
            $qty = (int) ($item['qty'] ?? 0);
            if ($qty < 1 || $qty > 9999) {
                throw ValidationException::withMessages(["items.$i.qty" => 'Quantity must be between 1 and 9,999.']);
            }
            $productId = $item['product_id'] ?? null;

            if ($productId) {
                $p = $products->get($productId);
                if (! $p || ! $p->is_active) {
                    throw ValidationException::withMessages(["items.$i.product_id" => 'This product is no longer for sale. Remove it and search again.']);
                }
                $unit = isset($item['unit_price_paise']) ? (int) $item['unit_price_paise'] : $p->price_paise;
                if ($unit < 0) {
                    throw ValidationException::withMessages(["items.$i.unit_price_paise" => 'The price cannot be negative.']);
                }
                $line = [
                    'product_id' => $p->id,
                    'product_model' => $p,
                    'name' => $p->name,
                    'hsn' => $p->hsn,
                    'unit' => $p->unit ?: 'pcs',
                    'qty' => $qty,
                    'unit_price_paise' => $unit,
                    'list_price_paise' => $p->price_paise,
                    'mrp_paise' => $p->mrp_paise,
                    'gst_rate' => $p->gst_rate,
                    'price_includes_gst' => $p->price_includes_gst,
                    'discount_paise' => max(0, (int) ($item['discount_paise'] ?? 0)),
                ];
                $listUnit = $p->price_paise;
            } else {
                if (! self::canAddCustomItem($by)) {
                    throw ValidationException::withMessages(["items.$i.product_id" => 'Only the Owner or an Admin can add an item that is not in the product list.']);
                }
                $name = trim((string) ($item['name'] ?? ''));
                $unit = (int) ($item['unit_price_paise'] ?? -1);
                if ($name === '' || $unit < 0) {
                    throw ValidationException::withMessages(["items.$i.name" => 'A custom item needs a name and a price.']);
                }
                $line = [
                    'product_id' => null,
                    'product_model' => null,
                    'name' => mb_substr($name, 0, 160),
                    'hsn' => $item['hsn'] ?? null,
                    'unit' => 'pcs',
                    'qty' => $qty,
                    'unit_price_paise' => $unit,
                    'list_price_paise' => $unit,
                    'mrp_paise' => null,
                    'gst_rate' => $item['gst_rate'] ?? $defaultRate,
                    'price_includes_gst' => (bool) ($item['price_includes_gst'] ?? true),
                    'discount_paise' => max(0, (int) ($item['discount_paise'] ?? 0)),
                ];
                $listUnit = $unit;
            }

            // List value incl. GST: what the bill would be at the shelf price with no discount.
            $one = $calc->calculate([['qty' => $qty, 'unit_price_paise' => $listUnit, 'gst_rate' => $line['gst_rate'], 'price_includes_gst' => $line['price_includes_gst']]]);
            $listValue += $one['before_round_paise'];
            $lines[] = $line;
        }

        return [$lines, $listValue];
    }

    /** Sales may give up to billing.max_discount_percent off the shelf price; more needs prices.manage. */
    private function checkDiscount(array $totals, int $listValue, User $by): void
    {
        $limit = self::discountLimitPercent($by);
        if ($limit === null || $listValue <= 0) {
            return;
        }
        $given = $listValue - $totals['before_round_paise'];
        if ($given * 100 > $listValue * $limit) {
            $pct = round($given * 100 / $listValue, 1);
            throw ValidationException::withMessages([
                'bill_discount_paise' => "Discount is {$pct}% (".Money::format($given)."). Above {$limit}% needs the Owner's approval.",
            ]);
        }
    }

    private function checkDue(int $due, bool $walkIn, User $by, int $branchId): void
    {
        if ($due <= 0) {
            return;
        }
        if ($walkIn) {
            throw ValidationException::withMessages(['payments' => 'To leave a due, enter the customer’s mobile number.']);
        }
        $limit = self::dueLimitPaise($by, $branchId);
        if ($limit !== null && $due > $limit) {
            throw ValidationException::withMessages(['payments' => 'A due over '.Money::format($limit).' needs an Admin or the Owner. Due now: '.Money::format($due).'.']);
        }
    }

    /** @return array<int, array{mode:string, amount_paise:int, reference:?string}> */
    private function cleanPayments(array $payments): array
    {
        $out = [];
        foreach (array_values($payments) as $i => $p) {
            $amount = (int) ($p['amount_paise'] ?? 0);
            if ($amount === 0) {
                continue;
            }
            $mode = (string) ($p['mode'] ?? '');
            if (! in_array($mode, Payment::MODES, true)) {
                throw ValidationException::withMessages(["payments.$i.mode" => 'Pick Cash, UPI, Card or Bank.']);
            }
            if ($amount < 0) {
                throw ValidationException::withMessages(["payments.$i.amount_paise" => 'An amount cannot be negative.']);
            }
            $out[] = ['mode' => $mode, 'amount_paise' => $amount, 'reference' => isset($p['reference']) ? mb_substr((string) $p['reference'], 0, 80) : null];
        }

        return $out;
    }

    /** @return array{0: ?Customer, 1: ?CustomerCar} */
    private function resolveCustomer(array $data, User $by): array
    {
        $customer = null;
        if (! empty($data['customer_id'])) {
            $customer = Customer::query()->find($data['customer_id']);
            if (! $customer) {
                throw ValidationException::withMessages(['customer_id' => 'Customer not found.']);
            }
        } elseif (! empty($data['customer']['mobile'])) {
            $customer = $this->customers->findOrCreateByMobile(
                $data['customer']['mobile'],
                Arr::only($data['customer'], ['name', 'source', 'area']) + ['branch_id' => $by->branch_id],
                $by,
            );
        }
        if (! $customer) {
            return [null, null];
        }

        $car = null;
        if (! empty($data['customer_car_id'])) {
            $car = $customer->cars()->whereKey($data['customer_car_id'])->first();
            if (! $car) {
                throw ValidationException::withMessages(['customer_car_id' => 'That car belongs to another customer.']);
            }
        } elseif (! empty($data['customer']['car']['model'])) {
            $carData = $data['customer']['car'];
            $reg = RegNo::normalize($carData['reg_no'] ?? null);
            $car = ($reg ? $customer->cars()->where('reg_no', $reg)->first() : null)
                ?? $this->customers->addCar($customer, [
                    'make' => $carData['make'] ?? '',
                    'model' => $carData['model'],
                    'reg_no' => $reg,
                ], $by);
        }

        return [$customer, $car];
    }

    /** Credit values for $qty of a line; the last unit returned takes the remainder, so totals match the bill. */
    private function creditValues(InvoiceItem $item, int $qty): array
    {
        $keys = ['taxable_paise', 'cgst_paise', 'sgst_paise', 'igst_paise', 'line_total_paise'];
        if ($qty === $item->returnableQty()) {
            $already = DB::table('credit_note_items')->where('invoice_item_id', $item->id)
                ->selectRaw('COALESCE(SUM(taxable_paise),0) t, COALESCE(SUM(cgst_paise),0) c, COALESCE(SUM(sgst_paise),0) s, COALESCE(SUM(igst_paise),0) i, COALESCE(SUM(line_total_paise),0) l')
                ->first();
            $prev = ['taxable_paise' => $already->t, 'cgst_paise' => $already->c, 'sgst_paise' => $already->s, 'igst_paise' => $already->i, 'line_total_paise' => $already->l];

            return collect($keys)->mapWithKeys(fn ($k) => [$k => $item->{$k} - (int) $prev[$k]])->all();
        }
        $v = collect($keys)->mapWithKeys(fn ($k) => [$k => intdiv(2 * $item->{$k} * $qty + $item->qty, 2 * $item->qty)])->all();
        $v['line_total_paise'] = $v['taxable_paise'] + $v['cgst_paise'] + $v['sgst_paise'] + $v['igst_paise'];

        return $v;
    }

    private function recordRefund(Invoice $invoice, int $amount, User $by, string $note, ?string $mode = null): Payment
    {
        $mode ??= collect($invoice->paidByMode())->sortDesc()->keys()->first() ?? 'cash';

        $refund = Payment::create([
            'branch_id' => $invoice->branch_id,
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'kind' => Payment::KIND_REFUND,
            'mode' => $mode,
            'amount_paise' => -$amount,
            'note' => $note,
            'received_by' => $by->id,
        ]);
        $refund->allocations()->create(['invoice_id' => $invoice->id, 'amount_paise' => -$amount]);

        return $refund;
    }
}
