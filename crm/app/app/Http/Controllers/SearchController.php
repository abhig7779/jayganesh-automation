<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use App\Support\Phone;
use App\Support\RegNo;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Global search (UX-FLOWS §1 "Global search"): one box finds customers (mobile digits or name),
 * cars (plate), bills (number), quotations (number) and products (name / SKU). Results are
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

        // Bills by number (guarded until billing merges). WIRE: invoices.number
        $invoice = 'App\\Models\\Invoice';
        if ($user->can('invoices.view') && class_exists($invoice) && Schema::hasTable('invoices')) {
            $items = $this->safe(fn () => $invoice::query()->where('number', 'like', '%'.str_replace(['%', '_'], '', $q).'%')
                ->with('customer')->latest()->limit(self::PER_GROUP)->get()
                ->map(fn ($i) => [
                    'title' => $i->number,
                    'subtitle' => ($i->customer?->name ?? 'Walk-in').' · '.$i->created_at?->timezone('Asia/Kolkata')->format('j M'),
                    'meta' => Money::format((int) $i->total_paise),
                    'url' => Route::has('bills.show') ? route('bills.show', $i) : null,
                    'icon' => 'receipt',
                ])->all());
            if ($items) {
                $groups['bills'] = ['label' => 'Bills', 'items' => $items];
            }
        }

        // Quotations by number (guarded). WIRE: quotations.number
        $quotation = 'App\\Models\\Quotation';
        if ($user->can('quotations.view') && class_exists($quotation) && Schema::hasTable('quotations')) {
            $items = $this->safe(fn () => $quotation::query()->where('number', 'like', '%'.str_replace(['%', '_'], '', $q).'%')
                ->with('customer')->latest()->limit(self::PER_GROUP)->get()
                ->map(fn ($x) => [
                    'title' => $x->number,
                    'subtitle' => ($x->customer?->name ?? 'Customer').' · '.$x->created_at?->timezone('Asia/Kolkata')->format('j M'),
                    'meta' => Money::format((int) $x->total_paise),
                    'url' => Route::has('quotations.show') ? route('quotations.show', $x) : null,
                    'icon' => 'doc',
                ])->all());
            if ($items) {
                $groups['quotations'] = ['label' => 'Quotations', 'items' => $items];
            }
        }

        // Products by name / SKU / brand (the products table is on main).
        if ($user->can('inventory.view') && mb_strlen($q) >= 2 && Schema::hasTable('products')) {
            $items = $this->safe(fn () => Product::query()->active()->search($q)->with('brand')
                ->orderByDesc('sold_count')->limit(self::PER_GROUP)->get()
                ->map(fn (Product $p) => [
                    'title' => $p->name,
                    'subtitle' => trim(($p->brand?->name ? $p->brand->name.' · ' : '').($p->isService() ? 'Service' : ($p->track_stock ? $p->on_hand.' in stock' : 'Product'))),
                    'meta' => Money::format((int) $p->price_paise),
                    'url' => Route::has('products.show') ? route('products.show', $p) : (Route::has('stock.check') ? route('stock.check', ['q' => $p->name]) : null),
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
