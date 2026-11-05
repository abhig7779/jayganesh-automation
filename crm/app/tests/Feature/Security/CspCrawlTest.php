<?php

/*
 * Audit H3: the enforced CSP blocks inline <script> without the request's nonce and every
 * on*="" handler. Crawl the main pages as each role and check the HTML that comes back.
 */

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\JobCards\JobCardService;
use App\Services\Quotations\QuotationService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDef;
use Illuminate\Support\Facades\Route;
use Tests\Support\FinanceFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 18:00', 'Asia/Kolkata'));
    config(['ops.csp' => 'enforce']);

    FinanceFixture::build($this->branch->id);
    $owner = User::role('owner')->first();
    $customer = Customer::query()->first();
    app(QuotationService::class)->create(['customer_id' => $customer->id, 'items' => [['product_id' => Product::query()->first()->id, 'qty' => 1]]], $owner);
    $car = CustomerCar::factory()->create(['customer_id' => $customer->id, 'make' => 'Hyundai', 'model' => 'Creta', 'reg_no' => 'MH12KT4521']);
    app(JobCardService::class)->create([
        'customer_id' => $customer->id, 'customer_car_id' => $car->id,
        'items' => [['description' => 'Ceramic coating 9H', 'qty' => 1, 'price_paise' => 1_499_900]],
    ], $owner);
});

/** Every GET page a signed-in user can reach, with route parameters filled from the fixture. */
function cspPages(): array
{
    $models = [
        'invoice' => fn () => Invoice::query()->first(),
        'quotation' => fn () => Quotation::query()->first(),
        'customer' => fn () => Customer::query()->first(),
        'product' => fn () => Product::query()->first(),
        'jobCard' => fn () => JobCard::query()->first(),
        'expense' => fn () => Expense::query()->first(),
        'payment' => fn () => Payment::query()->first(),
        'staff' => fn () => User::query()->first(),
        'type' => fn () => 'expenses',
    ];
    $skip = '#^(api/|dev/|webhooks/|login|logout|up$|bill-fonts|b/|q/|sanctum|storage|_test)#';

    $urls = [];
    /** @var RouteDef $r */
    foreach (Route::getRoutes() as $r) {
        if (! in_array('GET', $r->methods(), true) || preg_match($skip, $r->uri())) {
            continue;
        }
        $params = [];
        foreach ($r->parameterNames() as $name) {
            if (! isset($models[$name]) || ! ($value = $models[$name]())) {
                continue 2;
            }
            $params[$name] = $value;
        }
        $urls[] = $r->getName() ? route($r->getName(), $params) : url($r->uri());
    }
    $urls[] = url('/no-such-page'); // the 404 error view
    $urls[] = route('bills.print', [Invoice::query()->first(), 'format' => 'thermal', 'preview' => 1]);
    foreach (['daily', 'staff', 'products', 'gst', 'pnl', 'ca'] as $report) {
        $urls[] = route('reports.index', ['report' => $report]);
    }

    return array_values(array_unique($urls));
}

it('serves no inline script without the nonce and no on*= handler, for every role', function (string $role) {
    $user = staff($role);
    $checked = 0;

    foreach (cspPages() as $url) {
        $res = $this->actingAs($user)->get($url);
        $type = (string) $res->headers->get('Content-Type');
        if (! str_contains($type, 'text/html') || $res->isRedirection()) {
            continue;
        }
        $checked++;
        $html = (string) $res->getContent();
        $csp = (string) $res->headers->get('Content-Security-Policy');
        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $nonce = $m[1] ?? null;
        expect($nonce)->not->toBeNull("no CSP nonce on $url");

        preg_match_all('/<script\b[^>]*>/i', $html, $tags);
        foreach ($tags[0] as $tag) {
            expect(str_contains($tag, 'nonce="'.$nonce.'"'))->toBeTrue("script without the nonce on $url as $role: $tag");
        }
        expect(preg_match('/\son[a-z]+\s*=\s*["\']/i', $html, $handler))
            ->toBe(0, "inline handler on $url as $role: ".($handler[0] ?? ''));
    }

    expect($checked)->toBeGreaterThan(8);
})->with(['owner', 'admin', 'finance', 'sales', 'marketing', 'technician']);

it('keeps the view files free of inline handlers and nonce-less scripts', function () {
    $bad = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    foreach ($it as $file) {
        if (! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }
        $src = file_get_contents($file->getPathname());
        if (preg_match('/<script\b(?![^>]*\bnonce=)[^>]*>/i', $src) || preg_match('/\son[a-z]+="/i', $src)) {
            $bad[] = str_replace(resource_path('views').'/', '', $file->getPathname());
        }
    }

    expect($bad)->toBe([]);
});
