<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CustomerRequest;
use App\Http\Requests\Customers\LookupRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Customers\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    /** GET /api/v1/customers?q= — 20 per page, newest activity first, archived hidden. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Customer::class);

        $page = Customer::query()
            ->active()
            ->search((string) $request->query('q', ''))
            ->with('cars')
            ->orderByRaw('last_visit_at IS NULL')
            ->orderByDesc('last_visit_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return CustomerResource::collection($page);
    }

    /** GET /api/v1/customers/lookup?mobile= — the bill screen's instant check. */
    public function lookup(LookupRequest $request): CustomerResource|JsonResponse
    {
        $customer = Customer::query()->where('mobile', $request->validated('mobile'))->with('cars')->first();

        return $customer
            ? new CustomerResource($customer)
            : response()->json(['message' => 'No customer with this number yet.'], 404);
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $customer = $this->customers->findOrCreateByMobile($data['mobile'], $data, $request->user());

        return (new CustomerResource($customer->load('cars')))->response()->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        Gate::authorize('view', $customer);

        return new CustomerResource($customer->load('cars'));
    }

    public function update(CustomerRequest $request, Customer $customer): CustomerResource
    {
        $this->customers->update($customer, $request->validated());

        return new CustomerResource($customer->load('cars'));
    }

    public function archive(Request $request, Customer $customer): CustomerResource
    {
        Gate::authorize('archive', $customer);
        $this->customers->archive($customer, $request->user());

        return new CustomerResource($customer->load('cars'));
    }
}
