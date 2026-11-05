<?php

use App\Http\Controllers\SearchController;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\FollowUp;
use App\Models\JobCard;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Dashboard\FollowUps;
use App\Support\Dashboard\HomeData;
use App\Support\Dashboard\LowStock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/*
| Integration: the merged modules working together on Home, the menu and global search.
| The shop is filled by the modules' own demo seeders (database/seeders/Demo), the same data a
| developer sees after `migrate:fresh --seed`.
*/

uses(RefreshDatabase::class);

function seedDemoShop(): array
{
    test()->seed(RolesAndPermissionsSeeder::class);
    $branch = Branch::create(['name' => 'Jay Ganesh Car Accessories', 'code' => 'JG', 'state_code' => '27']);
    test()->seed(SettingsSeeder::class);

    $users = [];
    foreach (['owner', 'admin', 'finance', 'sales', 'technician'] as $role) {
        $users[$role] = User::create(['name' => ucfirst($role).' (demo)', 'email' => "$role@crm.test", 'password' => 'password', 'branch_id' => $branch->id, 'is_active' => true]);
        $users[$role]->syncRoles([$role]);
    }
    Customer::factory()->count(30)->create(['branch_id' => $branch->id])
        ->each(fn (Customer $c) => CustomerCar::factory()->create(['customer_id' => $c->id]));

    foreach (['10_ProductsDemoSeeder', '30_QuotationsDemoSeeder', '50_JobCardsDemoSeeder', '60_FinanceDemoSeeder', '70_OffersDemoSeeder'] as $file) {
        require_once database_path("seeders/Demo/$file.php");
        test()->seed('Database\\Seeders\\Demo\\'.preg_replace('/^\d+_/', '', $file));
    }
    $users['marketing'] = User::role('marketing')->orderBy('id')->firstOrFail();
    Cache::flush();

    return $users;
}

/** Internal links (same host) in a page, without query-less duplicates of tel:/wa.me links. */
function internalLinks(string $html): array
{
    preg_match_all('/href="(http:\/\/localhost[^"#]*)"/', $html, $m);

    // Static files (icons, the manifest, built assets) are not routes.
    $pages = array_filter($m[1], fn ($u) => ! preg_match('/\.(png|svg|ico|webmanifest|json|css|js)$/', (string) parse_url($u, PHP_URL_PATH)));

    return array_values(array_unique(array_map(fn ($u) => html_entity_decode($u), $pages)));
}

it('shows real module data on every role\'s Home', function (string $role, array $see) {
    $users = seedDemoShop();
    $res = $this->actingAs($users[$role])->get('/')->assertOk();
    foreach ($see as $text) {
        $res->assertSee($text, false);
    }
    $res->assertDontSee('Switches on with Quotations')->assertDontSee('Switches on with Job cards');
})->with([
    'owner' => ['owner', ['overdue follow-up', 'low on stock', 'Cars in workshop', 'Bay view', 'Money today', 'Expenses', 'Offers', 'Diwali']],
    'admin' => ['admin', ['overdue follow-up', 'Cars in workshop', 'Money today']],
    'finance' => ['finance', ['Money today', 'Cash expected', 'Add expense', 'Cash close', 'Dues list']],
    'sales' => ['sales', ['Follow-ups today', 'data-overdue', 'Log result', 'Waiting on me', 'open quotation', 'Low stock alerts', 'Cars in workshop', 'Car in']],
    'marketing' => ['marketing', ['Follow-ups today', 'Offers', 'Send offer', 'new enquir']],
    'technician' => ['technician', ['My jobs', 'Ready for QC']],
]);

