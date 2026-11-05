<?php

namespace App\Http\Controllers\Bills;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bills\StoreBillRequest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceSeries;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Billing\BillShare;
use App\Services\Billing\GstCalculator;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\NumberSeries;
use App\Services\Billing\PaymentService;
use App\Services\Billing\UpiQr;
use App\Services\Pdf\InvoicePdf;
use App\Services\WhatsApp\WhatsApp;
use App\Support\CarModels;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RegNo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BillController extends Controller
{
    public function __construct(private readonly InvoiceService $bills) {}

    /** New bill: the PC one-page POS and the phone 3-step flow share this page and one endpoint. */
    public function create(Request $request): View
    {
        Gate::authorize('create', Invoice::class);
        $user = $request->user();
        $calc = GstCalculator::fromSettings($user->branch_id);

        return view('bills.create', [
            'config' => [
                'urls' => [
                    'store' => route('bills.store'),
                    'products' => route('bills.product-index'),
                    'lookup' => url('/api/v1/customers/lookup'),
                    'context' => route('bills.customer-context'),
                    'modelSuggestions' => route('bills.model-suggestions'),
                    'draft' => route('bills.draft'),
                    'qr' => route('bills.upi-qr'),
                    'home' => route('dashboard'),
                ],
                'prefill' => $this->prefill($request),
                'can' => [
                    'editPrice' => $user->can('prices.manage'),
                    'customItem' => InvoiceService::canAddCustomItem($user),
                ],
                'limits' => [
                    'discountPercent' => InvoiceService::discountLimitPercent($user),
                    'duePaise' => InvoiceService::dueLimitPaise($user, $user->branch_id),
                ],
                'gst' => ['registered' => $calc->isRegistered(), 'state' => $calc->shopStateCode()],
                'upi' => ['id' => UpiQr::upiId($user->branch_id), 'name' => Setting::get('billing.upi_name', null, $user->branch_id)],
                'nextNumber' => $this->nextNumberPreview($user),
                'userId' => $user->id,
                'sources' => Customer::SOURCE_LABELS,
                'carModels' => CarModels::list(),
                'defaultGst' => (float) Setting::get('tax.default_gst_rate', 18),
            ],
        ]);
    }

    public function store(StoreBillRequest $request): JsonResponse
    {
        $invoice = $this->bills->create($request->validated(), $request->user());

        return response()->json([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'total_paise' => $invoice->total_paise,
            'redirect' => route('bills.done', $invoice),
        ], $invoice->wasRecentlyCreated ? 201 : 200);
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);
        $user = $request->user();
        $f = $this->filters($request);

        $base = Invoice::query()->visibleTo($user)
            ->whereBetween('created_at', [$f['from']->copy()->startOfDay()->utc(), $f['to']->copy()->endOfDay()->utc()])
            ->when($f['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($f['unpaid'], fn ($q) => $q->unpaid())
            ->when($f['staff'], fn ($q, $s) => $q->where('created_by', $s))
            ->when($f['mode'], fn ($q, $m) => $q->whereHas('allocations.payment', fn ($p) => $p->where('mode', $m)->where('kind', Payment::KIND_RECEIPT)))
            ->when($f['q'], function ($q, $term) {
                $digits = preg_replace('/\D+/', '', $term);
                $q->where(function ($w) use ($term, $digits) {
                    $w->where('number', 'like', '%'.str_replace(['%', '_'], '', $term).'%')
                        ->orWhere('customer_name', 'like', '%'.str_replace(['%', '_'], '', $term).'%')
                        ->orWhere('car_label', 'like', '%'.str_replace(['%', '_'], '', RegNo::normalize($term) ?? '').'%');
                    if (strlen($digits) >= 4) {
                        $w->orWhere('customer_mobile', 'like', '%'.substr($digits, -10).'%');
                    }
                });
            });

        $live = (clone $base)->notCancelled();
        $totals = [
            'count' => (clone $base)->count(),
            'cancelled' => (clone $base)->where('status', Invoice::STATUS_CANCELLED)->count(),
            'total' => (int) (clone $live)->sum('total_paise'),
            'paid' => (int) (clone $live)->sum('paid_paise'),
            'due' => (int) (clone $live)->sum('due_paise'),
        ];

        $bills = $base->with(['creator:id,name', 'allocations.payment'])
            ->latest('created_at')->latest('id')
            ->paginate(50)->withQueryString();

        return view('bills.index', [
            'bills' => $bills,
            'f' => $f,
            'totals' => $totals,
            'staffList' => $user->can('reports.all_staff') ? User::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'waStatus' => $this->whatsappStatuses($bills->pluck('id')->all()),
        ]);
    }

    public function show(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['items.product', 'customer', 'car', 'creator', 'canceller', 'allocations.payment.receiver', 'creditNotes.items']);

        return view('bills.show', [
            'invoice' => $invoice,
            'hsn' => GstCalculator::hsnSummary($invoice->items->map->toArray()),
            'waLink' => BillShare::whatsappLink($invoice),
            'customerDue' => $invoice->customer_id ? PaymentService::customerDuePaise($invoice->customer_id) : 0,
        ]);
    }

    /** "Bill saved" screen after New bill (UX-FLOWS §3.1 Bill done). */
    public function done(Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);
        $invoice->load(['items', 'allocations.payment', 'car']);

        return view('bills.done', [
            'invoice' => $invoice,
            'waLink' => BillShare::whatsappLink($invoice),
            'pdfUrl' => BillShare::pdfUrl($invoice),
            'printFormat' => Setting::get('billing.print_format', 'a4', $invoice->branch_id),
        ]);
    }

    /** Small JSON for the live WhatsApp status on Bill done. Reads the WhatsApp module's log once merged. */
    public function status(Invoice $invoice): JsonResponse
    {
        Gate::authorize('view', $invoice);
        $status = $this->whatsappStatuses([$invoice->id])[$invoice->id] ?? null;

        return response()->json([
            'number' => $invoice->number,
            'send_whatsapp' => $invoice->send_whatsapp,
            'whatsapp' => $status,   // null until the WhatsApp module records something
            'automatic' => $this->whatsappIsAutomatic(),
            'manual_link' => BillShare::whatsappLink($invoice),
        ]);
    }

    public function print(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);
        $format = $request->query('format', Setting::get('billing.print_format', 'a4', $invoice->branch_id));

        if ($format === 'thermal') {
            $invoice->load(['items', 'allocations.payment', 'creator']);

            return response()->view('bills.thermal', [
                'invoice' => $invoice,
                'shop' => InvoicePdf::data($invoice)['shop'],
                'hsn' => GstCalculator::hsnSummary($invoice->items->map->toArray()),
            ]);
        }

        return response()->file(InvoicePdf::render($invoice), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->fileName().'"',
        ]);
    }

    public function pdf(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response()->file(InvoicePdf::render($invoice), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$invoice->fileName().'"',
        ]);
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('cancel', $invoice);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:200'],
        ], ['reason.required' => 'Pick a reason for cancelling.']);
        $reason = trim($data['reason'].(filled($data['note'] ?? null) ? ': '.$data['note'] : ''));

        $this->bills->cancel($invoice, $reason, $request->user());

        return redirect()->route('bills.show', $invoice)->with('status', "Bill {$invoice->number} cancelled. Stock is back and the money is marked as refunded.");
    }

    public function returnItems(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('returnItems', $invoice);
        $data = $request->validate([
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:200'],
            'refund_mode' => ['nullable', 'in:'.implode(',', Payment::MODES)],
        ]);

        $note = $this->bills->returnItems($invoice, $data['qty'], $data['reason'], $request->user(), $data['refund_mode'] ?? 'cash');

        $msg = "Credit note {$note->number} for ".Money::format($note->total_paise).' saved.';
        if ($note->refund_paise > 0) {
            $msg .= ' Give back '.Money::format($note->refund_paise).'.';
        }

        return redirect()->route('bills.show', $invoice)->with('status', $msg);
    }

    /** Extra facts the bill screen shows after the mobile lookup: due, last visit, open quote, what this car model buys. */
    public function customerContext(Request $request): JsonResponse
    {
        Gate::authorize('create', Invoice::class);
        $request->validate(['customer_id' => ['required_without:mobile', 'nullable', 'integer'], 'mobile' => ['nullable', 'string', 'max:20']]);

        $customer = $request->filled('customer_id')
            ? Customer::query()->find($request->integer('customer_id'))
            : Customer::query()->where('mobile', Phone::normalize((string) $request->query('mobile')))->first();
        if (! $customer) {
            return response()->json(['message' => 'No customer with this number yet.'], 404);
        }
        $customer->load('cars');

        $bills = Invoice::query()->where('customer_id', $customer->id)->notCancelled();
        $last = (clone $bills)->latest('created_at')->with('items:id,invoice_id,name')->first();

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
                'mobile_pretty' => Phone::pretty($customer->mobile),
                'area' => $customer->area,
                'whatsapp_opt_in' => $customer->whatsapp_opt_in,
                'cars' => $customer->cars->map(fn ($c) => [
                    'id' => $c->id, 'make' => $c->make, 'model' => $c->model,
                    'reg_no' => $c->reg_no, 'reg_pretty' => RegNo::pretty($c->reg_no),
                ])->values(),
            ],
            'due_paise' => PaymentService::customerDuePaise($customer->id),
            'bills_count' => (clone $bills)->count(),
            'last_visit' => $customer->last_visit_at?->timezone('Asia/Kolkata')->format('d M Y'),
            'last_items' => $last?->items->pluck('name')->take(2)->implode(', '),
            'open_quote' => $this->openQuote($customer->id),
            'car_suggestions' => $customer->cars->mapWithKeys(fn ($c) => [$c->id => $this->modelSuggestions($c->model)]),
        ]);
    }

    /** What owners of this car model usually buy (by units sold on their bills). */
    public function modelSuggestionsJson(Request $request): JsonResponse
    {
        Gate::authorize('create', Invoice::class);

        return response()->json(['products' => $this->modelSuggestions((string) $request->query('model'))]);
    }

    public function upiQr(Request $request): Response
    {
        abort_unless($request->user()->can('invoices.create') || $request->user()->can('payments.collect'), 403);
        $amount = max(0, min(10_000_000_00, (int) $request->query('amount_paise')));
        $svg = UpiQr::svg($amount, $request->query('note'), (int) $request->query('size', 220), $request->user()->branch_id);
        if (! $svg) {
            return response('No UPI ID set. Add it in Settings → Tax & billing.', 404);
        }

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'private, max-age=600']);
    }

    // ---------------------------------------------------------------------------------------

    /** @return int[] product ids */
    private function modelSuggestions(?string $model): array
    {
        if (blank($model)) {
            return [];
        }

        return InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('customer_cars', 'customer_cars.id', '=', 'invoices.customer_car_id')
            ->where('customer_cars.model', $model)
            ->where('invoices.status', '!=', Invoice::STATUS_CANCELLED)
            ->whereNotNull('invoice_items.product_id')
            ->groupBy('invoice_items.product_id')
            ->orderByRaw('SUM(invoice_items.qty) DESC')
            ->limit(6)
            ->pluck('invoice_items.product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** Open quotation for the customer, once the quotations module is merged (guarded). */
    private function openQuote(int $customerId): ?array
    {
        if (! Schema::hasTable('quotations') || ! Schema::hasColumn('quotations', 'customer_id')) {
            return null;
        }
        $cols = Schema::getColumnListing('quotations');
        $q = DB::table('quotations')->where('customer_id', $customerId)
            ->when(in_array('status', $cols, true), fn ($x) => $x->whereIn('status', ['open', 'sent', 'visit_booked', 'draft']))
            ->latest('id')->first();
        if (! $q) {
            return null;
        }
        $total = $q->total_paise ?? null;

        return [
            'id' => $q->id,
            'number' => $q->number ?? null,
            'total_paise' => $total !== null ? (int) $total : null,
            'date' => isset($q->created_at) ? Carbon::parse($q->created_at)->timezone('Asia/Kolkata')->format('j M') : null,
            'url' => Route::has('quotations.convert') ? route('quotations.convert', $q->id) : (Route::has('quotations.show') ? route('quotations.show', $q->id) : null),
        ];
    }

    /**
     * Prefill from a quotation (session bill_prefill, crm/CONTRACTS.md hand-off 3) or from
     * ?customer=ID / ?product=ID[,ID].
     */
    private function prefill(Request $request): array
    {
        $p = $request->session()->pull('bill_prefill');
        $out = ['customer_id' => null, 'customer_car_id' => null, 'quotation_id' => null, 'items' => []];

        if (is_array($p)) {
            $out['customer_id'] = isset($p['customer_id']) ? (int) $p['customer_id'] : null;
            $out['customer_car_id'] = isset($p['customer_car_id']) ? (int) $p['customer_car_id'] : null;
            $out['quotation_id'] = isset($p['quotation_id']) ? (int) $p['quotation_id'] : null;
            foreach ((array) ($p['items'] ?? []) as $i) {
                if (! empty($i['product_id'])) {
                    $out['items'][] = [
                        'product_id' => (int) $i['product_id'],
                        'qty' => max(1, (int) ($i['qty'] ?? 1)),
                        'unit_price_paise' => isset($i['unit_price_paise']) ? (int) $i['unit_price_paise'] : null,
                    ];
                }
            }
        }
        if ($request->filled('customer')) {
            $out['customer_id'] = $request->integer('customer');
        }
        if ($request->filled('product')) {
            foreach (array_filter(array_map('intval', explode(',', (string) $request->query('product')))) as $id) {
                $out['items'][] = ['product_id' => $id, 'qty' => 1, 'unit_price_paise' => null];
            }
        }

        // Only products that still exist and are for sale.
        $valid = Product::query()->active()->whereIn('id', array_column($out['items'], 'product_id'))->pluck('id')->all();
        $out['items'] = array_values(array_filter($out['items'], fn ($i) => in_array($i['product_id'], $valid, true)));

        $out['customer'] = null;
        if ($out['customer_id'] && ($c = Customer::query()->find($out['customer_id']))) {
            $out['customer'] = ['id' => $c->id, 'mobile' => $c->mobile];
        } else {
            $out['customer_id'] = null;
        }
        $out['active'] = $out['customer_id'] || $out['items'] !== [];

        return $out;
    }

    private function nextNumberPreview(User $user): string
    {
        $fy = NumberSeries::financialYear(now());
        $series = InvoiceSeries::query()->where(['branch_id' => $user->branch_id, 'financial_year' => $fy, 'type' => NumberSeries::TYPE_INVOICE])->first();
        $prefix = $series->prefix ?? (Setting::get('billing.invoice_prefix', null, $user->branch_id) ?: 'JG');

        return sprintf('%s-%s-%04d', $prefix, $fy, $series->next_number ?? 1);
    }

    private function filters(Request $request): array
    {
        $tz = 'Asia/Kolkata';
        $parse = function (?string $v, Carbon $fallback) use ($tz) {
            try {
                return $v ? Carbon::createFromFormat('Y-m-d', $v, $tz)->startOfDay() : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        };
        $today = now($tz)->startOfDay();
        $range = $request->query('range');
        [$from, $to] = match ($range) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'week' => [$today->copy()->subDays(6), $today],
            'month' => [$today->copy()->startOfMonth(), $today],
            '30' => [$today->copy()->subDays(29), $today],
            default => [$parse($request->query('from'), $today), $parse($request->query('to'), $today)],
        };
        if ($request->boolean('unpaid') && ! $request->filled('from') && ! $range) {
            $from = $today->copy()->subYears(5); // "Unpaid" alone means every open due
        }
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from, 'to' => $to, 'range' => $range,
            'status' => in_array($request->query('status'), array_keys(Invoice::STATUS_LABELS), true) ? $request->query('status') : null,
            'mode' => in_array($request->query('mode'), Payment::MODES, true) ? $request->query('mode') : null,
            'staff' => $request->user()->can('reports.all_staff') ? ($request->integer('staff') ?: null) : null,
            'unpaid' => $request->boolean('unpaid'),
            'q' => trim((string) $request->query('q')) ?: null,
        ];
    }

    /** invoice id => latest e-bill status from message_log ('queued', 'sent', 'delivered', 'read', 'failed'). */
    private function whatsappStatuses(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return MessageLog::query()
            ->where('related_type', (new Invoice)->getMorphClass())
            ->whereIn('related_id', $invoiceIds)
            ->where('template', (string) Setting::get('whatsapp.template_invoice'))
            ->orderBy('id')
            ->get(['related_id', 'status'])
            ->mapWithKeys(fn ($r) => [$r->related_id => $r->status])
            ->all();
    }

    private function whatsappIsAutomatic(): bool
    {
        return app(WhatsApp::class)->isAutomatic();
    }
}
