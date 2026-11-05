<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\CustomerImport;
use App\Services\Imports\ContactsReader;
use App\Services\Imports\CustomerImporter;
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
    $this->importer = app(CustomerImporter::class);
});

const OLD_CUSTOMERS_CSV = <<<'CSV'
Jay Ganesh customers 2024,,,,,
Customer Name,Mobile No.,Car Model,Car Number,Area,Remarks
Rahul Pawar,98231 07676,Hyundai Creta,MH 12 AB 1234,Hadapsar,PPF done
Sneha Kulkarni,+91 99813-97676,nexon,mh14cd5678,Wakad,
Bad Number,12345,Swift,,Kharadi,
Rahul Pawar,9823107676,Swift Dzire,MH12XY9999,,second car
No Phone,,Creta,,,
,9876543210,,,,
Amit Shinde,09876 543 211 / 98500 11122,Creta MH 12 KL 4321,,Kothrud,
CSV;

function ciRows(string $csv): array
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $csv);
    try {
        return app(SheetReader::class)->read($path, 'csv');
    } finally {
        unlink($path);
    }
}

function ciTokenFor(int $userId): string
{
    return basename(collect(Storage::disk('local')->allFiles('imports/customers/'.$userId))->first(), '.json');
}

/** Runs the progress page's auto-continue until the import is done. */
function ciRunAll($test, $user, CustomerImport $import): CustomerImport
{
    for ($i = 0; $i < 50 && ! $import->refresh()->isDone(); $i++) {
        $test->actingAs($user)->post(route('customers.import.continue', $import))->assertRedirect(route('customers.import.show', $import));
    }

    return $import->refresh();
}

it('guesses the header row and columns from shop-style headings', function () {
    $g = $this->importer->guess(ciRows(OLD_CUSTOMERS_CSV));

    expect($g['header_line'])->toBe(2)
        ->and($g['mapping'])->toMatchArray(['name' => 0, 'mobile' => 1, 'car' => 2, 'reg_no' => 3, 'area' => 4, 'notes' => 5, 'email' => null]);

    expect($this->importer->guessMapping(['Party Name', 'Contact No', 'Vehicle', 'Reg No', 'City', 'Email ID']))
        ->toMatchArray(['name' => 0, 'mobile' => 1, 'car' => 2, 'reg_no' => 3, 'area' => 4, 'email' => 5]);
    expect($this->importer->guessMapping(['Name', 'Phone', 'Vehicle Number', 'Model']))
        ->toMatchArray(['name' => 0, 'mobile' => 1, 'reg_no' => 2, 'car' => 3]);
});

it('reads mobile numbers written many ways and splits car text', function () {
    expect($this->importer->pickMobile('98231 07676'))->toBe(['+919823107676', []])
        ->and($this->importer->pickMobile('098231-07676'))->toBe(['+919823107676', []])
        ->and($this->importer->pickMobile('9823107676 / 9981397676'))->toBe(['+919823107676', ['+919981397676']])
        ->and($this->importer->pickMobile('020 2687 1234'))->toBe([null, []])
        ->and(CustomerImporter::splitCar('creta'))->toBe(['Hyundai', 'Creta'])
        ->and(CustomerImporter::splitCar('Swift Dzire white'))->toBe(['Maruti Suzuki', 'Swift'])
        ->and(CustomerImporter::splitCar('Hyundai Grand i10 Nios'))->toBe(['Hyundai', 'Grand i10 Nios']);
});

it('previews rows and explains the red ones without saving anything', function () {
    $preview = collect($this->importer->preview(ciRows(OLD_CUSTOMERS_CSV), 2, $this->importer->guess(ciRows(OLD_CUSTOMERS_CSV))['mapping']))->keyBy('line');

    expect($preview[3]['action'])->toBe('create')
        ->and($preview[4]['action'])->toBe('create')
        ->and($preview[5]['action'])->toBe('error')
        ->and($preview[5]['errors'][0])->toContain('isn’t a 10-digit Indian mobile')
        ->and($preview[6]['action'])->toBe('error')
        ->and($preview[6]['errors'][0])->toBe('Same mobile as row 3. Its car is added there.')
        ->and($preview[3]['data']['cars'])->toHaveCount(2)
        ->and($preview[7]['errors'])->toBe(['No mobile number.'])
        ->and($preview[8]['errors'])->toBe(['No name. Add a name for this number.'])
        ->and($preview[9]['data']['mobile'])->toBe('+919876543211')
        ->and($preview[9]['data']['alt_mobile'])->toBe('+919850011122')
        ->and($preview[9]['data']['cars'][0])->toBe(['make' => 'Hyundai', 'model' => 'Creta', 'reg_no' => 'MH12KL4321']);

    expect(Customer::count())->toBe(0);
});

