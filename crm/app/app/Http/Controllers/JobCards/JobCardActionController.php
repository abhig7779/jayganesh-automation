<?php

namespace App\Http\Controllers\JobCards;

use App\Http\Controllers\Controller;
use App\Models\JobCard;
use App\Models\JobCardItem;
use App\Models\User;
use App\Services\JobCards\JobCardService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JobCardActionController extends Controller
{
    public function __construct(private readonly JobCardService $service) {}

    /** Status change: board drag (JSON), phone buttons and detail buttons (form posts). */
    public function status(Request $request, JobCard $jobCard): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(JobCard::STATUSES)],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        Gate::authorize('view', $jobCard);

        if (! Gate::allows('move', [$jobCard, $data['status']])) {
            $message = 'Only the counter or an admin can move a car to '.JobCard::LABELS[$data['status']].'.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }
            abort(403, $message);
        }

        $this->service->transition($jobCard, $data['status'], $request->user(), $data['note'] ?? null);

        $whatsApp = $jobCard->status === JobCard::READY && Gate::allows('seeAll', JobCard::class)
            ? route('jobcards.notify-ready', $jobCard) : null;

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'id' => $jobCard->id,
                'status' => $jobCard->status,
                'label' => $jobCard->label(),
                'message' => $jobCard->number.' moved to '.$jobCard->label().'.',
                'whatsapp_url' => $whatsApp,
            ]);
        }

        return back()->with('status', $jobCard->number.' moved to '.$jobCard->label().'.');
    }

    public function assign(Request $request, JobCard $jobCard): RedirectResponse
    {
        Gate::authorize('update', $jobCard);
        $data = $request->validate([
            'technician_id' => ['nullable', 'integer', 'exists:users,id'],
            'promised_at' => ['nullable', 'date'],
            'bay' => ['nullable', 'string', 'max:20'],
        ]);

        $tech = filled($data['technician_id'] ?? null) ? User::query()->find($data['technician_id']) : null;
        $this->service->assign($jobCard, $tech, $request->user());

        $promised = filled($data['promised_at'] ?? null) ? Carbon::parse($data['promised_at'], 'Asia/Kolkata')->utc() : null;
        if ($request->has('promised_at') && $promised?->timestamp !== $jobCard->promised_at?->timestamp) {
            $jobCard->promised_at = $promised;
            $jobCard->save();
            $this->service->event($jobCard, 'note', $request->user(), note: $promised
                ? 'Promised time changed to '.$promised->copy()->timezone('Asia/Kolkata')->format('j M, g:i a')
                : 'Promised time cleared');
        }
        if ($request->has('bay') && ($data['bay'] ?? null) !== $jobCard->bay) {
            $jobCard->bay = $data['bay'] ?? null;
            $jobCard->save();
        }

        return back()->with('status', 'Saved.');
    }

    /** Tick / untick a work item. JSON for the phone's instant ticks, redirect without JS. */
    public function toggleItem(Request $request, JobCard $jobCard, JobCardItem $item): JsonResponse|RedirectResponse
    {
        abort_unless($item->job_card_id === $jobCard->id, 404);
        Gate::authorize('work', $jobCard);

        $done = $request->has('done') ? $request->boolean('done') : ! $item->done;
        $this->service->setItemDone($item, $done, $request->user());

        if ($request->expectsJson()) {
            $jobCard->load('items');

            return response()->json([
                'ok' => true,
                'done' => $item->done,
                'progress' => $jobCard->items->where('done', true)->count().'/'.$jobCard->items->count(),
            ]);
        }

        return back();
    }

    public function addItem(Request $request, JobCard $jobCard): RedirectResponse
    {
        Gate::authorize('update', $jobCard);
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'description' => ['nullable', 'required_without:product_id', 'string', 'max:200'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            'price' => ['nullable', 'regex:/^\s*(₹\s*)?[\d,]+(\.\d{1,2})?\s*$/u'],
        ], ['description.required_without' => 'Type the work or pick a product.']);

        $this->service->addItem($jobCard, [
            'product_id' => $data['product_id'] ?? null,
            'description' => $data['description'] ?? null,
            'qty' => $data['qty'] ?? 1,
            'price_paise' => filled($data['price'] ?? null) ? Money::toPaise((string) $data['price']) : null,
        ], $request->user());

        return back()->with('status', 'Work added.');
    }

    /**
     * Delivered → Make bill. Puts the CONTRACTS.md §3 prefill in the session and opens New bill.
     * Until billing is merged the button says "Billing coming soon" and this just goes back.
     */
    public function makeBill(Request $request, JobCard $jobCard): RedirectResponse
    {
        Gate::authorize('makeBill', $jobCard);

        if (! Route::has('bills.create')) {
            return back()->with('status', 'Billing coming soon. Make this bill on the counter for now.');
        }
        if ($jobCard->invoice_id) {
            throw ValidationException::withMessages(['bill' => 'This job card already has a bill.']);
        }

        session(['bill_prefill' => $this->service->billPrefill($jobCard)]);
        // Read by App\Listeners\JobCards\LinkInvoiceToJobCard when billing fires InvoiceSaved.
        session(['jobcards.billing_job_card_id' => $jobCard->id]);
        $this->service->event($jobCard, 'note', $request->user(), note: 'Bill started');

        return redirect()->route('bills.create');
    }

    /** Logs the ready message on the timeline, then opens WhatsApp with it. */
    public function notifyReady(Request $request, JobCard $jobCard): RedirectResponse
    {
        Gate::authorize('view', $jobCard);
        abort_unless(Gate::allows('seeAll', JobCard::class), 403);

        $link = $this->service->readyWhatsAppLink($jobCard);
        abort_if($link === null, 404, 'This customer has no mobile number.');
        $this->service->logReadyMessage($jobCard, $request->user());

        return redirect()->away($link);
    }
}
