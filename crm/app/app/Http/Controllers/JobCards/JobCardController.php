<?php

namespace App\Http\Controllers\JobCards;

use App\Http\Controllers\Controller;
use App\Http\Requests\JobCards\StoreJobCardRequest;
use App\Models\Customer;
use App\Models\JobCard;
use App\Models\Product;
use App\Models\User;
use App\Services\JobCards\JobCardService;
use App\Support\Dashboard\Workshop;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JobCardController extends Controller
{
    public function __construct(private readonly JobCardService $service) {}

    /** Desktop job board. Technicians land on their own list instead. */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('viewAny', JobCard::class);
        if (! Gate::allows('seeAll', JobCard::class)) {
            return redirect()->route('jobcards.mine');
        }

        $technicianId = $request->integer('technician') ?: null;
        $scope = $request->query('scope') === 'today' ? 'today' : 'all';

        $cards = JobCard::query()->onBoard()
            ->when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($technicianId, fn ($q) => $q->where('technician_id', $technicianId))
            ->when($scope === 'today', function ($q) {
                $start = JobCard::todayStart();
                $end = $start->copy()->addDay();
                $q->where(fn ($w) => $w->where('created_at', '>=', $start)
                    ->orWhere('promised_at', '<', $end)
                    ->orWhere('status', JobCard::DELIVERED));
            })
            ->with(['car', 'customer', 'technician', 'items', 'photos'])
            ->orderByRaw('promised_at IS NULL')->orderBy('promised_at')->orderBy('id')
            ->get();

        $columns = [
            JobCard::WAITING => 'Waiting',
            JobCard::WORKING => 'Working',
            JobCard::QC => 'QC',
            JobCard::READY => 'Ready',
            JobCard::DELIVERED => 'Delivered today',
        ];

        return view('jobcards.index', [
            'columns' => $columns,
            'byStatus' => $cards->groupBy('status'),
            'technicians' => $this->technicians(),
            'technicianId' => $technicianId,
            'scope' => $scope,
            'summary' => Workshop::summary($user->branch_id),
            'transitions' => JobCardService::TRANSITIONS,
            'canMove' => $user->can('jobcards.manage') || $user->can('jobcards.create'),
        ]);
    }

    /** Technician phone home: my cars only, big cards, no money. */
    public function mine(Request $request): View
    {
        Gate::authorize('viewAny', JobCard::class);

        return view('jobcards.mine', [
            'cards' => Workshop::forTechnician($request->user()),
            'user' => $request->user(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', JobCard::class);

        $customer = null;
        if ($request->filled('customer')) {
            $customer = Customer::query()->with('cars')->find($request->integer('customer'));
        }

        return view('jobcards.create', [
            'technicians' => $this->technicians(),
            'customer' => $customer,
            'popular' => Product::query()->active()->orderByDesc('sold_count')->orderBy('name')->limit(8)->get(['id', 'name', 'price_paise']),
        ]);
    }

    public function store(StoreJobCardRequest $request): RedirectResponse
    {
        $card = $this->service->create($request->forService(), $request->user());

        return redirect()->route('jobcards.show', $card)
            ->with('status', "Car in. Job card {$card->number} opened.");
    }

    public function show(Request $request, JobCard $jobCard): View
    {
        Gate::authorize('view', $jobCard);
        $jobCard->load(['car', 'customer', 'technician', 'creator', 'items.doneBy', 'photos', 'events.user']);
        $user = $request->user();

        $moves = collect(JobCardService::TRANSITIONS[$jobCard->status] ?? [])
            ->filter(fn ($to) => Gate::allows('move', [$jobCard, $to]))
            ->values();

        return view('jobcards.show', [
            'card' => $jobCard,
            'moves' => $moves,
            'seePrices' => Gate::allows('seePrices', JobCard::class),
            'canWork' => Gate::allows('work', $jobCard),
            'canUpdate' => Gate::allows('update', $jobCard),
            'technicians' => Gate::allows('update', $jobCard) ? $this->technicians() : collect(),
            'canBill' => Gate::allows('makeBill', $jobCard),
            'whatsAppLink' => in_array($jobCard->status, [JobCard::READY, JobCard::DELIVERED], true) && Gate::allows('seeAll', JobCard::class)
                ? route('jobcards.notify-ready', $jobCard) : null,
            'isTechnician' => ! Gate::allows('seeAll', JobCard::class),
            'user' => $user,
        ]);
    }

    /** A4 job sheet for the workshop. Prices only with ?prices=1 and permission. */
    public function print(Request $request, JobCard $jobCard): View
    {
        Gate::authorize('view', $jobCard);
        $jobCard->load(['car', 'customer', 'technician', 'items', 'branch']);

        return view('jobcards.print', [
            'card' => $jobCard,
            'withPrices' => $request->boolean('prices') && Gate::allows('seePrices', JobCard::class),
        ]);
    }

    /** Product/service search for the Car in form and "Add work". */
    public function products(Request $request): JsonResponse
    {
        Gate::authorize('create', JobCard::class);
        $q = trim((string) $request->query('q', ''));

        $rows = Product::query()->active()
            ->when($q !== '', fn ($x) => $x->search($q))
            ->orderByDesc('sold_count')->orderBy('name')
            ->limit(12)->get(['id', 'name', 'type', 'price_paise']);

        return response()->json(['data' => $rows->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'type' => $p->type,
            'price_paise' => $p->price_paise,
            'price' => number_format($p->price_paise / 100, $p->price_paise % 100 ? 2 : 0, '.', ''),
            'price_label' => Money::format($p->price_paise),
        ])]);
    }

    /** Active staff who can work on cars (role technician), for the assign dropdowns. */
    private function technicians(): Collection
    {
        return User::role('technician')->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
