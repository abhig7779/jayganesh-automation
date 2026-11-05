<?php

namespace App\Services\Billing;

use App\Events\PaymentCollected;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\ClientKey;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Collect payment (UX-FLOWS §3.3): one amount spread over the customer's unpaid bills, oldest first. */
class PaymentService
{
    /** The customer's unpaid bills, oldest first. */
    public function unpaidBills(Customer $customer): Collection
    {
        return Invoice::query()->where('customer_id', $customer->id)->unpaid()
            ->orderBy('created_at')->orderBy('id')->get();
    }

    public static function customerDuePaise(int $customerId): int
    {
        return (int) Invoice::query()->where('customer_id', $customerId)->unpaid()->sum('due_paise');
    }

    /**
     * Oldest-first split of $amount over bills (each with id + due_paise). Pure, for the preview.
     *
     * @return array<int,int> invoice id => paise applied
     */
    public static function allocateOldestFirst(int $amount, iterable $bills): array
    {
        $out = [];
        foreach ($bills as $bill) {
            if ($amount <= 0) {
                break;
            }
            $take = min($amount, (int) $bill->due_paise);
            if ($take > 0) {
                $out[$bill->id] = $take;
                $amount -= $take;
            }
        }

        return $out;
    }

    /**
     * @param  int[]|null  $invoiceIds  bills staff left ticked (null = all unpaid bills)
     * @param  ?string  $clientKey  the form's idempotency key: a double submit returns the first payment
     */
    public function collect(Customer $customer, int $amount, string $mode, ?string $reference, ?array $invoiceIds, User $by, ?string $clientKey = null): Payment
    {
        if (! in_array($mode, Payment::MODES, true)) {
            throw ValidationException::withMessages(['mode' => 'Pick Cash, UPI, Card or Bank.']);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter the amount received.']);
        }

        $branchId = $by->branch_id;

        return ClientKey::once(Payment::class, $branchId, $clientKey, fn () => DB::transaction(function () use ($customer, $amount, $mode, $reference, $invoiceIds, $by, $clientKey) {
            $bills = Invoice::query()->where('customer_id', $customer->id)->unpaid()
                ->when($invoiceIds !== null, fn ($q) => $q->whereIn('id', $invoiceIds))
                ->orderBy('created_at')->orderBy('id')
                ->lockForUpdate()->get();

            if ($bills->isEmpty()) {
                throw ValidationException::withMessages(['invoices' => 'Tick at least one unpaid bill.']);
            }
            $due = (int) $bills->sum('due_paise');
            if ($amount > $due) {
                throw ValidationException::withMessages(['amount' => 'That is more than the due on the ticked bills ('.Money::format($due).').']);
            }

            $split = self::allocateOldestFirst($amount, $bills);
            $payment = Payment::forceCreate([
                'branch_id' => $by->branch_id ?? $bills->first()->branch_id,
                'invoice_id' => count($split) === 1 ? array_key_first($split) : null,
                'customer_id' => $customer->id,
                'kind' => Payment::KIND_RECEIPT,
                'mode' => $mode,
                'amount_paise' => $amount,
                'reference' => $reference ? mb_substr($reference, 0, 80) : null,
                'note' => 'Due collected',
                'received_by' => $by->id,
                'client_key' => $clientKey ?: null,
            ]);

            foreach ($bills as $bill) {
                $take = $split[$bill->id] ?? 0;
                if ($take <= 0) {
                    continue;
                }
                $payment->allocations()->forceCreate(['invoice_id' => $bill->id, 'amount_paise' => $take]);
                $paid = $bill->paid_paise + $take;
                $left = $bill->due_paise - $take;
                $bill->forceFill(['paid_paise' => $paid, 'due_paise' => $left, 'status' => InvoiceService::statusFor($paid, $left)])->save();
            }

            $balance = self::customerDuePaise($customer->id);
            activity('bills')->performedOn($payment)->causedBy($by)->event('collected')
                ->withProperties(['customer_id' => $customer->id, 'amount_paise' => $amount, 'mode' => $mode, 'bills' => $split, 'balance_paise' => $balance])
                ->log('Due collected '.Money::format($amount));

            event(new PaymentCollected($payment, $balance));

            return $payment->load('allocations.invoice');
        }));
    }
}
