<?php

namespace App\Services\Imports;

use App\Models\Customer;
use App\Models\CustomerCar;
use App\Models\CustomerImport;
use App\Models\User;
use App\Services\Customers\CustomerService;
use App\Support\CarModels;
use App\Support\Phone;
use App\Support\RegNo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * "Import customers" from an old Excel sheet or a phone's contacts.
 *
 * 1. guess(): find the header row and which column is which (same approach as the price list import).
 * 2. preview(): check every row. Nothing is written. Red rows: no/bad mobile number, the same
 *    number earlier in the file, or a new customer with no name.
 * 3. commitChunk(): save a slice of the checked rows (the controller calls it repeatedly, so 5,000
 *    rows never run into shared hosting's time limit). Customers are matched by the normalised
 *    mobile. An existing customer only gets fields that are empty today; nothing is overwritten.
 *
 * WhatsApp consent: imported customers get whatsapp_opt_in = true (service messages: bills,
 * receipts, "your car is ready") but marketing_opt_in = false. They never agreed to offers, and
 * WhatsApp's policy needs that opt-in, so they stay out of offer campaigns until they say yes.
 */
class CustomerImporter
{
    public const CHUNK = 250;

    public const TAG = 'imported';

    /** field => [label, header words it is usually called] (first words win ties). */
    public const FIELDS = [
        'name' => ['Name', ['name', 'customer name', 'customer', 'full name', 'client name', 'client', 'party name', 'party', 'contact name', 'display name', 'naam']],
        'mobile' => ['Mobile', ['mobile', 'mobile no', 'mobile number', 'phone', 'phone no', 'phone number', 'contact', 'contact no', 'contact number', 'cell', 'whatsapp', 'whatsapp no', 'whatsapp number', 'mob', 'mob no', 'number', 'tel']],
        'alt_mobile' => ['Other phone', ['other phone', 'alt mobile', 'alternate mobile', 'alternate number', 'phone 2', 'mobile 2', 'second number', 'landline']],
        'car' => ['Car / model', ['car', 'car model', 'model', 'vehicle', 'vehicle model', 'car name', 'gadi', 'make model', 'vehicle name']],
        'reg_no' => ['Number plate', ['number plate', 'car number', 'car no', 'vehicle number', 'vehicle no', 'reg no', 'registration', 'registration no', 'registration number', 'regn no', 'plate', 'rto no', 'gadi number']],
        'area' => ['Area', ['area', 'city', 'location', 'address', 'locality', 'place', 'village', 'town']],
        'email' => ['Email', ['email', 'e mail', 'email id', 'mail']],
        'notes' => ['Notes', ['notes', 'note', 'remarks', 'remark', 'comments', 'comment', 'details', 'work done']],
    ];

    public function __construct(private CustomerService $customers) {}

    // ------------------------------------------------------------------ columns

    /**
     * @param  array<int, list<string>>  $rows
     * @return array{header_line: int|null, mapping: array<string, int|null>}
     */
    public function guess(array $rows): array
    {
        $best = null;
        foreach (array_slice($rows, 0, 10, true) as $line => $cells) {
            $mapping = $this->guessMapping($cells);
            $hits = count(array_filter($mapping, fn ($c) => $c !== null));
            if ($hits >= 2 && ($best === null || $hits > $best[1])) {
                $best = [$line, $hits, $mapping];
            }
        }
        if ($best !== null) {
            return ['header_line' => $best[0], 'mapping' => $best[2]];
        }
        $line = array_key_first($rows);

        return ['header_line' => $line, 'mapping' => $this->guessMapping($line !== null ? $rows[$line] : [])];
    }

    /** @return array<string, int|null> */
    public function guessMapping(array $headers): array
    {
        $norm = array_map(fn ($h) => self::norm((string) $h), $headers);
        $mapping = array_fill_keys(array_keys(self::FIELDS), null);
        $used = [];

        // Pass 1: exact header names; pass 2: the header contains the word ("Customer Mobile No.").
        foreach ([true, false] as $exact) {
            foreach (self::FIELDS as $field => [, $synonyms]) {
                if ($mapping[$field] !== null) {
                    continue;
                }
                foreach ($synonyms as $syn) {
                    foreach ($norm as $col => $h) {
                        if ($h === '' || isset($used[$col])) {
                            continue;
                        }
                        $hit = $exact ? $h === $syn : (strlen($syn) > 3 && preg_match('/(^| )'.preg_quote($syn, '/').'( |$)/', $h));
                        if ($hit) {
                            $mapping[$field] = $col;
                            $used[$col] = true;

                            continue 3;
                        }
                    }
                }
            }
        }

        return $mapping;
    }

    public static function norm(string $h): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($h))));
    }

    // ------------------------------------------------------------------ check

    /**
     * Check every row against the mapping. Nothing is written.
     *
     * action: create | update (adds missing details or a car) | same | error
     *
     * @param  array<int, list<string>>  $rows
     * @param  array<string, int|null>  $mapping
     * @return list<array{line: int, action: string, errors: list<string>, notes: list<string>, data: array, customer_id: int|null}>
     */
    public function preview(array $rows, ?int $headerLine, array $mapping): array
    {
        $parsed = [];
        $mobiles = [];
        foreach ($rows as $line => $cells) {
            if ($headerLine !== null && $line <= $headerLine) {
                continue;
            }
            $get = fn (string $f) => isset($mapping[$f]) && $mapping[$f] !== null ? trim((string) ($cells[(int) $mapping[$f]] ?? '')) : '';
            $row = $this->parseRow($line, $get);
            $parsed[] = $row;
            if ($row['data']['mobile']) {
                $mobiles[] = $row['data']['mobile'];
            }
        }

        // One query per 500 numbers for who already exists, with their cars.
        $existing = collect();
        foreach (array_chunk(array_values(array_unique($mobiles)), 500) as $chunk) {
            $existing = $existing->merge(Customer::query()->whereIn('mobile', $chunk)->with('cars:id,customer_id,model,reg_no')->get()->keyBy('mobile'));
        }

        $first = []; // mobile => index in $out of the first row with it
        $out = [];
        foreach ($parsed as $row) {
            $m = $row['data']['mobile'];
            if ($m !== null && isset($first[$m])) {
                $firstRow = &$out[$first[$m]];
                $row['action'] = 'error';
                $row['errors'][] = 'Same mobile as row '.$firstRow['line'].'.';
                // A second row for the same person usually means a second car: keep it.
                foreach ($row['data']['cars'] as $car) {
                    if (! $this->hasCar($firstRow['data']['cars'], $car)) {
                        $firstRow['data']['cars'][] = $car;
                        $row['errors'][count($row['errors']) - 1] = 'Same mobile as row '.$firstRow['line'].'. Its car is added there.';
                        if ($firstRow['action'] === 'same') {
                            $firstRow['action'] = 'update';
                        }
                        $firstRow['notes'][] = 'Also gets the car from row '.$row['line'].'.';
                    }
                }
                unset($firstRow);
                $out[] = $row;

                continue;
            }

            if ($row['errors'] === []) {
                $customer = $m !== null ? $existing->get($m) : null;
                if ($customer) {
                    $row['customer_id'] = $customer->id;
                    $fills = $this->fills($customer, $row['data']);
                    $newCars = array_values(array_filter($row['data']['cars'], fn ($c) => ! $this->carExists($customer->cars, $c)));
                    $row['action'] = ($fills || $newCars) ? 'update' : 'same';
                    $row['notes'][] = 'Already a customer: '.$customer->name.'.';
                    if ($fills) {
                        $row['notes'][] = 'Adds '.implode(', ', array_map(fn ($f) => self::FIELDS[$f][0] ?? $f, array_keys($fills))).'.';
                    }
                } elseif ($row['data']['name'] === '') {
                    $row['action'] = 'error';
                    $row['errors'][] = 'No name. Add a name for this number.';
                } else {
                    $row['action'] = 'create';
                }
            } else {
                $row['action'] = 'error';
            }

            if ($m !== null && $row['action'] !== 'error') {
                $first[$m] = count($out);
            }
            $out[] = $row;
        }

        return $out;
    }

    /** @param callable(string): string $get */
    private function parseRow(int $line, callable $get): array
    {
        $errors = [];
        $notes = [];

        $rawMobile = $get('mobile');
        [$mobile, $others] = $this->pickMobile($rawMobile);
        if ($rawMobile === '') {
            $errors[] = 'No mobile number.';
        } elseif ($mobile === null) {
            $errors[] = 'Mobile number "'.Str::limit($rawMobile, 24).'" isn’t a 10-digit Indian mobile.';
        }
        [$alt] = $this->pickMobile($get('alt_mobile'));
        $alt = $alt ?? ($others[0] ?? null);
        if ($alt === $mobile) {
            $alt = null;
        }

        $email = mb_strtolower($get('email'));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $notes[] = 'Email "'.Str::limit($email, 30).'" looks wrong; left out.';
            $email = '';
        }

        $cars = [];
        $carText = $get('car');
        $plateText = $get('reg_no');
        // "Creta MH12AB1234" in one cell: pull the plate out of the car text.
        if ($plateText === '' && preg_match('/\b([A-Z]{2}[\s-]*\d{1,2}[\s-]*[A-Z]{0,3}[\s-]*\d{3,4}|\d{2}\s*BH\s*\d{4}\s*[A-Z]{1,2})\b/i', $carText, $pm)) {
            $plateText = $pm[1];
            $carText = trim(str_replace($pm[1], '', $carText), ' ,-/');
        }
        $plate = RegNo::normalize($plateText);
        if ($plate !== null && ! self::plausiblePlate($plate)) {
            $notes[] = 'Number plate "'.Str::limit($plateText, 16).'" looks wrong; left out.';
            $plate = null;
        }
        if ($carText !== '' || $plate !== null) {
            [$make, $model] = $carText !== '' ? self::splitCar($carText) : ['', 'Model not noted'];
            $cars[] = ['make' => Str::limit($make, 60, ''), 'model' => Str::limit($model, 80, ''), 'reg_no' => $plate];
        }

        $name = Str::limit(trim(preg_replace('/\s+/', ' ', $get('name'))), 120, '');

        return [
            'line' => $line,
            'action' => 'error',
            'errors' => $errors,
            'notes' => $notes,
            'customer_id' => null,
            'data' => [
                'name' => $name,
                'mobile' => $mobile,
                'alt_mobile' => $alt,
                'email' => $email !== '' ? Str::limit($email, 150, '') : null,
                'area' => ($a = Str::limit($get('area'), 120, '')) !== '' ? $a : null,
                'notes' => ($n = Str::limit($get('notes'), 1000, '')) !== '' ? $n : null,
                'cars' => $cars,
            ],
        ];
    }

    /**
     * The first valid Indian mobile in a cell, and any others. Cells often hold two numbers:
     * "98231 07676 / 99813 97676", "9823107676, 020-2687…", "+91 98231-07676 ::: …".
     *
     * @return array{0: string|null, 1: list<string>}
     */
    public function pickMobile(string $cell): array
    {
        $found = [];
        foreach (preg_split('/\s*(?:\/|,|;|\||:::|\bor\b|&)\s*/i', $cell) as $part) {
            $e164 = Phone::normalize($part);
            if ($e164 !== null && Phone::isValidIndianMobile($e164)) {
                $found[] = $e164;
            }
        }
        // "9823107676 9981397676" with only spaces between two numbers.
        if ($found === [] && preg_match_all('/(?<!\d)(?:\+?91[\s-]?|0)?[6-9]\d{4}[\s-]?\d{5}(?!\d)/', $cell, $m)) {
            foreach ($m[0] as $num) {
                $e164 = Phone::normalize($num);
                if (Phone::isValidIndianMobile($e164)) {
                    $found[] = $e164;
                }
            }
        }
        $found = array_values(array_unique($found));

        return [$found[0] ?? null, array_slice($found, 1)];
    }

    /** "Hyundai Creta SX", "creta", "Swift Dzire white" → [make, model]. */
    public static function splitCar(string $text): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $best = null;
        foreach (CarModels::options() as $o) {
            foreach ([$o['label'], $o['model']] as $needle) {
                if (preg_match('/(^|[^a-z0-9])'.preg_quote(mb_strtolower($needle), '/').'($|[^a-z0-9])/', mb_strtolower($text))
                    && ($best === null || mb_strlen($needle) > $best[2])) {
                    $best = [$o['make'], $o['model'], mb_strlen($needle)];
                }
            }
        }
        if ($best !== null) {
            return [$best[0], $best[1]];
        }

        return CarModels::split(null, $text);
    }

    public static function plausiblePlate(string $plate): bool
    {
        return (bool) preg_match('/^([A-Z]{2}\d{1,2}[A-Z]{0,3}\d{1,4}|\d{2}BH\d{4}[A-Z]{1,2})$/', $plate);
    }

    /** Fields this row would fill because they are empty on the existing customer. */
    private function fills(Customer $c, array $d): array
    {
        $out = [];
        foreach (['alt_mobile', 'email', 'area', 'notes'] as $f) {
            if (($d[$f] ?? null) !== null && blank($c->{$f}) && ! ($f === 'alt_mobile' && $d[$f] === $c->mobile)) {
                $out[$f] = $d[$f];
            }
        }

        return $out;
    }

    private function carExists(iterable $cars, array $car): bool
    {
        foreach ($cars as $c) {
            $c = $c instanceof CustomerCar ? ['model' => $c->model, 'reg_no' => $c->reg_no] : $c;
            if ($car['reg_no'] !== null && $c['reg_no'] === $car['reg_no']) {
                return true;
            }
            if ($car['reg_no'] === null && strcasecmp((string) $c['model'], $car['model']) === 0) {
                return true;
            }
        }

        return false;
    }

    private function hasCar(array $cars, array $car): bool
    {
        return $this->carExists($cars, $car);
    }

    // ------------------------------------------------------------------ save

    /**
     * Save rows [$offset, $offset + $size) of a checked preview and add the counts to $import.
     * Each slice is one transaction; running the same slice twice changes nothing.
     */
    public function commitChunk(array $preview, int $offset, CustomerImport $import, User $by, int $size = self::CHUNK): CustomerImport
    {
        $slice = array_slice($preview, $offset, $size);
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'cars_added' => 0, 'skipped' => 0];
        $problems = [];

        DB::transaction(function () use ($slice, $by, &$counts, &$problems) {
            foreach ($slice as $row) {
                if ($row['action'] === 'error') {
                    $counts['skipped']++;
                    $problems[] = ['line' => $row['line'], 'reason' => implode(' ', $row['errors'])];

                    continue;
                }
                try {
                    $result = $this->saveRow($row, $by, $problems);
                    $counts[$result]++;
                } catch (Throwable $e) {
                    report($e);
                    $counts['skipped']++;
                    $problems[] = ['line' => $row['line'], 'reason' => 'Could not be saved: '.Str::limit($e->getMessage(), 120)];
                }
                $counts['cars_added'] += $row['_cars_added'] ?? 0;
            }
        });

        $import->forceFill([
            'processed' => min($import->total_rows, $offset + count($slice)),
            'created' => $import->created + $counts['created'],
            'updated' => $import->updated + $counts['updated'],
            'unchanged' => $import->unchanged + $counts['unchanged'],
            'cars_added' => $import->cars_added + $counts['cars_added'],
            'skipped' => $import->skipped + $counts['skipped'],
            'problems' => array_slice([...($import->problems ?? []), ...$problems], 0, 1000),
        ]);
        if ($offset + $size >= count($preview)) {
            $import->status = 'done';
            $import->finished_at = now();
        }
        $import->save();

        return $import;
    }

    /** @return string created | updated | unchanged */
    private function saveRow(array &$row, User $by, array &$problems): string
    {
        $d = $row['data'];
        $customer = Customer::query()->where('mobile', $d['mobile'])->with('cars')->first();
        $result = 'unchanged';

        if (! $customer) {
            $customer = $this->customers->findOrCreateByMobile($d['mobile'], [
                'name' => $d['name'],
                'alt_mobile' => $d['alt_mobile'],
                'email' => $d['email'],
                'area' => $d['area'],
                'notes' => $d['notes'],
                'source' => 'import',
                'whatsapp_opt_in' => true,   // service messages: bills, receipts, car ready
                'tags' => [self::TAG],
            ], $by);
            $customer->forceFill(['marketing_opt_in' => false])->save(); // no offers until they agree
            $customer->setRelation('cars', collect());
            $result = 'created';
        } else {
            $fills = $this->fills($customer, $d);
            if ($fills) {
                $this->customers->update($customer, $fills);
                $result = 'updated';
            }
        }

        $row['_cars_added'] = 0;
        foreach ($d['cars'] as $car) {
            if ($this->carExists($customer->cars, $car)) {
                continue;
            }
            if ($car['reg_no'] !== null && ($owner = CustomerCar::query()->where('reg_no', $car['reg_no'])->where('customer_id', '!=', $customer->id)->with('customer:id,name')->first())) {
                $problems[] = ['line' => $row['line'], 'reason' => 'Car '.RegNo::pretty($car['reg_no']).' is already saved under '.($owner->customer?->name ?? 'another customer').'; not added again.'];

                continue;
            }
            $saved = $this->customers->addCar($customer, $car, $by);
            $customer->cars->push($saved);
            $row['_cars_added']++;
            if ($result === 'unchanged') {
                $result = 'updated';
            }
        }

        return $result;
    }
}
