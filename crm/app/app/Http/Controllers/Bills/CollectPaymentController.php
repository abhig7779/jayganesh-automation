<?php

namespace App\Http\Controllers\Bills;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\PaymentService;
use App\Services\Billing\UpiQr;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Collect payment (UX-FLOWS §3.3): customer → unpaid bills oldest first → amount + mode → receipt. */
class CollectPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function create(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $customer = null;
        $matches = collect();

        if ($request->filled('customer')) {
            $customer = Customer::query()->find($request->integer('customer'));
        } elseif ($q !== '') {
            $e164 = Phone::normalize($q);
            $customer = Phone::isValidIndianMobile($e164) ? Customer::query()->where('mobile', $e164)->first() : null;
            if (! $customer) {
                $matches = Customer::query()->active()->search($q)
                    ->whereIn('id', Invoice::query()->unpaid()->whereNotNull('customer_id')->select('customer_id'))
                    ->limit(20)->get();
            }
        }

        // Customers with the biggest dues, so the page is useful before anyone types.
        $topDues = $customer || $q !== '' ? collect() : Invoice::query()->unpaid()->whereNotNull('customer_id')
            ->selectRaw('customer_id, SUM(due_paise) as due, COUNT(*) as bills, MIN(created_at) as oldest')
            ->groupBy('customer_id')->orderByDesc('due')->limit(15)->get()
            ->each(fn ($r) => $r->customer = Customer::query()->find($r->customer_id));

        $bills = $customer ? $this->payments->unpaidBills($customer) : collect();

        return view('bills.collect', [
            'q' => $q,
            'customer' => $customer,
            'matches' => $matches,
            'topDues' => $topDues,
            'bills' => $bills,
            'totalDue' => (int) $bills->sum('due_paise'),
            'upi' => UpiQr::upiId($request->user()->branch_id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'amount' => ['required', 'string', 'max:20'],
            'mode' => ['required', 'in:'.implode(',', Payment::MODES)],
            'reference' => ['nullable', 'string', 'max:80'],
            'invoices' => ['required', 'array', 'min:1'],
            'invoices.*' => ['integer'],
        ], ['invoices.required' => 'Tick at least one unpaid bill.']);

        try {
            $amount = Money::toPaise($data['amount']);
        } catch (\InvalidArgumentException) {
            return back()->withErrors(['amount' => 'Enter the amount in rupees, e.g. 3500.'])->withInput();
        }

        $customer = Customer::query()->findOrFail($data['customer_id']);
        $payment = $this->payments->collect($customer, $amount, $data['mode'], $data['reference'] ?? null, array_map('intval', $data['invoices']), $request->user());

        return redirect()->route('payments.receipt', $payment);
    }

    public function receipt(Request $request, Payment $payment): View
    {
        abort_unless($request->user()->can('payments.collect') || $request->user()->can('reports.all_staff'), 403);
        $payment->load(['allocations.invoice', 'customer', 'receiver']);
        $balance = $payment->customer_id ? PaymentService::customerDuePaise($payment->customer_id) : 0;

        $text = 'Received '.Money::format($payment->amount_paise).' ('.$payment->modeLabel().') with thanks. '
            .($balance > 0 ? 'Balance due: '.Money::format($balance).'.' : 'No balance due.');
        $wa = $payment->customer && Phone::isValidIndianMobile($payment->customer->mobile)
            ? (class_exists('App\\Services\\WhatsApp\\WhatsApp')
                ? app('App\\Services\\WhatsApp\\WhatsApp')->manualLink($payment->customer->mobile, $text)
                : 'https://wa.me/'.ltrim($payment->customer->mobile, '+').'?text='.rawurlencode($text))
            : null;

        return view('bills.receipt', ['payment' => $payment, 'balance' => $balance, 'waLink' => $wa, 'waText' => $text]);
    }
}
