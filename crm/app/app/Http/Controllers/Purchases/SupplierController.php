<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Purchases\PurchaseService;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SupplierController extends Controller
{
    private const MONEY = '/^\s*-?\s*(?:₹)?\s*[\d,]+(\.\d{1,2})?\s*$/';

    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q')) ?: null;
        $suppliers = Supplier::query()->withBalance()
            ->when($request->user()->branch_id, fn ($s, $b) => $s->where('branch_id', $b))
            ->when($q, fn ($s) => $s->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('mobile', 'like', '%'.preg_replace('/\D/', '', $q).'%')->orWhere('gstin', 'like', "%$q%")))
            ->withMax(['purchases as last_bill_date' => fn ($p) => $p->whereNull('cancelled_at')], 'bill_date')
            ->orderByDesc('is_active')->orderBy('name')->get();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'q' => $q,
            'owed' => $suppliers->sum(fn (Supplier $s) => max(0, $s->balancePaise())),
        ]);
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => new Supplier]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($this->validated($request) + [
            'branch_id' => $request->user()->branch_id ?? Branch::query()->value('id'),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier '.$supplier->name.' added.');
    }

    public function edit(Supplier $supplier)
    {
        $this->authorizeBranch($supplier);

        return view('suppliers.form', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorizeBranch($supplier);
        $supplier->fill($this->validated($request, $supplier))->save();
        $this->purchases->reallocate($supplier); // the opening balance may have changed

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier details saved.');
    }

    /** Supplier ledger: bills and payments in date order with a running balance. */
    public function show(Supplier $supplier)
    {
        $this->authorizeBranch($supplier);

        $bills = $supplier->purchases()->with('creator:id,name')->withCount('items')->get();
        $payments = $supplier->payments()->with(['creator:id,name', 'purchase:id,bill_no'])->get();

        $rows = collect();
        foreach ($bills as $b) {
            $rows->push(['date' => $b->bill_date, 'sort' => $b->created_at, 'kind' => 'bill', 'model' => $b,
                'debit' => $b->isCancelled() ? 0 : $b->total_paise, 'credit' => 0, 'void' => $b->isCancelled()]);
        }
        foreach ($payments as $p) {
            $rows->push(['date' => $p->date, 'sort' => $p->created_at, 'kind' => 'payment', 'model' => $p,
                'debit' => 0, 'credit' => $p->cancelled_at ? 0 : $p->amount_paise, 'void' => $p->cancelled_at !== null]);
        }
        // Same day: the bill first, then what was paid against it.
        $rows = $rows->sortBy(fn ($r) => $r['date']->format('Y-m-d').($r['kind'] === 'bill' ? '0' : '1').$r['sort']->format('Y-m-d H:i:s.u'))->values();

        $running = (int) $supplier->opening_balance_paise;
        $rows = $rows->map(function ($r) use (&$running) {
            $running += $r['debit'] - $r['credit'];
            $r['balance'] = $running;

            return $r;
        })->reverse()->values(); // newest first on screen

        return view('suppliers.show', [
            'supplier' => $supplier,
            'rows' => $rows,
            'balance' => $running,
            'billed' => (int) $bills->reject->isCancelled()->sum('total_paise'),
            'paid' => (int) $payments->whereNull('cancelled_at')->sum('amount_paise'),
            'openBills' => $bills->reject->isCancelled()->filter(fn ($b) => $b->duePaise() > 0)->sortBy('bill_date')->values(),
            'modes' => SupplierPayment::MODES,
            'today' => now('Asia/Kolkata')->toDateString(),
        ]);
    }

    public function pay(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorizeBranch($supplier);
        $data = $request->validate([
            'amount' => ['required', 'regex:/^\s*(?:₹)?\s*[\d,]+(\.\d{1,2})?\s*$/'],
            'date' => ['required', 'date', 'before_or_equal:'.now('Asia/Kolkata')->addDay()->toDateString()],
            'mode' => ['required', Rule::in(array_keys(SupplierPayment::MODES))],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:300'],
            'purchase_id' => ['nullable', 'integer'],
        ], [
            'amount.required' => 'Type how much you paid.',
            'amount.regex' => 'Amount must be like 5,000 or 5000.50.',
        ]);

        $amount = Money::toPaise($data['amount']);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be more than ₹0.']);
        }

        try {
            $this->purchases->pay($supplier, [
                'amount_paise' => $amount, 'date' => $data['date'], 'mode' => $data['mode'],
                'reference' => $data['reference'] ?? null, 'note' => $data['note'] ?? null,
                'purchase_id' => $data['purchase_id'] ?? null,
            ], $request->user());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['purchase_id' => $e->getMessage()]);
        }

        $balance = $supplier->refresh()->balancePaise();

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Payment of '.Money::format($amount).' to '.$supplier->name.' saved. '
            .($balance > 0 ? 'Still to pay: '.Money::format($balance).'.' : ($balance < 0 ? 'Advance with them: '.Money::format(-$balance).'.' : 'All clear.')));
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'gstin' => ['nullable', 'string', 'regex:/^\s*[0-9]{2}[A-Za-z0-9]{13}\s*$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'regex:'.self::MONEY],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'gstin.regex' => 'GSTIN is 15 characters, like 27ABCDE1234F1Z5.',
            'opening_balance.regex' => 'Opening balance must be an amount, like 12,500.',
        ]);

        $mobile = Phone::normalize($data['mobile'] ?? null);
        if ($mobile !== null && ! Phone::isValidIndianMobile($mobile) && strlen(preg_replace('/\D/', '', $mobile)) < 10) {
            throw ValidationException::withMessages(['mobile' => 'Mobile number needs 10 digits.']);
        }

        $opening = trim((string) ($data['opening_balance'] ?? ''));

        return [
            'name' => trim($data['name']),
            'mobile' => $mobile,
            'gstin' => isset($data['gstin']) ? strtoupper(trim($data['gstin'])) : null,
            'address' => $data['address'] ?? null,
            'opening_balance_paise' => $opening === '' ? 0 : Money::toPaise(str_replace(' ', '', $opening)),
            'notes' => $data['notes'] ?? null,
            'is_active' => $supplier ? $request->boolean('is_active') : true,
        ];
    }

    private function authorizeBranch(Supplier $supplier): void
    {
        $branchId = request()->user()->branch_id;
        abort_if($branchId && $supplier->branch_id !== $branchId, 404);
    }
}
