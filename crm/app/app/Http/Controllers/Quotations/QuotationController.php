<?php

namespace App\Http\Controllers\Quotations;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Pdf\QuotationPdf;
use App\Services\Quotations\FollowUpMessages;
use App\Services\Quotations\LeadService;
use App\Services\Quotations\LeakReport;
use App\Services\Quotations\QuotationService;
use App\Services\Quotations\QuoteTotals;
use App\Support\Dashboard\FollowUps;
use App\Support\Money;
use App\Support\Phone;
use App\Support\Quotations\ShopClock;
use App\Support\RegNo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    public function __construct(private readonly QuotationService $quotations) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');
        $filter = $request->query('filter');
        $q = trim((string) $request->query('q', ''));

        $base = Quotation::query()->visibleTo($user)->whereNull('revised_to_id');
        $counts = (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $quotes = (clone $base)
            ->with(['customer', 'car', 'items', 'assignee:id,name', 'followUps' => fn ($f) => $f->pending()->orderBy('due_on')])
            ->when(in_array($status, Quotation::STATUSES, true), fn ($w) => $w->where('status', $status))
            ->when($filter === 'overdue', fn ($w) => $w->overdue())
            ->when($filter === 'mine', fn ($w) => $w->where('assigned_to', $user->id))
            ->when($filter === 'week', fn ($w) => $w->where('created_at', '>=', ShopClock::now()->startOfWeek()->utc()))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', '%'.str_replace(['%', '_'], '', $q).'%')
                ->orWhereHas('customer', fn ($c) => $c->search($q))))
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('quotations.index', [
            'quotes' => $quotes,
            'counts' => $counts,
            'status' => $status,
            'filter' => $filter,
            'search' => $q,
            'overdueCount' => (clone $base)->overdue()->count(),
            'report' => Quotation::seesAll($user) ? app(LeakReport::class)->forMonth() : null,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $prefill = null;

        if ($from = Quotation::query()->with('items', 'customer.cars', 'car')->find($request->query('from'))) {
            $this->authorizeQuote($user, $from);
            $prefill = [
                'revised_from_id' => $from->id,
                'revised_from_number' => $from->number,
                'mobile' => substr((string) $from->customer->mobile, 3),
                'customer' => $this->customerPayload($from->customer),
                'customer_car_id' => $from->customer_car_id,
                'items' => $from->items->map(fn ($i) => [
                    'product_id' => $i->product_id, 'name' => $i->name, 'qty' => $i->qty,
                    'unit_price_paise' => $i->unit_price_paise, 'list_price_paise' => $i->unit_price_paise,
                    'mrp_paise' => $i->mrp_paise, 'gst_rate' => (float) $i->gst_rate,
                ])->values()->all(),
                'notes' => $from->notes,
            ];
        } elseif ($lead = Lead::query()->with('customer.cars', 'product')->find($request->query('lead'))) {
            $prefill = [
                'lead_id' => $lead->id,
                'mobile' => substr((string) $lead->mobile, 3),
                'name' => $lead->name,
                'customer' => $lead->customer ? $this->customerPayload($lead->customer) : null,
                'items' => $lead->product ? [[
                    'product_id' => $lead->product->id, 'name' => $lead->product->name, 'qty' => 1,
                    'unit_price_paise' => $lead->price_told_paise ?? $lead->product->price_paise,
                    'list_price_paise' => $lead->product->price_paise,
                    'mrp_paise' => $lead->product->mrp_paise, 'gst_rate' => (float) $lead->product->gst_rate,
                ]] : [],
                'notes' => $lead->interest,
            ];
        } elseif ($request->filled('mobile')) {
            $prefill = ['mobile' => preg_replace('/\D/', '', (string) $request->query('mobile'))];
        }

        return view('quotations.create', [
            'prefill' => $prefill,
            'canEditPrice' => QuotationService::canEditPrice($user),
            'validDays' => (int) Setting::get('billing.quotation_valid_days', null, $user->branch_id),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required_without:customer_id', 'nullable', 'string', 'max:20'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'customer_car_id' => ['nullable', 'integer'],
            'car.make' => ['nullable', 'string', 'max:60'],
            'car.model' => ['nullable', 'string', 'max:80'],
            'car.reg_no' => ['nullable', 'string', 'max:20'],
            'items' => ['required', 'array', 'min:1', 'max:60'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.name' => ['nullable', 'string', 'max:160'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price_paise' => ['nullable', 'integer', 'min:0'],
            'valid_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'revised_from_id' => ['nullable', 'integer', 'exists:quotations,id'],
            'send' => ['nullable', 'boolean'],
        ], [
            'mobile.required_without' => 'Enter the customer’s mobile number. Without it we can’t follow up.',
            'items.required' => 'Add at least one item to the quotation.',
        ]);

        if (! empty($data['revised_from_id'])) {
            $this->authorizeQuote($request->user(), Quotation::query()->findOrFail($data['revised_from_id']));
        }

        $data['sent'] = $request->boolean('send');
        $quote = $this->quotations->create($data, $request->user());
        $wa = FollowUpMessages::waLink($quote->customer->mobile, app(FollowUpMessages::class)->forQuotation($quote));

        if ($data['sent']) {
            // The Day-1 message is the "here is your quotation" text; sending now counts as contact.
            $quote->followUps()->pending()->orderBy('due_on')->first()?->forceFill(['contacted_at' => now(), 'channel' => 'whatsapp'])->save();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $quote->id,
                'number' => $quote->number,
                'whatsapp_url' => $wa,
                'show_url' => route('quotations.show', $quote),
            ], 201);
        }

        return redirect()->route('quotations.show', $quote)
            ->with('status', "Quotation {$quote->number} saved. Follow-ups are planned.")
            ->with('wa_url', $data['sent'] ? $wa : null);
    }

    public function show(Request $request, Quotation $quotation)
    {
        $this->authorizeQuote($request->user(), $quotation);
        $quotation->load(['items', 'customer.cars', 'car', 'assignee:id,name', 'creator:id,name', 'revisedFrom:id,number', 'revisedTo:id,number', 'lead',
            'followUps' => fn ($f) => $f->with('doneBy:id,name')->orderBy('due_on')->orderBy('id')]);

        $next = $quotation->followUps->firstWhere('status', FollowUp::PENDING);
        $messages = app(FollowUpMessages::class);
        $text = $next ? $messages->forFollowUp($next) : $messages->forQuotation($quotation);

        return view('quotations.show', [
            'q' => $quotation,
            'next' => $next,
            'nextRow' => $next ? FollowUps::row($next) : null,
            'waUrl' => $next ? route('followups.whatsapp', $next) : route('quotations.whatsapp', $quotation),
            'message' => $text,
            'tel' => FollowUpMessages::telLink($quotation->customer->mobile),
            'pdfLink' => FollowUpMessages::pdfLink($quotation),
            'staff' => Quotation::seesAll($request->user()) ? $this->assignableStaff() : collect(),
            'billingReady' => Route::has('bills.create'),
            'timeline' => $this->timeline($quotation),
        ]);
    }

    /** Records the send, then opens WhatsApp (Web on the PC) with the message + PDF link. */
    public function whatsapp(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeQuote($request->user(), $quotation);
        $this->quotations->markSent($quotation);
        $text = app(FollowUpMessages::class)->forQuotation($quotation);

        return redirect()->away(FollowUpMessages::waLink($quotation->customer->mobile, $text));
    }

    public function convert(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeQuote($request->user(), $quotation);
        session(['bill_prefill' => $this->quotations->billPrefill($quotation)]);

        if (Route::has('bills.create')) {
            return redirect()->route('bills.create');
        }

        return redirect()->route('quotations.show', $quotation)
            ->with('status', 'Billing coming soon. The items are kept ready for when the bill screen is switched on.');
    }

    public function lost(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeQuote($request->user(), $quotation);
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(Quotation::LOST_REASONS))],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $this->quotations->markLost($quotation, $data['reason'], $data['note'] ?? null);

        return back()->with('status', "Quotation {$quotation->number} marked lost.");
    }

    public function reassign(Request $request, Quotation $quotation): RedirectResponse
    {
        abort_unless(Quotation::seesAll($request->user()), 403);
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $to = User::query()->findOrFail($data['user_id']);
        abort_unless($to->can('quotations.manage'), 422, 'That person can’t handle quotations.');
        $this->quotations->reassign($quotation, $to);

        return back()->with('status', "Follow-ups for {$quotation->number} now go to {$to->name}.");
    }

    public function pdf(Request $request, Quotation $quotation)
    {
        $this->authorizeQuote($request->user(), $quotation);

        return response()->file(QuotationPdf::render($quotation), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$quotation->number.'.pdf"',
        ]);
    }

    /** Signed link from the WhatsApp message (routes/public/quotations.php). */
    public function publicPdf(Quotation $quotation)
    {
        return response()->file(QuotationPdf::render($quotation), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$quotation->number.'.pdf"',
        ]);
    }

    /** One-tap "Asked price": mobile + product + price told (UX-FLOWS §3.2 step 1). */
    public function askedPrice(Request $request, LeadService $leads): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'what' => ['nullable', 'string', 'max:200', 'required_without:product_id'],
            'price' => ['nullable', 'string', 'max:15'],
            'source' => ['nullable', Rule::in(array_keys(Lead::SOURCES))],
        ], ['what.required_without' => 'Pick the product or type what they asked for.']);

        $price = null;
        if (filled($data['price'] ?? null)) {
            try {
                $price = Money::toPaise($data['price']);
            } catch (\InvalidArgumentException) {
                return back()->withErrors(['price' => 'Type the price in rupees, like 4999.'])->withInput();
            }
        } elseif (! empty($data['product_id'])) {
            $price = (int) Product::query()->whereKey($data['product_id'])->value('price_paise');
        }

        $lead = $leads->askedPrice($data['mobile'], $data['product_id'] ?? null, $price, $data['what'] ?? null, $request->user(), $data['source'] ?? 'walk_in_enquiry');

        if ($request->expectsJson()) {
            return response()->json(['id' => $lead->id], 201);
        }

        return back()->with('status', 'Price enquiry from '.Phone::pretty($lead->mobile).' saved. It is on today’s follow-up list.');
    }

    /** JSON list for the quotation builder's instant search (swap-in point for billing's module). */
    public function productIndex(): JsonResponse
    {
        return response()->json(['products' => $this->productList()]);
    }

    private function productList(): array
    {
        return Product::query()->active()->with('brand:id,name')
            ->orderByDesc('sold_count')->orderBy('name')->limit(5000)
            ->get()->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'brand' => $p->brand?->name,
                'type' => $p->type,
                'price_paise' => QuoteTotals::inclusive((int) $p->price_paise, $p->gst_rate, (bool) $p->price_includes_gst),
                'mrp_paise' => $p->mrp_paise,
                'gst_rate' => (float) $p->gst_rate,
                'sold_count' => $p->sold_count,
                'on_hand' => $p->track_stock ? $p->on_hand : null,
            ])->all();
    }

    private function customerPayload($c): array
    {
        return [
            'id' => $c->id, 'name' => $c->name, 'mobile_pretty' => Phone::pretty($c->mobile),
            'cars' => $c->cars->map(fn ($car) => ['id' => $car->id, 'label' => $car->label(), 'reg_no_pretty' => RegNo::pretty($car->reg_no)])->values()->all(),
        ];
    }

    private function assignableStaff()
    {
        return User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->filter(fn (User $u) => $u->can('quotations.manage'))->values();
    }

    /** Sent, follow-ups and their outcomes, won/lost/expired, oldest first. */
    private function timeline(Quotation $q): array
    {
        $tz = ShopClock::TZ;
        $events = [['at' => $q->created_at, 'icon' => 'doc', 'tone' => 'ink', 'title' => 'Quotation made', 'text' => ($q->creator?->name ?? '').($q->revisedFrom ? ' · revision of '.$q->revisedFrom->number : '')]];
        if ($q->sent_at) {
            $events[] = ['at' => $q->sent_at, 'icon' => 'chat', 'tone' => 'ink', 'title' => 'Sent on WhatsApp', 'text' => 'With the PDF link'];
        }
        foreach ($q->followUps as $f) {
            if ($f->status === FollowUp::DONE) {
                $events[] = ['at' => $f->done_at, 'icon' => 'chat', 'tone' => in_array($f->outcome, FollowUp::LOST_OUTCOMES, true) ? 'bad' : 'ok',
                    'title' => $f->stepLabel().': '.$f->outcomeLabel().($f->visit_on ? ' '.$f->visit_on->format('D j M') : ''),
                    'text' => trim(($f->doneBy?->name ?? '').($f->outcome_note ? ' · “'.$f->outcome_note.'”' : '').($f->done_at && $f->done_at->copy()->timezone($tz)->toDateString() > $f->due_on->toDateString() ? ' · late' : ''), ' ·')];
            }
        }
        if ($q->won_at) {
            $events[] = ['at' => $q->won_at, 'icon' => 'receipt', 'tone' => 'ok', 'title' => 'Won', 'text' => $q->won_invoice_id ? 'Bill #'.$q->won_invoice_id : ''];
        }
        if ($q->lost_at) {
            $events[] = ['at' => $q->lost_at, 'icon' => 'x', 'tone' => 'bad', 'title' => 'Lost: '.(Quotation::LOST_REASONS[$q->lost_reason] ?? $q->lost_reason), 'text' => (string) $q->lost_note];
        }
        if ($q->expired_at) {
            $events[] = ['at' => $q->expired_at, 'icon' => 'x', 'tone' => 'muted', 'title' => $q->revisedTo ? 'Replaced by '.$q->revisedTo->number : 'Expired', 'text' => ''];
        }
        usort($events, fn ($a, $b) => $a['at'] <=> $b['at']);

        return $events;
    }

    private function authorizeQuote(User $user, Quotation $q): void
    {
        abort_unless(Quotation::seesAll($user) || $q->assigned_to === $user->id, 403);
    }
}
