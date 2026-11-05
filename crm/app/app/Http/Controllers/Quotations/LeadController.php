<?php

namespace App\Http\Controllers\Quotations;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quotations\LeadService;
use App\Services\Quotations\QuotationService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Leads from ads / calls / walk-in enquiries (UX-FLOWS §3.10). */
class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');
        $base = Lead::query()->visibleTo($user);

        return view('leads.index', [
            'leads' => (clone $base)->with(['assignee:id,name', 'product:id,name', 'followUps' => fn ($f) => $f->pending()->orderBy('due_on')])
                ->when(array_key_exists((string) $status, Lead::STATUS_LABELS), fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(30)->withQueryString(),
            'counts' => (clone $base)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'status' => $status,
            'highlight' => (int) $request->query('highlight'),
            'staff' => Quotation::seesAll($user) ? User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->filter(fn ($u) => $u->can('leads.manage'))->values() : collect(),
            'canQuote' => $user->can('quotations.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:120'],
            'interest' => ['required', 'string', 'max:500'],
            'source' => ['required', Rule::in(array_keys(Lead::SOURCES))],
            'price' => ['nullable', 'string', 'max:15'],
        ], ['interest.required' => 'Write what they asked for, e.g. "PPF for Creta".']);

        if (filled($data['price'] ?? null)) {
            try {
                $data['price_told_paise'] = Money::toPaise($data['price']);
            } catch (\InvalidArgumentException) {
                return back()->withErrors(['price' => 'Type the price in rupees, like 4999.'])->withInput();
            }
        }
        $lead = $this->leads->create($data, $request->user());

        return redirect()->route('leads.index', ['highlight' => $lead->id])->with('status', 'Lead saved. A follow-up is on today’s list.');
    }

    public function lost(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless(Quotation::seesAll($request->user()) || $lead->assigned_to === $request->user()->id, 403);
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(Quotation::LOST_REASONS))],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $this->leads->markLost($lead, $data['reason'], $data['note'] ?? null);

        return back()->with('status', 'Lead closed as lost.');
    }

    public function reassign(Request $request, Lead $lead, QuotationService $quotations): RedirectResponse
    {
        abort_unless(Quotation::seesAll($request->user()), 403);
        $to = User::query()->findOrFail($request->validate(['user_id' => ['required', 'integer']])['user_id']);
        $quotations->reassign($lead, $to);

        return back()->with('status', "Lead now handled by {$to->name}.");
    }
}
