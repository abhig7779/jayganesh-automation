<?php

namespace App\Services\Imports;

use App\Services\Products\SheetReader;
use RuntimeException;

/**
 * Reads a customer list into rows of strings (line number => cells), whatever the shop has:
 *  - an Excel / CSV / ODS sheet (via the products module's SheetReader, same quirks handled);
 *  - a phone contacts export (.vcf, vCard 2.1 / 3.0 / 4.0, as Android and iPhone save it);
 *  - a Google Contacts CSV export ("First Name", "Phone 1 - Value", …), flattened to simple columns.
 * Contacts files come out with the headers Name, Mobile, Other phone, Email, Area, Notes.
 */
class ContactsReader
{
    public const MAX_ROWS = SheetReader::MAX_ROWS;

    public const CONTACT_HEADERS = ['Name', 'Mobile', 'Other phone', 'Email', 'Area', 'Notes'];

    public function __construct(private SheetReader $sheets) {}

    /** @return array{0: array<int, list<string>>, 1: string} rows and the kind: sheet | vcf | google */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['vcf', 'vcard'], true)) {
            $raw = file_get_contents($path);
            if ($raw === false) {
                throw new RuntimeException('Could not read the file.');
            }

            return [$this->fromVcf($raw), 'vcf'];
        }

        $rows = $this->sheets->read($path, $extension);
        $first = $rows[array_key_first($rows) ?? 0] ?? [];
        if ($this->isGoogleContacts($first)) {
            return [$this->fromGoogleCsv($rows), 'google'];
        }

        return [$rows, 'sheet'];
    }

    // ------------------------------------------------------------------ vCard

    /** @return array<int, list<string>> */
    public function fromVcf(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        if (! str_contains(strtoupper($raw), 'BEGIN:VCARD')) {
            throw new RuntimeException('This doesn’t look like a contacts (.vcf) file.');
        }

        $rows = [1 => self::CONTACT_HEADERS];
        $line = 1;
        foreach ($this->cards($raw) as $card) {
            $contact = $this->parseCard($card);
            if ($contact === null) {
                continue;
            }
            $rows[++$line] = $contact;
            if (count($rows) > self::MAX_ROWS + 1) {
                throw new RuntimeException('This file has more than '.number_format(self::MAX_ROWS).' contacts. Split it into smaller files.');
            }
        }

        return $rows;
    }

    /** @return list<list<string>> each card as unfolded "NAME;PARAMS:value" lines */
    private function cards(string $raw): array
    {
        $cards = [];
        $current = null;
        $lines = explode("\n", $raw);
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            $l = $lines[$i];
            // vCard 3/4 folding: a line starting with a space or tab continues the previous one.
            if ($current !== null && $current !== [] && ($l !== '' && ($l[0] === ' ' || $l[0] === "\t"))) {
                $current[count($current) - 1] .= substr($l, 1);

                continue;
            }
            // vCard 2.1 quoted-printable: a value ending in "=" continues on the next line.
            while (preg_match('/ENCODING=QUOTED-PRINTABLE|;QUOTED-PRINTABLE/i', strtok($l, ':') ?: '') && str_ends_with($l, '=') && $i + 1 < $count) {
                $l = substr($l, 0, -1).ltrim($lines[++$i]);
            }
            $upper = strtoupper(trim($l));
            if ($upper === 'BEGIN:VCARD') {
                $current = [];
            } elseif ($upper === 'END:VCARD') {
                if ($current !== null) {
                    $cards[] = $current;
                }
                $current = null;
            } elseif ($current !== null && trim($l) !== '') {
                $current[] = $l;
            }
        }

        return $cards;
    }

    /** @param list<string> $lines */
    private function parseCard(array $lines): ?array
    {
        $fn = $n = $note = $email = $area = '';
        $phones = [];

        foreach ($lines as $line) {
            $pos = $this->valueSeparator($line);
            if ($pos === null) {
                continue;
            }
            $head = substr($line, 0, $pos);
            $value = substr($line, $pos + 1);
            $parts = explode(';', $head);
            $name = strtoupper(preg_replace('/^[^.]+\./', '', array_shift($parts))); // drop "item1."
            $params = strtoupper(implode(';', $parts));

            if (str_contains($params, 'QUOTED-PRINTABLE')) {
                $value = quoted_printable_decode($value);
            }
            if (preg_match('/CHARSET=([A-Z0-9-]+)/', $params, $m) && $m[1] !== 'UTF-8') {
                $value = @mb_convert_encoding($value, 'UTF-8', $m[1]) ?: $value;
            }
            if (! mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            }

            switch ($name) {
                case 'FN':
                    $fn = $this->unescape($value);
                    break;
                case 'N':
                    $p = array_map(fn ($x) => $this->unescape($x), $this->splitStructured($value));
                    // Family;Given;Middle;Prefix;Suffix → "Given Middle Family"
                    $n = trim(preg_replace('/\s+/', ' ', ($p[1] ?? '').' '.($p[2] ?? '').' '.($p[0] ?? '')));
                    break;
                case 'TEL':
                    $tel = trim(preg_replace('/^tel:/i', '', $this->unescape($value)));
                    if ($tel !== '') {
                        // Mobile numbers first: TYPE=CELL (2.1 writes just ";CELL").
                        str_contains($params, 'CELL') ? array_unshift($phones, $tel) : $phones[] = $tel;
                    }
                    break;
                case 'EMAIL':
                    $email = $email ?: trim($this->unescape($value));
                    break;
                case 'NOTE':
                    $note = trim($this->unescape($value));
                    break;
                case 'ADR':
                    $p = array_map(fn ($x) => trim($this->unescape($x)), $this->splitStructured($value));
                    // PO box;ext;street;locality;region;postcode;country → the locality, else the street.
                    $area = $area ?: (($p[3] ?? '') !== '' ? $p[3] : ($p[2] ?? ''));
                    break;
            }
        }

        $name = trim($fn !== '' ? $fn : $n);
        $phones = array_values(array_unique($phones));
        if ($name === '' && $phones === []) {
            return null;
        }

        return [
            $this->clean($name),
            $this->clean($phones[0] ?? ''),
            $this->clean(implode(' / ', array_slice($phones, 1))),
            $this->clean($email),
            $this->clean($area),
            $this->clean($note),
        ];
    }

    /** The first ":" that isn't inside a quoted parameter value. */
    private function valueSeparator(string $line): ?int
    {
        $quoted = false;
        $len = strlen($line);
        for ($i = 0; $i < $len; $i++) {
            if ($line[$i] === '"') {
                $quoted = ! $quoted;
            } elseif ($line[$i] === ':' && ! $quoted) {
                return $i;
            }
        }

        return null;
    }

    /** Split on ";" that isn't escaped as "\;". */
    private function splitStructured(string $value): array
    {
        return preg_split('/(?<!\\\\);/', $value);
    }

    private function unescape(string $v): string
    {
        return str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], [' ', ' ', ',', ';', '\\'], $v);
    }

    private function clean(string $v): string
    {
        return trim(preg_replace('/\s+/u', ' ', $v));
    }

    // ------------------------------------------------------------------ Google Contacts CSV

    private function isGoogleContacts(array $headers): bool
    {
        foreach ($headers as $h) {
            if (preg_match('/^Phone 1 - Value$/i', trim((string) $h))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Google's export has one column per name part and several "Phone N - Value" columns, and
     * puts more than one number in a cell as "98231 07676 ::: 99813 97676".
     *
     * @param  array<int, list<string>>  $rows
     * @return array<int, list<string>>
     */
    public function fromGoogleCsv(array $rows): array
    {
        $headerLine = array_key_first($rows);
        $col = [];
        foreach ($rows[$headerLine] as $i => $h) {
            $col[mb_strtolower(trim((string) $h))] = $i;
        }
        $get = fn (array $r, string $h) => isset($col[$h]) ? trim((string) ($r[$col[$h]] ?? '')) : '';
        $phoneCols = collect($col)->filter(fn ($i, $h) => preg_match('/^phone \d+ - value$/', $h))->sortKeys(SORT_NATURAL)->values();
        $emailCols = collect($col)->filter(fn ($i, $h) => preg_match('/^e-?mail \d+ - value$/', $h))->sortKeys(SORT_NATURAL)->values();

        $out = [$headerLine => self::CONTACT_HEADERS];
        foreach ($rows as $line => $r) {
            if ($line === $headerLine) {
                continue;
            }
            $name = $get($r, 'name');
            if ($name === '') {
                $name = trim(preg_replace('/\s+/', ' ', implode(' ', [
                    $get($r, 'first name') ?: $get($r, 'given name'),
                    $get($r, 'middle name') ?: $get($r, 'additional name'),
                    $get($r, 'last name') ?: $get($r, 'family name'),
                ])));
            }
            $name = $name ?: ($get($r, 'nickname') ?: ($get($r, 'organization name') ?: $get($r, 'organization 1 - name')));

            $phones = [];
            foreach ($phoneCols as $i) {
                foreach (preg_split('/\s*:::\s*/', (string) ($r[$i] ?? '')) as $p) {
                    if (trim($p) !== '') {
                        $phones[] = trim($p);
                    }
                }
            }
            $phones = array_values(array_unique($phones));
            $email = '';
            foreach ($emailCols as $i) {
                $email = $email ?: trim(explode(':::', (string) ($r[$i] ?? ''))[0]);
            }

            $out[$line] = [
                $name,
                $phones[0] ?? '',
                implode(' / ', array_slice($phones, 1)),
                $email,
                $get($r, 'address 1 - city') ?: $get($r, 'address 1 - street'),
                $get($r, 'notes'),
            ];
        }

        return $out;
    }
}
