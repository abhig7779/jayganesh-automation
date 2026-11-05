<?php

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\StockMove;
use App\Services\Products\PriceListImporter;
use App\Services\Products\SheetReader;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Branch::create(['name' => 'Main', 'code' => 'JG']);
    Storage::fake('local');
    $this->importer = app(PriceListImporter::class);
});

const SHOP_CSV = <<<'CSV'
Jay Ganesh price list Nov 2026,,,,,,,
Item Name,Make,Rate,M.R.P.,GST %,HSN/SAC,Qty,Item Code
DHC Gleam 2.0 LED headlight H4,DHC,"₹4,999",6499,18%,85395200,12 pcs,DHC-GLM2-H4
7D floor mats Creta,7D,3200/-,3800,0.18,87089900,6,
Fitting charge,,500,,18,998714,,SRV-FIT
LED & LIGHTS,,,,,,,
Broken row,DHC,abc,,15,12,two,
,,,,,,,
Seat cover Creta,Autoform,9000,8500,18,,1,
DHC Gleam 2.0 LED headlight H4 again,DHC,4999,,18,,1,DHC-GLM2-H4
CSV;

function uploadCsv(string $csv, string $name = 'price-list.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $csv);
}

/** Parse CSV text the way an upload is parsed. */
function csvRows(string $csv): array
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $csv);
    try {
        return app(SheetReader::class)->read($path, 'csv');
    } finally {
        unlink($path);
    }
}

it('guesses the header row and columns from shop-style headings', function () {
    $rows = csvRows(SHOP_CSV);
    $g = $this->importer->guess($rows);

    expect($g['header_line'])->toBe(2)
        ->and($g['mapping'])->toMatchArray([
            'name' => 0, 'brand' => 1, 'price' => 2, 'mrp' => 3, 'gst' => 4, 'hsn' => 5, 'stock' => 6, 'sku' => 7,
            'cost' => null, 'aliases' => null,
        ]);

    $alt = $this->importer->guessMapping(['Product', 'Brand', 'Selling Price', 'MRP', 'Tax', 'HSN Code', 'Stock', 'SKU', 'Also called', 'Purchase Price']);
    expect($alt)->toMatchArray(['name' => 0, 'brand' => 1, 'price' => 2, 'mrp' => 3, 'gst' => 4, 'hsn' => 5, 'stock' => 6, 'sku' => 7, 'aliases' => 8, 'cost' => 9]);

    // Only an MRP column: sell at MRP.
    expect($this->importer->guessMapping(['Description', 'MRP']))->toMatchArray(['name' => 0, 'price' => 1, 'mrp' => 1]);
});

it('reads money and GST written many ways', function () {
    expect($this->importer->money('₹4,999'))->toBe(499_900)
        ->and($this->importer->money('Rs. 1499/-'))->toBe(149_900)
        ->and($this->importer->money('4999.0000001'))->toBe(499_900)
        ->and($this->importer->money('1,23,450.50'))->toBe(12_345_050)
        ->and($this->importer->money('abc'))->toBeNull()
        ->and($this->importer->gst('18%'))->toBe(18.0)
        ->and($this->importer->gst('0.28'))->toBe(28.0)
        ->and($this->importer->gst('GST 5'))->toBe(5.0)
        ->and($this->importer->gst('15'))->toBeNull();
});