it('imports end to end: customers, cars, source, opt-in defaults and a summary', function () {
    $owner = staff('owner');
    $this->actingAs($owner)->post(route('customers.import.upload'), ['file' => UploadedFile::fake()->createWithContent('old.csv', OLD_CUSTOMERS_CSV)])
        ->assertRedirect();
    $token = ciTokenFor($owner->id);

    $this->actingAs($owner)->get(route('customers.import.map', $token))->assertOk()
        ->assertSee('isn’t a 10-digit Indian mobile')
        ->assertSee('Same mobile as row 3')
        ->assertSee('No mobile number.')
        ->assertSee('Import 3 customers')
        ->assertSee('no offers until they agree');

    $this->actingAs($owner)->post(route('customers.import.start', $token), [
        'header' => 2, 'map' => ['name' => 0, 'mobile' => 1, 'car' => 2, 'reg_no' => 3, 'area' => 4, 'notes' => 5],
    ])->assertRedirect();

    $import = CustomerImport::firstOrFail();
    $this->actingAs($owner)->get(route('customers.import.show', $import))->assertOk()->assertSee('Importing customers');
    $import = ciRunAll($this, $owner, $import);

    expect($import->created)->toBe(3)
        ->and($import->cars_added)->toBe(4)
        ->and($import->skipped)->toBe(4)
        ->and(collect($import->problems)->pluck('line')->all())->toBe([5, 6, 7, 8]);

    $rahul = Customer::where('mobile', '+919823107676')->with('cars')->first();
    expect($rahul->name)->toBe('Rahul Pawar')
        ->and($rahul->source)->toBe('import')
        ->and($rahul->tags)->toBe(['imported'])
        ->and($rahul->whatsapp_opt_in)->toBeTrue()
        ->and((bool) $rahul->getRawOriginal('marketing_opt_in'))->toBeFalse()
        ->and($rahul->getRawOriginal('marketing_opt_in'))->not->toBeNull()
        ->and($rahul->cars->pluck('reg_no')->sort()->values()->all())->toBe(['MH12AB1234', 'MH12XY9999'])
        ->and($rahul->cars->firstWhere('reg_no', 'MH12AB1234')->make)->toBe('Hyundai');

    expect(Customer::where('mobile', '+919981397676')->first()->cars->first()->only(['make', 'model', 'reg_no']))
        ->toBe(['make' => 'Tata', 'model' => 'Nexon', 'reg_no' => 'MH14CD5678']);

    $this->actingAs($owner)->get(route('customers.import.show', $import))->assertOk()
        ->assertSee('Import finished')->assertSee('new customers')->assertSee('Row 5');
    expect(Storage::disk('local')->exists("imports/customers/{$owner->id}/{$token}.json"))->toBeFalse();
});