it('feeds Home from the module helpers', function () {
    $users = seedDemoShop();

    $sales = (new HomeData($users['sales']))->toArray();
    expect($sales['followUps']['count'])->toBe(FollowUps::countForUser($users['sales']))->toBeGreaterThan(0)
        ->and($sales['followUps']['overdue'])->toBeGreaterThan(0)
        ->and($sales['followUps']['rows'][0]['overdue'])->toBeTrue() // overdue first
        ->and($sales['lowStock'])->toHaveCount(min(5, LowStock::count()))
        ->and($sales['workshop']['in_shop'])->toBe(JobCard::query()->open()->count());

    $tech = (new HomeData($users['technician']))->toArray();
    $mine = JobCard::query()->where('technician_id', $users['technician']->id)->whereIn('status', JobCard::OPEN)->pluck('number');
    expect($tech['jobs'])->not->toBeEmpty()
        ->and(collect($tech['jobs'])->pluck('number')->sort()->values()->all())->toEqual($mine->sort()->values()->all())
        ->and(collect($tech['jobs'])->first())->not->toHaveKey('paise');

    $owner = (new HomeData($users['owner']))->toArray();
    expect($owner['finance']['expenses']['count'])->toBeGreaterThanOrEqual(0)
        ->and($owner['offers'])->not->toBeEmpty()
        ->and($owner['attention'][0]['title'])->toContain('overdue follow-up');

    expect((new HomeData($users['finance']))->toArray()['finance'])->not->toBeNull()
        ->and((new HomeData($users['sales']))->toArray()['offers'])->toBeNull();
});

it('puts Follow-ups above the stats on the sales Home when any are due', function () {
    $users = seedDemoShop();
    $html = $this->actingAs($users['sales'])->get('/')->getContent();
    $main = substr($html, strpos($html, '<main'));
    expect(strpos($main, 'Follow-ups today'))->toBeLessThan(strpos($main, 'My today'));
});

it('links every Home tile and menu item to a route that exists, for every role', function () {
    $users = seedDemoShop();

    foreach (HomeData::tileRouteNames() as $name) {
        $defined = Route::has($name);
        foreach ($users as $user) {
            $keys = collect((new HomeData($user))->tiles())->pluck('url')->filter()->all();
            if (! $defined) {
                expect($keys)->not->toContain(url('/'.$name));
            }
        }
    }

    foreach ($users as $role => $user) {
        foreach ((new HomeData($user))->tiles() as $tile) {
            expect($tile['url'])->not->toBeNull("$role tile {$tile['key']}");
        }
        foreach (require resource_path('views/partials/nav.php') as $item) {
            foreach ([$item, ...$item['children']] as $link) {
                if (! empty($link['soon'])) {
                    continue;
                }
                expect(Route::has($link['route']))->toBeTrue("menu {$link['label']}");
            }
        }

        // Every internal link on this person's Home opens for them: no 404, no 403, no 500.
        $html = $this->actingAs($user)->get('/')->assertOk()->getContent();
        foreach (internalLinks($html) as $url) {
            if (str_contains($url, '/followups/') && str_ends_with(parse_url($url, PHP_URL_PATH), '/whatsapp')) {
                continue; // opens wa.me and logs the contact: covered by the follow-ups tests
            }
            Route::getRoutes()->match(Request::create($url)); // throws on a missing route
            $status = $this->actingAs($user)->get($url)->getStatusCode();
            expect($status)->toBeLessThan(400, "$role: $url gave $status");
        }
    }
});

it('gives technicians only My job cards in the menu', function () {
    $users = seedDemoShop();
    $html = $this->actingAs($users['technician'])->get('/')->assertOk()->getContent();
    $sidebar = substr($html, 0, strpos($html, '</aside>'));
    expect($sidebar)->toContain('My job cards')
        ->not->toContain('href="'.route('jobcards.index').'"')
        ->not->toContain('>Home<')
        ->not->toContain('Customers');
});

it('highlights Stock on the stock check page and shows Offers and Finance links to the owner', function () {
    $users = seedDemoShop();
    $html = $this->actingAs($users['owner'])->get(route('stock.check'))->assertOk()->getContent();
    expect($html)->toMatch('/href="'.preg_quote(route('products.index'), '/').'"[^>]*nav-link-active|nav-link-active[^>]*href="'.preg_quote(route('products.index'), '/').'"/')
        ->toContain('href="'.route('offers.index').'"')
        ->toContain('href="'.route('finance.expenses.index').'"')
        ->toContain('href="'.route('finance.cash-close').'"');

    $sales = $this->actingAs($users['sales'])->get('/')->getContent();
    expect($sales)->not->toContain('href="'.route('leads.index').'"')->not->toContain('href="'.route('offers.index').'"');
});