it('previews rows, explains the red ones, then imports with a summary', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->post(route('products.import.upload'), ['file' => uploadCsv(SHOP_CSV)])
        ->assertRedirect();
    $token = basename(collect(Storage::disk('local')->allFiles('imports/'.$owner->id))->first(), '.json');

    $page = $this->actingAs($owner)->get(route('products.import.map', $token))->assertOk()
        ->assertSee('Price "abc" is not an amount')
        ->assertSee('GST "15" should be 0, 5, 12, 18 or 28')
        ->assertSee('HSN "12" should be 4 to 8 digits')
        ->assertSee('Stock "two" is not a whole number')
        ->assertSee('No price (looks like a heading row)')
        ->assertSee('Selling price is more than MRP')
        ->assertSee('Same item as row 3')
        ->assertSee('Import 3 items');
    expect(Product::count())->toBe(0); // nothing saved before confirming

    $this->actingAs($owner)->post(route('products.import.commit', $token), [
        'header' => 2, 'map' => ['name' => 0, 'brand' => 1, 'price' => 2, 'mrp' => 3, 'gst' => 4, 'hsn' => 5, 'stock' => 6, 'sku' => 7],
    ])->assertRedirect(route('products.import'));

    $summary = session('import_summary');
    expect($summary['created'])->toBe(3)
        ->and($summary['updated'])->toBe(0)
        ->and(collect($summary['skipped'])->pluck('line')->all())->toBe([6, 7, 9, 10]);

    $gleam = Product::where('sku', 'DHC-GLM2-H4')->first();
    expect($gleam->price_paise)->toBe(499_900)
        ->and($gleam->mrp_paise)->toBe(649_900)
        ->and($gleam->brand->name)->toBe('DHC')
        ->and($gleam->on_hand)->toBe(12)
        ->and(StockMove::where('product_id', $gleam->id)->value('type'))->toBe('opening')
        ->and((float) Product::where('name', '7D floor mats Creta')->value('gst_rate'))->toBe(18.0)
        ->and(Product::where('sku', 'SRV-FIT')->first()->type)->toBe('service')
        ->and(Storage::disk('local')->exists("imports/{$owner->id}/{$token}.json"))->toBeFalse();

    $this->actingAs($owner)->get(route('products.import'))->assertSee('new items added');
});

it('changes nothing when the same file is imported again', function () {
    $owner = staff('owner');
    $rows = csvRows(SHOP_CSV);
    $g = $this->importer->guess($rows);

    $first = $this->importer->commit($this->importer->preview($rows, $g['header_line'], $g['mapping'], $owner), $owner);
    $moves = StockMove::count();
    $second = $this->importer->commit($this->importer->preview($rows, $g['header_line'], $g['mapping'], $owner), $owner);

    expect($first['created'])->toBe(3)
        ->and($second['created'])->toBe(0)
        ->and($second['updated'])->toBe(0)
        ->and($second['same'])->toBe(3)
        ->and(Product::count())->toBe(3)
        ->and(StockMove::count())->toBe($moves)
        ->and(Brand::where('name', 'DHC')->count())->toBe(1);
});

it('updates by SKU, or by name + brand when there is no SKU', function () {
    $owner = staff('owner');
    $dhc = Brand::create(['name' => 'DHC']);
    $bySku = Product::create(['name' => 'Old name', 'sku' => 'DHC-GLM2-H4', 'brand_id' => $dhc->id, 'price_paise' => 450_000]);
    $byName = Product::create(['name' => '7D floor mats Creta', 'brand_id' => Brand::create(['name' => '7D'])->id, 'price_paise' => 300_000]);

    $rows = [1 => ['Name', 'Brand', 'Price', 'SKU', 'Stock'], 2 => ['DHC Gleam 2.0 LED headlight H4', 'dhc', '4999', 'dhc-glm2-h4', '5'], 3 => ['7D Floor Mats Creta', '7D', '3200', '', '9']];
    $preview = $this->importer->preview($rows, 1, $this->importer->guessMapping($rows[1]), $owner);
    $summary = $this->importer->commit($preview, $owner);

    expect($summary['updated'])->toBe(2)->and($summary['created'])->toBe(0)
        ->and($bySku->fresh()->name)->toBe('DHC Gleam 2.0 LED headlight H4')
        ->and($bySku->fresh()->price_paise)->toBe(499_900)
        ->and($bySku->fresh()->on_hand)->toBe(0) // stock column only for new items by default
        ->and($byName->fresh()->price_paise)->toBe(320_000);

    // "Stock is today's count" corrects existing items through the ledger.
    $preview = $this->importer->preview($rows, 1, $this->importer->guessMapping($rows[1]), $owner, stockIsCount: true);
    $this->importer->commit($preview, $owner, stockIsCount: true);
    expect($byName->fresh()->on_hand)->toBe(9)
        ->and(StockMove::where('product_id', $byName->id)->value('type'))->toBe('adjustment');
});

