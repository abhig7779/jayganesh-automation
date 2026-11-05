<?php

use App\Http\Controllers\Finance\DuesController;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Services\Finance\Period;
use App\Services\Finance\ReportBuilder;
use App\Services\Finance\SalesQueries;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FinanceFixture;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SettingsSeeder::class);
    $this->branch = Branch::create(['name' => 'Main', 'code' => 'JG']);
    $this->travelTo(CarbonImmutable::parse('2026-11-05 18:00', 'Asia/Kolkata'));
});

/** Read every sheet of an xlsx into [sheetName => rows]. */
function financeReadXlsx(string $path): array
{
    $options = new Options;
    $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
    $reader = new Reader($options);
    $reader->open($path);
    $out = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $out[$sheet->getName()][] = $row->toArray();
        }
    }
    $reader->close();

    return $out;
}

describe('with the billing tables', function () {
    beforeEach(function () {
        $this->f = FinanceFixture::build($this->branch->id);
        $this->today = Period::preset('today');
        $this->month = Period::preset('this_month');
    });

    it('sums the day to the paisa', function () {
        expect(SalesQueries::summary($this->today, $this->branch->id))->toBe([
            'bills' => 2, 'sales_paise' => 1189900, 'discount_paise' => 0, 'avg_bill_paise' => 594950,
            'cancelled_count' => 1, 'cancelled_paise' => 99900,
            'modes' => ['cash' => 639900, 'upi' => 500000, 'card' => 50000, 'bank' => 0],
            'collected_paise' => 1189900, 'dues_collected_paise' => 50000,
        ]);
    });

    it('splits daily sales by day', function () {
        $rows = SalesQueries::dailySales(Period::between('2026-11-04', '2026-11-05'), $this->branch->id);
        expect($rows)->toBe([
            ['date' => '2026-11-04', 'bills' => 1, 'sales_paise' => 499900, 'cash_paise' => 0, 'upi_paise' => 499900, 'card_paise' => 0, 'bank_paise' => 0, 'avg_bill_paise' => 499900, 'cancelled_count' => 0, 'cancelled_paise' => 0],
            ['date' => '2026-11-05', 'bills' => 2, 'sales_paise' => 1189900, 'cash_paise' => 639900, 'upi_paise' => 500000, 'card_paise' => 50000, 'bank_paise' => 0, 'avg_bill_paise' => 594950, 'cancelled_count' => 1, 'cancelled_paise' => 99900],
        ]);

        $this->actingAs(staff('finance'))->get(route('reports.index', ['report' => 'daily', 'preset' => 'today']))
            ->assertOk()->assertSee('₹11,899')->assertSee('₹5,949.50')->assertSee('Day by day');
    });

    it('splits sales by staff', function () {
        expect(SalesQueries::salesByStaff($this->today, $this->branch->id))->toBe([
            ['user_id' => $this->f['sales']->id, 'name' => 'Rohit Shinde', 'bills' => 1, 'sales_paise' => 1139900, 'avg_bill_paise' => 1139900, 'cancelled_count' => 0],
            ['user_id' => $this->f['owner']->id, 'name' => 'Prakash Jadhav', 'bills' => 1, 'sales_paise' => 50000, 'avg_bill_paise' => 50000, 'cancelled_count' => 1],
        ]);
        $this->actingAs(staff('admin'))->get(route('reports.index', ['report' => 'staff']))->assertOk()->assertSee('Rohit Shinde');
    });

    it('ranks products and categories with margins from the cost price', function () {
        $p = SalesQueries::topProducts($this->today, $this->branch->id);
        expect(array_column($p, 'name'))->toBe(['7D floor mats', 'DHC Gleam 2.0 LED', 'Fitting charge'])
            ->and($p[0])->toMatchArray(['qty' => 2, 'sales_paise' => 640000, 'taxable_paise' => 500000, 'cost_paise' => null, 'margin_paise' => null, 'margin_pct' => null])
            ->and($p[1])->toMatchArray(['qty' => 1, 'sales_paise' => 499900, 'taxable_paise' => 423644, 'cost_paise' => 300000, 'margin_paise' => 123644, 'margin_pct' => 29.2])
            ->and($p[2])->toMatchArray(['qty' => 1, 'sales_paise' => 50000, 'taxable_paise' => 42373, 'cost_paise' => 10000, 'margin_paise' => 32373, 'margin_pct' => 76.4]);

        $c = SalesQueries::topCategories($this->today, $this->branch->id);
        expect(array_column($c, 'name'))->toBe(['Mats', 'Lighting', 'No category'])
            ->and(array_column($c, 'sales_paise'))->toBe([640000, 499900, 50000]);

        // Mats (no cost price) moved into Lighting: margin counts only the LED, never the uncosted mats.
        Product::query()->where('name', '7D floor mats')->update(['category_id' => Category::where('name', 'Lighting')->value('id')]);
        $lighting = collect(SalesQueries::topCategories($this->today, $this->branch->id))->firstWhere('name', 'Lighting');
        expect($lighting)->toMatchArray(['taxable_paise' => 923644, 'cost_paise' => 300000, 'margin_paise' => 123644, 'margin_pct' => 29.2]);
    });

    it('hides margins from anyone without reports.margins', function () {
        $this->actingAs(staff('finance'))->get(route('reports.index', ['report' => 'products', 'preset' => 'today']))
            ->assertOk()->assertSee('7D floor mats')->assertDontSee('Margin')->assertDontSee('Cost');

        $build = app(ReportBuilder::class)->build('products', $this->today, staff('admin'), $this->branch->id);
        expect(array_keys($build['sections'][0]['columns']))->not->toContain('margin_paise')
            ->and(array_keys($build['sections'][0]['rows'][1]))->not->toContain('cost_paise');

        $this->actingAs(staff('owner'))->get(route('reports.index', ['report' => 'products', 'preset' => 'today']))
            ->assertOk()->assertSee('Margin')->assertSee('₹1,236.44')->assertSee('29.2%');
    });

    it('builds the GST summary by rate and by HSN for the CA', function () {
        expect(SalesQueries::gstByRate($this->month, $this->branch->id))->toBe([
            ['rate' => 18, 'bills' => 3, 'taxable_paise' => 889661, 'cgst_paise' => 41942, 'sgst_paise' => 41941, 'igst_paise' => 76256, 'tax_paise' => 160139, 'total_paise' => 1049800],
            ['rate' => 28, 'bills' => 1, 'taxable_paise' => 500000, 'cgst_paise' => 70000, 'sgst_paise' => 70000, 'igst_paise' => 0, 'tax_paise' => 140000, 'total_paise' => 640000],
        ]);
        expect(SalesQueries::hsnSummary($this->month, $this->branch->id))->toBe([
            ['hsn' => '85122010', 'rate' => 18, 'qty' => 2, 'taxable_paise' => 847288, 'cgst_paise' => 38128, 'sgst_paise' => 38128, 'igst_paise' => 76256, 'total_paise' => 999800],
            ['hsn' => '87089900', 'rate' => 28, 'qty' => 2, 'taxable_paise' => 500000, 'cgst_paise' => 70000, 'sgst_paise' => 70000, 'igst_paise' => 0, 'total_paise' => 640000],
            ['hsn' => '998714', 'rate' => 18, 'qty' => 1, 'taxable_paise' => 42373, 'cgst_paise' => 3814, 'sgst_paise' => 3813, 'igst_paise' => 0, 'total_paise' => 50000],
        ]);

        $this->actingAs(staff('finance'))->get(route('reports.index', ['report' => 'gst', 'preset' => 'this_month']))
            ->assertOk()->assertSee('HSN summary')->assertSee('₹13,896.61')->assertSee('₹762.56');
    });

    it('works out the simple monthly P&L for the owner only', function () {
        $r = app(ReportBuilder::class)->build('pnl', $this->month, $this->f['owner'], $this->branch->id);
        expect($r['sections'][0]['rows'])->toBe([[
            'month' => 'Nov 2026', 'sales_paise' => 1689800, 'gst_paise' => 300139, 'taxable_paise' => 1389661,
            'cost_paise' => 610000, 'gross_paise' => 779661, 'expenses_paise' => 3575000, 'net_paise' => -2795339,
            'uncosted_paise' => 500000,
        ]]);
        expect(array_column($r['sections'][1]['rows'], 'total_paise'))->toBe([3500000, 45000, 30000]);

        $this->actingAs($this->f['owner'])->get(route('reports.index', ['report' => 'pnl']))
            ->assertOk()->assertSee('−₹27,953.39')->assertSee('of sales (before GST) is on items with no cost price');
        $this->actingAs(staff('finance'))->get(route('reports.index', ['report' => 'pnl']))->assertForbidden();
        $this->actingAs(staff('admin'))->get(route('reports.index', ['report' => 'pnl']))->assertForbidden();
    });

    it('ages the dues and writes a polite reminder', function () {
        $dues = SalesQueries::openDues($this->branch->id);
        expect(array_column($dues, 'number'))->toHaveCount(2)
            ->and(array_column($dues, 'age_days'))->toBe([40, 0])
            ->and(array_column($dues, 'bucket'))->toBe(['31_60', '0_15'])
            ->and(array_column($dues, 'due_paise'))->toBe([150000, 50000]);

        $res = $this->actingAs(staff('finance'))->get(route('finance.dues'))->assertOk()
            ->assertSee('Amit Jadhav')->assertSee('Sneha Kulkarni')->assertSee('₹2,000')->assertSee('Remind on WhatsApp');
        expect($res->viewData('customers')->first()['remind_url'])->toStartWith('https://wa.me/919823011245?text=Hello%20Amit');

        $res = $this->actingAs(staff('finance'))->get(route('finance.dues', ['age' => '31_60']));
        expect($res->viewData('customers')->pluck('name')->all())->toBe(['Amit Jadhav']);

        expect(DuesController::reminderText('Amit Jadhav', 150000, ['JG-2526-0005'], 'Jay Ganesh Car Accessories'))
            ->toBe('Hello Amit, greetings from Jay Ganesh Car Accessories. This is a gentle reminder that ₹1,500 is pending on your bill JG-2526-0005. You can pay at the shop or by UPI whenever it suits you. If you have already paid, please ignore this message. Thank you!');
    });

    it('exports a report as a real xlsx with rupee numbers', function () {
        $res = $this->actingAs(staff('finance'))->get(route('reports.export', ['report' => 'gst', 'preset' => 'this_month']));
        $res->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        expect($res->headers->get('content-disposition'))->toContain('jay-ganesh-gst-2026-11-01_to_2026-11-05.xlsx');

        $path = $res->baseResponse->getFile()->getPathname();
        expect(file_get_contents($path, false, null, 0, 2))->toBe('PK'); // zip container
        $sheets = financeReadXlsx($path);
        expect(array_keys($sheets))->toBe(['By GST rate', 'HSN summary'])
            ->and($sheets['By GST rate'][3])->toBe(['GST rate', 'Bills', 'Taxable value', 'CGST', 'SGST', 'IGST', 'Total tax', 'Value incl. GST'])
            ->and($sheets['By GST rate'][4])->toEqual(['18%', 3, 8896.61, 419.42, 419.41, 762.56, 1601.39, 10498.0])
            ->and(end($sheets['HSN summary'])[0])->toBe('Total');
    });

    it('exports the sales and expense registers for Tally', function () {
        $u = staff('finance');
        $res = $this->actingAs($u)->get(route('reports.register', ['type' => 'sales', 'preset' => 'this_month']))->assertOk();
        $rows = financeReadXlsx($res->baseResponse->getFile()->getPathname())['Bills'];
        // title, period, blank, header, then bills C, A, D (cancelled), B in time order, then the total
        expect(array_column(array_slice($rows, 4, 4), 4))->toBe(['paid', 'paid', 'cancelled', 'due'])
            ->and(end($rows)[10])->toEqual(16898.0);

        $res = $this->actingAs($u)->get(route('reports.register', ['type' => 'expenses', 'preset' => 'this_month']))->assertOk();
        $rows = financeReadXlsx($res->baseResponse->getFile()->getPathname())['Expenses'];
        expect(count($rows))->toBe(4 + 3 + 1)->and(end($rows)[4])->toEqual(35750.0);
    });

    it('exports every report without errors', function () {
        $owner = staff('owner');
        foreach (['daily', 'staff', 'products', 'gst', 'pnl'] as $key) {
            $this->actingAs($owner)->get(route('reports.export', ['report' => $key, 'preset' => 'this_month']))->assertOk();
            $this->actingAs($owner)->get(route('reports.index', ['report' => $key, 'preset' => 'last_month']))->assertOk();
        }
        $this->actingAs($owner)->get(route('reports.index', ['report' => 'ca']))->assertOk()->assertSee('Download sales register');
    });
});