it('shows the follow-ups count as a red badge, cached for a minute', function () {
    $users = seedDemoShop();
    $sales = $users['sales'];
    $count = FollowUps::countForUser($sales);
    expect($count)->toBeGreaterThan(0)->and(HomeData::followUpBadge($sales))->toBe($count);

    $this->actingAs($sales)->get('/')
        ->assertSee('data-badge="followups" aria-label="'.$count.' due">'.$count.'</span>', false);

    // A new follow-up due today shows after the cache expires, not on every page load.
    $quote = Quotation::query()->where('assigned_to', $sales->id)->whereIn('status', Quotation::ACTIVE)
        ->whereDoesntHave('followUps', fn ($f) => $f->pending())->first()
        ?? Quotation::query()->where('assigned_to', $sales->id)->first();
    FollowUp::query()->where('subject_type', Quotation::class)->where('subject_id', $quote->id)->delete();
    $quote->update(['status' => Quotation::OPEN]);
    FollowUp::create(['subject_type' => Quotation::class, 'subject_id' => $quote->id, 'assigned_to' => $sales->id, 'due_on' => now('Asia/Kolkata')->toDateString(), 'status' => FollowUp::PENDING, 'step' => 1, 'branch_id' => $quote->branch_id, 'customer_id' => $quote->customer_id]);
    $fresh = FollowUps::countForUser($sales);
    expect(HomeData::followUpBadge($sales))->toBe($count);
    Cache::forget(HomeData::badgeCacheKey($sales));
    expect(HomeData::followUpBadge($sales))->toBe($fresh);

    // Technicians never see it.
    expect(HomeData::followUpBadge($users['technician']))->toBeNull();
});

it('finds a quotation by number, a product by its alias and a job card by plate', function () {
    $users = seedDemoShop();
    $search = app(SearchController::class);

    $quote = Quotation::query()->where('assigned_to', $users['sales']->id)->firstOrFail();
    $digits = substr($quote->number, -4);
    $groups = $search->search($quote->number, $users['sales']);
    expect(collect($groups['quotations']['items'])->pluck('title'))->toContain($quote->number);
    expect(collect($search->search($digits, $users['owner'])['quotations']['items'] ?? [])->pluck('title'))->toContain($quote->number);

    $product = Product::query()->whereNotNull('aliases')->where('aliases', '!=', '')->firstOrFail();
    $alias = $product->aliasList()[0];
    $this->actingAs($users['sales'])->get(route('search', ['q' => $alias, 'stay' => 1]))->assertOk()
        ->assertSee($product->name)->assertSee(route('products.show', $product), false);

    $card = JobCard::query()->whereNotNull('customer_car_id')->with('car')->firstOrFail();
    $plate = $card->car->reg_no;
    $res = $this->actingAs($users['owner'])->get(route('search', ['q' => strtolower(substr($plate, 0, 4).' '.substr($plate, 4)), 'stay' => 1]))->assertOk();
    $res->assertSee('Job cards')->assertSee($card->number)->assertSee(route('jobcards.show', $card), false);

    // A technician finds only their own cars.
    $other = JobCard::query()->where(fn ($q) => $q->whereNull('technician_id')->orWhere('technician_id', '!=', $users['technician']->id))->whereNotNull('customer_car_id')->with('car')->first();
    if ($other) {
        expect(collect($search->search($other->number, $users['technician'])['jobcards']['items'] ?? [])->pluck('url'))
            ->not->toContain(route('jobcards.show', $other));
    }
});

it('lists quotations and job cards in the customer history, with links', function () {
    $users = seedDemoShop();
    $card = JobCard::query()->firstOrFail();
    $customer = $card->customer;
    $quote = Quotation::query()->create([
        'number' => 'Q-TEST-0001', 'branch_id' => $customer->branch_id, 'customer_id' => $customer->id,
        'assigned_to' => $users['sales']->id, 'created_by' => $users['sales']->id, 'valid_until' => now()->addWeek(), 'total_paise' => 150000,
    ]);

    $this->actingAs($users['owner'])->get(route('customers.show', $customer))->assertOk()
        ->assertSee('Q-TEST-0001')->assertSee(route('quotations.show', $quote), false)
        ->assertSee($card->number)->assertSee(route('jobcards.show', $card), false);
});
