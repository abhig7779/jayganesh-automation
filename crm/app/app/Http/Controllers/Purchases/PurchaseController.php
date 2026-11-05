<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Purchases\PurchaseService;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public const GST_RATES = [0, 5, 12, 18, 28];

    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $filters = [
            'supplier' => $request->integer('supplier') ?: null,
            'state' => in_array($request->query('state'), ['due', 'paid', 'cancelled'], true) ? $request->query('state') : null,
            'from' => $this->date($request->query('from')),
            'to' => $this->date($request->query('to')),
            'q' => trim((string) $request->query('q')) ?: null,
        ];

        $base = Purchase::query()
            ->when($branchId, fn ($q) => $q->where('purchases.branch_id', $branchId))
            ->when($filters['supplier'], fn ($q, $s) => $q->where('supplier_id', $s))
            ->when($filters['from'], fn ($q, $d) => $q->where('bill_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->where('bill_date', '<=', $d))
            ->when($filters['q'], fn ($q, $s) => $q->where(fn ($w) => $w->where('bill_no', 'like', "%$s%")
                ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%$s%"))
                ->orWhereHas('items', fn ($iq) => $iq->where('name', 'like', "%$s%"))));

        $live = (clone $base)->whereNull('cancelled_at');
        $totals = [
            'count' => (clone $live)->count(),
            'total_paise' => (int) (clone $live)->sum('total_paise'),
            'gst_paise' => (int) (clone $live)->sum('gst_paise'),
            'due_paise' => (int) (clone $live)->selectRaw('SUM(total_paise - paid_paise) as d')->value('d'),
        ];

        $list = match ($filters['state']) {
            'cancelled' => (clone $base)->whereNotNull('cancelled_at'),
            'due' => (clone $live)->whereColumn('paid_paise', '<', 'total_paise'),
            'paid' => (clone $live)->whereColumn('paid_paise', '>=', 'total_paise'),
            default => $base,
        };

        return view('purchases.index', [
            'filters' => $filters,
            'totals' => $totals,
            'purchases' => $list->with('supplier:id,name')->withCount('items')
                ->orderByDesc('bill_date')->orderByDesc('id')->paginate(30)->withQueryString(),
            'suppliers' => $this->suppliers($request),
        ]);
    }

    public function create(Request $request)
    {
        // After a failed save, rebuild the lines with their names for the screen.
        $oldItems = collect(old('items', []))->filter(fn ($l) => ! empty($l['product_id']));
        $names = Product::query()->whereIn('id', $oldItems->pluck('product_id'))->pluck('name', 'id');

        return view('purchases.create', [
            'suppliers' => $this->suppliers($request),
            'selectedSupplier' => $request->integer('supplier') ?: null,
            'gstRates' => self::GST_RATES,
            'modes' => SupplierPayment::MODES,
            'today' => now('Asia/Kolkata')->toDateString(),
            'oldItems' => $oldItems->map(fn ($l) => [
                'product_id' => (int) $l['product_id'],
                'name' => $names[(int) $l['product_id']] ?? 'Item',
                'qty' => (string) ($l['qty'] ?? 1),
                'cost' => (string) ($l['cost'] ?? ''),
                'gst_rate' => (string) ($l['gst_rate'] ?? 18),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'supplier_id' => ['nullable', 'required_without:new_supplier', 'integer', Rule::exists('suppliers', 'id')],
            'new_supplier' => ['nullable', 'required_without:supplier_id', 'string', 'max:120'],
            'new_supplier_mobile' => ['nullable', 'string', 'max:20'],
            'bill_no' => ['nullable', 'string', 'max:60'],
            'bill_date' => ['required', 'date', 'before_or_equal:'.now('Asia/Kolkata')->addDay()->toDateString()],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:8192'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.cost' => ['required', 'regex:/^\s*(?:₹)?\s*[\d,]+(\.\d{1,2})?\s*$/'],
            'items.*.gst_rate' => ['required', Rule::in(self::GST_RATES)],
            'paid' => ['nullable', 'regex:/^\s*(?:₹)?\s*[\d,]+(\.\d{1,2})?\s*$/'],
            'paid_mode' => ['nullable', Rule::in(array_keys(SupplierPayment::MODES))],
        ], [
            'supplier_id.required_without' => 'Pick the supplier, or type a new supplier name.',
            'items.required' => 'Add at least one item.',
            'items.*.cost.required' => 'Type the cost price for every item.',
            'items.*.cost.regex' => 'Cost price must be an amount, like 1,250 or 1250.50.',
            'paid.regex' => 'Paid now must be an amount, like 5,000.',
            'bill_date.before_or_equal' => 'The bill date can’t be in the future.',
        ]);

        $supplier = $this->resolveSupplier($data, $request);

        if (! empty($data['bill_no']) && $supplier->purchases()->live()->where('bill_no', trim($data['bill_no']))->exists()) {
            throw ValidationException::withMessages(['bill_no' => 'Bill '.trim($data['bill_no']).' from '.$supplier->name.' is already entered. Open the purchases list to check it.']);
        }

        $items = array_map(fn ($l) => [
            'product_id' => (int) $l['product_id'],
            'qty' => (int) $l['qty'],
            'unit_cost_paise' => Money::toPaise($l['cost']),
            'gst_rate' => $l['gst_rate'],
        ], array_values($data['items']));

        $total = array_sum(array_map(fn ($l) => $l['qty'] * $l['unit_cost_paise'] + Money::percent($l['qty'] * $l['unit_cost_paise'], $l['gst_rate']), $items));
        $paid = isset($data['paid']) && $data['paid'] !== '' ? Money::toPaise($data['paid']) : 0;
        if ($paid > $total) {
            throw ValidationException::withMessages(['paid' => 'Paid now is more than the bill total of '.Money::format($total).'.']);
        }

        $photo = $request->hasFile('photo')
            ? $request->file('photo')->store('purchases/'.now()->format('Y/m'), 'local')
            : null;

        $purchase = $this->purchases->create([
            'supplier_id' => $supplier->id,
            'bill_no' => isset($data['bill_no']) ? trim($data['bill_no']) : null,
            'bill_date' => $data['bill_date'],
            'note' => $data['note'] ?? null,
            'photo_path' => $photo,
            'items' => $items,
            'paid_paise' => $paid,
            'paid_mode' => $data['paid_mode'] ?? 'cash',
        ], $user);

        $qty = array_sum(array_column($items, 'qty'));

        return redirect()->route('purchases.show', $purchase)
            ->with('status', "Stock in saved: {$qty} ".($qty === 1 ? 'piece' : 'pieces').' from '.$supplier->name.', '.Money::format($purchase->total_paise).'.');
    }

    public function show(Request $request, Purchase $purchase)
    {
        $this->authorizeBranch($purchase);
        $purchase->load(['supplier', 'items.product:id,name,sku,on_hand,unit', 'creator:id,name', 'canceller:id,name', 'payments' => fn ($q) => $q->live()]);

        return view('purchases.show', [
            'purchase' => $purchase,
            'canCancel' => $this->canCancel($request),
            'reasons' => Purchase::CANCEL_REASONS,
        ]);
    }

    public function photo(Purchase $purchase)
    {
        $this->authorizeBranch($purchase);
        abort_unless($purchase->photo_path && Storage::disk('local')->exists($purchase->photo_path), 404);

        return Storage::disk('local')->response($purchase->photo_path);
    }

    /** Owner/Admin only. Stock goes back out; the bill keeps its number with a CANCELLED stamp. */
    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizeBranch($purchase);
        abort_unless($this->canCancel($request), 403);

        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(Purchase::CANCEL_REASONS))],
            'reason_text' => ['nullable', 'required_if:reason,other', 'string', 'max:150'],
        ], ['reason_text.required_if' => 'Write why you are cancelling this purchase.']);

        $reason = Purchase::CANCEL_REASONS[$data['reason']].(! empty($data['reason_text']) ? ': '.$data['reason_text'] : '');
        $this->purchases->cancel($purchase, $reason, $request->user());

        return redirect()->route('purchases.show', $purchase)->with('status', 'Purchase '.$purchase->ref().' cancelled. The stock went back out.');
    }

    /** The product list for the instant search on New purchase (stock items only, with cost). */
    public function products(): JsonResponse
    {
        $list = Product::query()->active()->where('type', Product::TYPE_PRODUCT)->with('brand:id,name')
            ->orderByDesc('sold_count')->orderBy('name')->limit(5000)->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'brand' => $p->brand?->name,
                'aliases' => $p->aliases,
                'cost_paise' => $p->cost_paise,
                'gst_rate' => (float) $p->gst_rate,
                'on_hand' => $p->on_hand,
                'unit' => $p->unit,
                'sold_count' => $p->sold_count,
            ]);

        return response()->json(['products' => $list]);
    }

    private function resolveSupplier(array $data, Request $request): Supplier
    {
        if (! empty($data['supplier_id'])) {
            $supplier = Supplier::query()->findOrFail($data['supplier_id']);
            $branchId = $request->user()->branch_id;
            abort_if($branchId && $supplier->branch_id !== $branchId, 404);

            return $supplier;
        }

        $name = trim($data['new_supplier']);
        $existing = Supplier::query()->when($request->user()->branch_id, fn ($q, $b) => $q->where('branch_id', $b))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

        return $existing ?? Supplier::query()->create([
            'branch_id' => $request->user()->branch_id ?? Branch::query()->value('id'),
            'name' => $name,
            'mobile' => Phone::normalize($data['new_supplier_mobile'] ?? null),
            'created_by' => $request->user()->id,
        ]);
    }

    private function suppliers(Request $request)
    {
        return Supplier::query()->active()
            ->when($request->user()->branch_id, fn ($q, $b) => $q->where('branch_id', $b))
            ->orderBy('name')->get(['id', 'name', 'mobile']);
    }

    private function canCancel(Request $request): bool
    {
        return $request->user()->hasAnyRole(['owner', 'admin']);
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function authorizeBranch(Purchase $purchase): void
    {
        $branchId = request()->user()->branch_id;
        abort_if($branchId && $purchase->branch_id !== $branchId, 404);
    }
}