it('never overwrites an existing customer, only fills empty fields, and dedupes cars', function () {
    $owner = staff('owner');
    $existing = Customer::create(['branch_id' => Branch::first()->id, 'name' => 'Rahul P (counter)', 'mobile' => '9823107676', 'area' => 'Magarpatta', 'source' => 'walk_in']);
    $existing->cars()->create(['make' => 'Hyundai', 'model' => 'Creta', 'reg_no' => 'MH12AB1234']);

    $csv = "Name,Mobile,Car,Number plate,Area,Notes,Email\nRahul Pawar,+91 98231 07676,Creta,MH 12 AB 1234,Hadapsar,Old customer,rahul@example.com\n";
    $this->actingAs($owner)->post(route('customers.import.upload'), ['file' => UploadedFile::fake()->createWithContent('c.csv', $csv)]);
    $token = ciTokenFor($owner->id);
    $this->actingAs($owner)->get(route('customers.import.map', $token))->assertSee('Already a customer: Rahul P (counter).');
    $this->actingAs($owner)->post(route('customers.import.start', $token));
    $import = ciRunAll($this, $owner, CustomerImport::firstOrFail());

    $existing->refresh();
    expect($import->updated)->toBe(1)
        ->and($import->created)->toBe(0)
        ->and($import->cars_added)->toBe(0)
        ->and($existing->name)->toBe('Rahul P (counter)')   // kept
        ->and($existing->area)->toBe('Magarpatta')          // kept
        ->and($existing->source)->toBe('walk_in')           // kept
        ->and($existing->notes)->toBe('Old customer')       // was empty: filled
        ->and($existing->email)->toBe('rahul@example.com')  // was empty: filled
        ->and($existing->getRawOriginal('marketing_opt_in'))->toBeNull()
        ->and(CustomerCar::count())->toBe(1);

    // Same file again changes nothing.
    $this->actingAs($owner)->post(route('customers.import.upload'), ['file' => UploadedFile::fake()->createWithContent('c.csv', $csv)]);
    $token2 = collect(Storage::disk('local')->allFiles('imports/customers/'.$owner->id))->map(fn ($f) => basename($f, '.json'))->first();
    $this->actingAs($owner)->post(route('customers.import.start', $token2));
    $again = ciRunAll($this, $owner, CustomerImport::latest('id')->first());
    expect($again->unchanged)->toBe(1)->and($again->updated)->toBe(0);
});

it('does not add a plate that already belongs to another customer', function () {
    $owner = staff('owner');
    $other = Customer::create(['branch_id' => Branch::first()->id, 'name' => 'Old Owner', 'mobile' => '9000000001']);
    $other->cars()->create(['make' => 'Hyundai', 'model' => 'Creta', 'reg_no' => 'MH12AB1234']);

    $csv = "Name,Mobile,Car,Number plate\nNew Owner,9823107676,Creta,MH12AB1234\n";
    $this->actingAs($owner)->post(route('customers.import.upload'), ['file' => UploadedFile::fake()->createWithContent('c.csv', $csv)]);
    $this->actingAs($owner)->post(route('customers.import.start', ciTokenFor($owner->id)));
    $import = ciRunAll($this, $owner, CustomerImport::firstOrFail());

    expect($import->created)->toBe(1)->and($import->cars_added)->toBe(0)
        ->and($import->problems[0]['reason'])->toContain('already saved under Old Owner');
});

it('parses phone contacts (.vcf) from Android and iPhone', function () {
    $vcf = implode("\r\n", [
        'BEGIN:VCARD', 'VERSION:2.1',
        'N;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:=E0=A4=AA=E0=A4=B5=E0=A4=BE=E0=A4=B0;=E0=A4=B0=E0=A4=BE=E0=A4=B9=E0=A5=81=E0=A4=B2;;;',
        'FN;CHARSET=UTF-8;ENCODING=QUOTED-PRINTABLE:=E0=A4=B0=E0=A4=BE=E0=A4=B9=E0=A5=81=E0=A4=B2 =E0=A4=AA=E0=A4=B5=E0=A4=BE=E0=A4=B0',
        'TEL;HOME:020 2687 0000',
        'TEL;CELL:+91 98231 07676',
        'END:VCARD',
        'BEGIN:VCARD', 'VERSION:3.0',
        'N:Kulkarni;Sneha;;;', 'FN:Sneha Kulkarni (Nexon)',
        'item1.TEL;type=CELL;type=VOICE;type=pref:099813 97676',
        'EMAIL;type=INTERNET:sneha@example.com',
        'ADR;type=HOME:;;12 Lane;Wakad;Pune;411057;India',
        'NOTE:Ceramic coating\, Oct 2025',
        'END:VCARD',
        'BEGIN:VCARD', 'VERSION:4.0',
        'FN:Long Folded Name',
        '  Continues',
        'TEL;VALUE=uri;TYPE=cell:tel:+91-98765-43210',
        'END:VCARD',
        'BEGIN:VCARD', 'VERSION:3.0', 'ORG:Empty card', 'END:VCARD',
    ]);

    $rows = app(ContactsReader::class)->fromVcf($vcf);
    expect($rows[1])->toBe(ContactsReader::CONTACT_HEADERS)
        ->and($rows[2])->toBe(['राहुल पवार', '+91 98231 07676', '020 2687 0000', '', '', ''])
        ->and($rows[3])->toBe(['Sneha Kulkarni (Nexon)', '099813 97676', '', 'sneha@example.com', 'Wakad', 'Ceramic coating, Oct 2025'])
        ->and($rows[4][0])->toBe('Long Folded Name Continues')
        ->and($rows[4][1])->toBe('+91-98765-43210')
        ->and($rows)->toHaveCount(3 + 1);

    // Through the upload: columns are found by themselves.
    $owner = staff('owner');
    $this->actingAs($owner)->post(route('customers.import.upload'), ['file' => UploadedFile::fake()->createWithContent('contacts.vcf', $vcf)])->assertRedirect();
    $this->actingAs($owner)->get(route('customers.import.map', ciTokenFor($owner->id)))->assertOk()
        ->assertSee('Phone contacts (.vcf)')
        ->assertSee('Import 3 customers');
});

