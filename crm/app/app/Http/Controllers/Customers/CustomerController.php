<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\WebCustomerRequest;
use App\Models\Customer;
use App\Services\Customers\CustomerService;
use App\Support\CarModels;
use App\Support\Customers\CustomerHistory;
use App\Support\Phone;
use App\Support\RegNo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** Customers screens (web). Every write goes through CustomerService, same as /api/v1. */
class CustomerController extends Controller
{
    public const VISIT_FILTERS = [
        '' => 'Any time',
        '30' => 'Last 30 days',
        '90' => 'Last 3 months',
        'lapsed' => 'Over 6 months ago',
        'never' => 'No visit yet',
    ];

    public const SORTS = ['recent' => 'Recent visit', 'name' => 'Name A–Z', 'spent' => 'Top spenders', 'new' => 'Newest'];

    public function __construct(private readonly CustomerService $customers) {}

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $customers = $this->listQuery($filters)->paginate(25)->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'filters' => $filters,
            'total' => Customer::query()->active()->count(),
        ]);
    }

    public function show(Request $request, Customer $customer)
    {
        $customer->load(['cars' => fn ($q) => $q->latest(), 'creator']);
        $filters = $this->filters($request);
        $history = new CustomerHistory($customer, $request->user());

        return view('customers.show', [
            'customer' => $customer,
            'filters' => $filters,
            // Two-pane on wide screens: the list stays on the left.
            'list' => $this->listQuery($filters)->limit(40)->get(),
            'bills' => $history->bills(),
            'quotations' => $history->quotations(),
            'duePaise' => $history->duePaise(),
            'hiddenHistory' => $history->hiddenCount(),
            'billUrl' => Route::has('bills.create') && $request->user()->can('invoices.create') && ! $customer->isArchived()
                ? route('bills.create', ['customer' => $customer->id]) : null,
            'carOptions' => CarModels::options(),
        ]);
    }

    public function create(Request $request)
    {
        $digits = preg_replace('/\D+/', '', (string) $request->query('mobile', ''));

        return view('customers.form', [
            'customer' => new Customer(['source' => 'walk_in', 'whatsapp_opt_in' => true]),
            'prefillMobile' => strlen($digits) >= 10 ? substr($digits, -10) : '',
            'carOptions' => CarModels::options(),
            'next' => $request->query('next'),
        ]);
    }

    public function store(WebCustomerRequest $request)
    {
        $data = $request->validated();
        $customer = $this->customers->findOrCreateByMobile($data['mobile'], $data, $request->user());

        if (! empty($data['car'])) {
            [$make, $model] = CarModels::split($data['car_make'] ?? null, $data['car']);
            $this->customers->addCar($customer, ['make' => $make, 'model' => $model, 'reg_no' => $data['reg_no'] ?? null], $request->user());
        }

        if ($request->input('next') === 'bill' && Route::has('bills.create')) {
            return redirect()->route('bills.create', ['customer' => $customer->id]);
        }

        return redirect()->route('customers.show', $customer)->with('status', "{$customer->name} added.");
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', [
            'customer' => $customer,
            'prefillMobile' => substr((string) $customer->mobile, 3),
            'carOptions' => [],
            'next' => null,
        ]);
    }

    public function update(WebCustomerRequest $request, Customer $customer)
    {
        $this->customers->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('status', 'Saved.');
    }

    public function notes(Request $request, Customer $customer)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $this->customers->update($customer, ['notes' => $data['notes'] ?? null]);

        return redirect()->route('customers.show', [$customer, 'tab' => 'notes'])->with('status', 'Note saved.');
    }

    public function archive(Request $request, Customer $customer)
    {
        $this->customers->archive($customer, $request->user());

        return redirect()->route('customers.index')
            ->with('status', "{$customer->name} archived. Bills and history are kept; search the number again to bring them back.");
    }

    public function restore(Request $request, Customer $customer)
    {
        if ($customer->isArchived()) {
            $customer->archived_at = null;
            $customer->save();
            activity('customers')->performedOn($customer)->causedBy($request->user())->event('restored')->log('restored');
        }

        return redirect()->route('customers.show', $customer)->with('status', "{$customer->name} is back in the customer list.");
    }

    /**
     * GET /customers/check?mobile=…&ignore=ID — htmx partial for the Add/Edit form. Once 10
     * digits are typed, says whether the number already belongs to someone.
     */
    public function check(Request $request)
    {
        $e164 = Phone::normalize((string) $request->query('mobile', ''));
        $existing = Phone::isValidIndianMobile($e164)
            ? Customer::query()->where('mobile', $e164)->whereKeyNot((int) $request->query('ignore', 0))->with('cars')->first()
            : null;

        return view('customers.partials.duplicate', ['existing' => $existing]);
    }

    // ------------------------------------------------------------------ list helpers

    private function filters(Request $request): array
    {
        $f = [
            'q' => trim((string) $request->query('q', '')),
            'source' => (string) $request->query('source', ''),
            'visit' => (string) $request->query('visit', ''),
            'sort' => (string) $request->query('sort', 'recent'),
            'added' => (string) $request->query('added', ''),
            'archived' => $request->boolean('archived') && $request->user()->can('customers.manage'),
        ];
        if (! in_array($f['source'], Customer::SOURCES, true)) {
            $f['source'] = '';
        }
        if (! array_key_exists($f['visit'], self::VISIT_FILTERS)) {
            $f['visit'] = '';
        }
        if (! array_key_exists($f['sort'], self::SORTS)) {
            $f['sort'] = 'recent';
        }
        $f['active'] = array_filter([$f['source'], $f['visit'], $f['added'], $f['archived']]);

        return $f;
    }

    private function listQuery(array $f): Builder
    {
        $now = CarbonImmutable::now();

        return Customer::query()
            ->when($f['archived'], fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->active())
            ->search($f['q'])
            ->when($f['source'], fn ($q, $s) => $q->where('source', $s))
            ->when($f['visit'] === '30', fn ($q) => $q->where('last_visit_at', '>=', $now->subDays(30)))
            ->when($f['visit'] === '90', fn ($q) => $q->where('last_visit_at', '>=', $now->subDays(90)))
            ->when($f['visit'] === 'lapsed', fn ($q) => $q->where('last_visit_at', '<', $now->subMonths(6)))
            ->when($f['visit'] === 'never', fn ($q) => $q->whereNull('last_visit_at'))
            ->when($f['added'] === 'today', fn ($q) => $q->where('created_at', '>=', $now->setTimezone('Asia/Kolkata')->startOfDay()->utc()))
            ->with(['cars' => fn ($q) => $q->latest()])
            ->when($f['sort'] === 'name', fn ($q) => $q->orderBy('name'))
            ->when($f['sort'] === 'spent', fn ($q) => $q->orderByDesc('total_spent_paise'))
            ->when($f['sort'] === 'new', fn ($q) => $q->orderByDesc('id'))
            ->when($f['sort'] === 'recent', fn ($q) => $q->orderByRaw('last_visit_at IS NULL')->orderByDesc('last_visit_at'))
            ->orderByDesc('id');
    }

    /** "Creta · MH 12 KT 4521" for list rows. */
    public static function carLine(Customer $c): ?string
    {
        $car = $c->cars->first();
        if (! $car) {
            return null;
        }
        $line = $car->model.($car->reg_no ? ' · '.RegNo::pretty($car->reg_no) : '');

        return $c->cars->count() > 1 ? $line.' +'.($c->cars->count() - 1) : $line;
    }
}