it('shows a tidy empty state before billing is installed', function () {
    $u = staff('owner');
    $this->actingAs($u)->get(route('reports.index'))->assertOk()->assertSee('Bills module not installed yet');
    $this->actingAs($u)->get(route('reports.index', ['report' => 'ca']))->assertOk()->assertSee('Download expense register');
    $this->actingAs($u)->get(route('reports.export', ['report' => 'daily']))->assertNotFound();
    $this->actingAs($u)->get(route('reports.register', ['type' => 'expenses']))->assertOk();
    expect(SalesQueries::summary(Period::preset('today'))['bills'])->toBe(0)
        ->and(SalesQueries::openDues())->toBe([]);
});

it('keeps reports away from sales and limits staff totals to reports.all_staff', function () {
    $this->actingAs(staff('sales'))->get(route('reports.index'))->assertForbidden();
    $this->actingAs(staff('sales'))->get(route('reports.export', ['report' => 'daily']))->assertForbidden();
    $this->actingAs(staff('technician'))->get(route('reports.index'))->assertForbidden();

    $limited = staff('finance');
    $limited->revokePermissionTo('reports.all_staff');
    $limited->roles->first()->revokePermissionTo('reports.all_staff');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->actingAs($limited->fresh())->get(route('reports.index', ['report' => 'staff']))->assertForbidden();
    $this->actingAs($limited->fresh())->get(route('reports.index'))->assertOk()->assertDontSee('Sales by staff');
});

it('turns presets into IST day ranges', function () {
    expect(Period::preset('today')->label())->toBe('Thu, 5 Nov 2026')
        ->and(Period::preset('yesterday')->fromDate())->toBe('2026-11-04')
        ->and([Period::preset('this_week')->fromDate(), Period::preset('this_week')->toDate()])->toBe(['2026-11-02', '2026-11-05'])
        ->and([Period::preset('last_month')->fromDate(), Period::preset('last_month')->toDate()])->toBe(['2026-10-01', '2026-10-31'])
        ->and(Period::preset('today')->utcStart()->toDateTimeString())->toBe('2026-11-04 18:30:00')
        ->and(Period::preset('custom', '2026-11-03', '2026-11-01')->fromDate())->toBe('2026-11-01')
        ->and(Period::preset('custom', 'nonsense')->preset)->toBe('today');
});
