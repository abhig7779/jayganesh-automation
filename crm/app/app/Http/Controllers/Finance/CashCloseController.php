<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashClose;
use App\Services\Finance\CashCloseService;
use App\Services\Finance\Period;
use App\Services\Finance\SalesQueries;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CashCloseController extends Controller
{
    public function __construct(private CashCloseService $service) {}

    public function show(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $date = $this->date($request->query('date'));
        $close = CashClose::query()->with('closer:id,name')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('date', $date)->first();

        return view('finance.cash-close', [
            'date' => $date,
            'isToday' => $date === Period::today()->toDateString(),
            'expected' => $this->service->expected($date, $branchId),
            'summary' => $this->service->daySummary($date, $branchId),
            'close' => $close,
            'waText' => $close ? $this->service->whatsappText($close) : null,
            'waLink' => $close ? $this->service->whatsappLink($close) : null,
            'billingInstalled' => SalesQueries::paymentsInstalled(),
            'redo' => $request->boolean('redo'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.Period::today()->toDateString()],
            'counted' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['counted.required' => 'Type the cash you counted in the drawer.']);

        try {
            $counted = Money::toPaise($data['counted']);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['counted' => 'Type the amount in rupees, like 12,450.']);
        }
        if ($counted < 0) {
            throw ValidationException::withMessages(['counted' => 'The counted cash can\'t be below ₹0.']);
        }

        $this->service->close($request->user(), $data['date'], $counted, $data['note'] ?? null);

        return redirect()->route('finance.cash-close', ['date' => $data['date']])
            ->with('status', 'Cash close saved. Send the summary to the owner on WhatsApp.');
    }

    public function index(Request $request)
    {
        $branchId = $request->user()->branch_id;

        return view('finance.cash-closes', [
            'closes' => CashClose::query()->with('closer:id,name')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->orderByDesc('date')->paginate(31),
        ]);
    }

    private function date(mixed $input): string
    {
        $today = Period::today();
        if (! is_string($input) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
            return $today->toDateString();
        }
        $d = Period::day($input)->from;

        return $d->gt($today) ? $today->toDateString() : $d->toDateString();
    }
}