it('keeps prices when the importer may not change them', function () {
    $admin = staff('admin'); // inventory.manage without prices.manage
    $p = Product::create(['name' => 'Wash glove', 'sku' => 'CARE-GLOVE', 'price_paise' => 34_900]);
    $rows = [1 => ['Name', 'Price', 'SKU', 'Shelf'], 2 => ['Wash glove', '1', 'CARE-GLOVE', 'F2']];

    $preview = $this->importer->preview($rows, 1, $this->importer->guessMapping($rows[1]), $admin);
    expect($preview[0]['notes'])->toContain('New price not applied (only the owner can change prices)');
    $this->importer->commit($preview, $admin);

    expect($p->fresh()->price_paise)->toBe(34_900)->and($p->fresh()->shelf)->toBe('F2');
});

it('reads an Excel file and merges aliases', function () {
    $path = tempnam(sys_get_temp_dir(), 'pl').'.xlsx';
    $w = new Writer;
    $w->openToFile($path);
    $w->addRow(Row::fromValues(['Product', 'Brand', 'Selling price', 'GST', 'Also called', 'Opening stock']));
    $w->addRow(Row::fromValues(['Car perfume gel', null, 299.0, 18, 'perfume; fragrance, scent', 26]));
    $w->close();

    $owner = staff('owner');
    $this->actingAs($owner)->post(route('products.import.upload'), ['file' => new UploadedFile($path, 'list.xlsx', null, null, true)])->assertRedirect();
    $token = basename(collect(Storage::disk('local')->allFiles('imports/'.$owner->id))->first(), '.json');
    $this->actingAs($owner)->get(route('products.import.map', $token))->assertOk()->assertSee('Car perfume gel')->assertSee('Import 1 item');
    $this->actingAs($owner)->post(route('products.import.commit', $token))->assertRedirect();

    $p = Product::where('name', 'Car perfume gel')->first();
    expect($p->price_paise)->toBe(29_900)->and($p->aliases)->toBe('perfume, fragrance, scent')->and($p->on_hand)->toBe(26);
    expect(Product::query()->search('khush')->count())->toBe(0)->and(Product::query()->search('scent')->count())->toBe(1);
});

it('explains files it cannot read', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->post(route('products.import.upload'), ['file' => UploadedFile::fake()->createWithContent('old.xls', 'x')])
        ->assertSessionHasErrors(['file' => 'Old .xls files can\'t be read. In Excel use File → Save As → "Excel Workbook (.xlsx)" or "CSV", then upload again.']);
    $this->actingAs($owner)->post(route('products.import.upload'), ['file' => UploadedFile::fake()->createWithContent('empty.csv', "Name\n")])
        ->assertSessionHasErrors('file');
    $this->actingAs($owner)->post(route('products.import.upload'), ['file' => UploadedFile::fake()->createWithContent('fake.xlsx', 'not a zip')])
        ->assertSessionHasErrors('file');
});

it('reads a semicolon CSV saved by Excel in Windows-1252', function () {
    $csv = mb_convert_encoding("Name;Price;Brand\nCaf\u{e9} seat cover;1499;Autoform\n", 'Windows-1252', 'UTF-8');
    $rows = csvRows($csv);
    expect(array_values($rows)[1])->toBe(['Café seat cover', '1499', 'Autoform']);
});

it('lets only inventory managers import, and offers a template', function () {
    $this->actingAs(staff('sales'))->get(route('products.import'))->assertForbidden();
    $res = $this->actingAs(staff('admin'))->get(route('products.import.template'))->assertOk();
    expect($res->streamedContent())->toContain('Name,Brand,Category,"Selling price",MRP,"GST %",HSN,Stock,SKU,"Also called"');

    // Someone else's upload is not reachable.
    $owner = staff('owner');
    $this->actingAs($owner)->post(route('products.import.upload'), ['file' => uploadCsv(SHOP_CSV)]);
    $token = basename(collect(Storage::disk('local')->allFiles('imports/'.$owner->id))->first(), '.json');
    $this->actingAs(staff('admin'))->get(route('products.import.map', $token))->assertNotFound();
});