it('reads a Google Contacts CSV export', function () {
    $csv = "First Name,Middle Name,Last Name,Nickname,Notes,E-mail 1 - Label,E-mail 1 - Value,Phone 1 - Label,Phone 1 - Value,Phone 2 - Label,Phone 2 - Value,Address 1 - City\n"
        ."Rahul,,Pawar,,PPF,* Home,rahul@example.com,Mobile,+91 98231 07676 ::: 020 2687 0000,Work,99813 97676,Hadapsar\n"
        .",,,Garage Mama,,,,Mobile,98765 43210,,,\n";
    $path = tempnam(sys_get_temp_dir(), 'gc');
    file_put_contents($path, $csv);
    [$rows, $kind] = app(ContactsReader::class)->read($path, 'csv');
    unlink($path);

    expect($kind)->toBe('google')
        ->and($rows[1])->toBe(ContactsReader::CONTACT_HEADERS)
        ->and($rows[2])->toBe(['Rahul Pawar', '+91 98231 07676', '020 2687 0000 / 99813 97676', 'rahul@example.com', 'Hadapsar', 'PPF'])
        ->and($rows[3][0])->toBe('Garage Mama');

    $preview = $this->importer->preview($rows, 1, $this->importer->guess($rows)['mapping']);
    expect($preview[0]['data']['alt_mobile'])->toBe('+919981397676');
});

it('reads an Excel file and imports more rows than one slice', function () {
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    $w = new Writer;
    $w->openToFile($path);
    $w->addRow(Row::fromValues(['Name', 'Mobile', 'Car']));
    for ($i = 0; $i < CustomerImporter::CHUNK + 30; $i++) {
        $w->addRow(Row::fromValues(['Customer '.$i, 9_100_000_000 + $i, 'Creta']));
    }
    $w->close();

    $owner = staff('admin');
    $this->actingAs($owner)->post(route('customers.import.upload'), ['file' => new UploadedFile($path, 'list.xlsx', null, null, true)])->assertRedirect();
    $this->actingAs($owner)->post(route('customers.import.start', ciTokenFor($owner->id)))->assertRedirect();

    $import = CustomerImport::firstOrFail();
    $this->actingAs($owner)->post(route('customers.import.continue', $import));
    expect($import->refresh()->processed)->toBe(CustomerImporter::CHUNK)->and($import->isDone())->toBeFalse();

    $import = ciRunAll($this, $owner, $import);
    expect($import->created)->toBe(CustomerImporter::CHUNK + 30)
        ->and(Customer::count())->toBe(CustomerImporter::CHUNK + 30)
        ->and(CustomerCar::count())->toBe(CustomerImporter::CHUNK + 30);
    @unlink($path);
});

it('offers a template and is only for owner and admin', function () {
    $this->actingAs(staff('owner'))->get(route('customers.import.template'))->assertOk()
        ->assertDownload('jay-ganesh-customers-template.csv');
    $this->actingAs(staff('owner'))->get(route('customers.import'))->assertOk()->assertSee('WhatsApp: service yes, offers no');

    foreach (['sales', 'finance', 'technician'] as $role) {
        $this->actingAs(staff($role))->get(route('customers.import'))->assertForbidden();
    }
});
