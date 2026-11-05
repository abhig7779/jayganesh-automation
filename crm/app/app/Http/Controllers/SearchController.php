<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RegNo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * Global search (UX-FLOWS §1 "Global search"): one box finds customers (mobile digits or name),
 * cars (plate), job cards (number or plate), bills (number), quotations (number) and products
 * (name / SKU / "also called" alias, via Product::search + rankedFor). Results are
 * grouped by type and filtered by what the user may see. htmx gets the grouped partial (the
 * top-bar dropdown); a normal request gets the full page. 10 digits that match one customer
 * jump straight to their profile.
 */
class SearchController extends Controller
{
    private const PER_GROUP = 5;

    public function __invoke(Request $request)
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 80));
        $groups = $q === '' ? [] : $this->search($q, $request->user());

        // The top-bar dropdown asks for just the grouped list; the search page swaps its own region.
        if ($request->header('HX-Request') && $request->header('HX-Target') !== 'search-page-results') {
            return view('search.results', ['q' => $q, 'groups' => $groups, 'compact' => true]);
        }

        $digits = preg_replace('/\D+/', '', $q);
        if (strlen($digits) >= 10 && $request->user()->can('customers.view')) {
            $exact = Customer::query()->active()->where('mobile', Phone::normalize(substr($digits, -10)))->first();
            if ($exact && ! $request->boolean('stay')) {
                return redirect()->route('customers.show', $exact);
            }
        }

        return view('search.index', ['q' => $q, 'groups' => $groups]);
    }

    /** @return array<string, array{label:string, items:array}> */
    public function search(string $q, User $user): array
    {
        $groups = [];

        if ($user->can('customers.view')) {
            $customers = Customer::query()->active()->search($q)->with('cars')
                ->orderByRaw('last_visit_at IS NULL')->orderByDesc('last_visit_at')->limit(self::PER_GROUP)->get();
            $items = $customers->map(fn (Customer $c) => [
                'title' => $c->name,
                'subtitle' => trim(Phone::pretty($c->mobile).($c->cars->first() ? ' · '.$c->cars->first()->model : '')),
                'meta' => $c->total_spent_paise ? Money::format($c->total_spent_paise) : null,
                'url' => route('customers.show', $c),
                'avatar' => $c->name,
            ])->all();
            if ($items) {
                $groups['customers'] = ['label' => 'Customers', 'items' => $items];
            }

            // Cars by plate, only when the query looks like part of a number plate.
            $reg = RegNo::normalize($q);
            if ($reg && strlen($reg) >= 4 && preg_match('/[A-Z]/', $reg) && preg_match('/\d/', $reg)) {
                $cars = CustomerCar::query()->where('reg_no', 'like', '%'.$reg.'%')
                    ->whereHas('customer', fn ($c) => $c->whereNull('archived_at'))
                    ->with('customer')->limit(self::PER_GROUP)->get();
                $items = $cars->map(fn (CustomerCar $car) => [
                    'title' => RegNo::pretty($car->reg_no),
                    'subtitle' => $car->label().' · '.$car->customer->name,
                    'meta' => null,
                    'url' => route('customers.show', [$car->customer, 'tab' => 'cars']),
                    'icon' => 'car',
                ])->all();
                if ($items) {
                    $groups['cars'] = ['label' => 'Cars', 'items' => $items];
                }
            }
        }

        // Bills by number ("JG-2627-0142", "0142"). Sales see only their own bills unless they
        // have reports.all_staff (Invoice::visibleTo), the same rule as the Bills list.
        $term = str_replace(['%', '_'], '', $q);
        if ($user->can('invoices.view') && Schema::hasTable('invoices') && preg_match('/\d/', $term)) {
            $items = $this->safe(fn () => Invoice::query()->visibleTo($user)->where('number', 'like', '%'.$term.'%')
                ->with('customer')->latest('id')->limit(self::PER_GROUP)->get()
                ->map(fn (Invoice $i) => [
                    'title' => $i->number,
                    'subtitle' => ($i->customer?->name ?? ($i->customer_name ?: 'Walk-in')).' · '.$i->statusLabel().' · '.$i->created_at?->timezone('Asia/Kolkata')->format('j M'),
                    'meta' => Money::format((int) $i->total_paise),
                    'url' => route('bills.show', $i),
                    'icon' => 'receipt',
                ])->all());
            if ($items) {
                $groups['bills'] = ['label' => 'Bills', 'items' => $items];
            }
        }

        // Quotations by number ("Q-2627-0012", "0012"): only your own unless you see all staff
        // (reports.all_staff) and may open anyone's quote (Owner/Admin).
        if ($user->can('quotations.view') && Schema::hasTable('quotations') && preg_match('/\d/', $term)) {
            $seesAll = $user->can('reports.all_staff') && Quotation::seesAll($user);
            $items = $this->safe(fn () => Quotation::query()->when(! $seesAll, fn ($w) => $w->where('assigned_to', $user->id))
                ->where('number', 'like', '%'.$term.'%')
                ->with('customer')->latest('id')->limit(self::PER_GROUP)->get()
                ->map(fn (Quotation $x) => [
                    'title' => $x->number,
                    'subtitle' => ($x->customer?->name ?? 'Customer').' · '.$x->statusLabel().' · '.$x->created_at?->timezone('Asia/Kolkata')->format('j M'),
                    'meta' => Money::format((int) $x->total_paise),
                    'url' => route('quotations.show', $x),
                    'icon' => 'doc',
                ])->all());
            if ($items) {
                $groups['quotations'] = ['label' => 'Quotations', 'items' => $items];
            }
        }

        // Job cards by number (JC-2627-0004) or plate. Technicians find only their own cars.
        if ($user->can('jobcards.view') && Schema::hasTable('job_cards')) {
            $reg = RegNo::normalize($q);
            $byPlate = $reg && strlen($reg) >= 4 && preg_match('/\d/', $reg);
            $byNumber = preg_match('/\d/', $term) && strlen($term) >= 2;
            if ($byPlate || $byNumber) {
                $items = $this->safe(fn () => JobCard::query()
                    ->when(! Gate::forUser($user)->allows('seeAll', JobCard::class), fn ($w) => $w->forTechnician($user))
                    ->where(fn ($w) => $w
                        ->when($byNumber, fn ($x) => $x->orWhere('number', 'like', '%'.$term.'%'))
                        ->when($byPlate, fn ($x) => $x->orWhereHas('car', fn ($c) => $c->where('reg_no', 'like', '%'.$reg.'%'))))
                    ->with(['car', 'customer'])->latest('id')->limit(self::PER_GROUP)->get()
                    ->map(fn (JobCard $j) => [
                        'title' => $j->plate() ? $j->plate().' · '.$j->number : $j->number,
                        'subtitle' => trim($j->carLabel(true).' · '.($j->customer?->name ?? '').' · '.$j->label(), ' ·'),
                        'meta' => $j->isOpen() ? $j->promiseText() : $j->created_at?->timezone('Asia/Kolkata')->format('j M'),
                        'url' => route('jobcards.show', $j),
                        'icon' => 'wrench',
                    ])->all());
                if ($items) {
                    $groups['jobcards'] = ['label' => 'Job cards', 'items' => $items];
                }
            }
        }

        // Products by name / SKU / brand / alias: exact SKU first, then names starting with the text.
        if ($user->can('inventory.view') && mb_strlen($q) >= 2 && Schema::hasTable('products')) {
            $items = $this->safe(fn () => Product::query()->active()->search($q)->rankedFor($q)->with('brand')
                ->limit(self::PER_GROUP)->get()
                ->map(fn (Product $p) => [
                    'title' => $p->name,
                    'subtitle' => trim(($p->brand?->name ? $p->brand->name.' · ' : '').($p->isService() ? 'Service' : ($p->track_stock ? $p->on_hand.' in stock' : 'Product'))),
                    'meta' => Money::format((int) $p->price_paise),
                    'url' => route('products.show', $p),
                    'icon' => 'box',
                ])->all());
            if ($items) {
                $groups['products'] = ['label' => 'Products', 'items' => $items];
            }
        }

        return $groups;
    }

    private function safe(callable $fn): array
    {
        try {
            return $fn();
        } catch (QueryException $e) {
            report($e);

            return [];
        }
    }
}
