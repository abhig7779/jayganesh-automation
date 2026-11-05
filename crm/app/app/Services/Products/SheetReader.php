<?php

namespace App\Services\Products;

use DateTimeInterface;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;
use Throwable;

/**
 * Reads the first sheet of a CSV / XLSX / ODS file into rows of trimmed strings.
 * Copes with what shops actually send: Excel CSVs in Windows-1252, a UTF-8 BOM,
 * ";" or tab separators, numbers stored as floats, blank rows and title rows above the header.
 */
class SheetReader
{
    public const MAX_ROWS = 5000;

    /** @return list<list<string>> */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        $rows = match ($extension) {
            'csv', 'txt' => $this->readCsv($path),
            'xlsx' => $this->readSpout(new XlsxReader, $path),
            'ods' => $this->readSpout(new OdsReader, $path),
            'xls' => throw new RuntimeException('Old .xls files can\'t be read. In Excel use File → Save As → "Excel Workbook (.xlsx)" or "CSV", then upload again.'),
            default => throw new RuntimeException('Please upload a .csv or .xlsx file.'),
        };

        // Drop fully empty rows but keep the original line number for messages.
        $out = [];
        foreach ($rows as $i => $row) {
            $row = array_map(fn ($c) => trim(preg_replace('/\s+/u', ' ', (string) $c)), $row);
            if (implode('', $row) === '') {
                continue;
            }
            $out[$i + 1] = $row;
            if (count($out) > self::MAX_ROWS + 20) {
                throw new RuntimeException('This file has more than '.number_format(self::MAX_ROWS).' rows. Split it into smaller files.');
            }
        }

        return $out;
    }

    /** @return list<list<string>> */
    private function readCsv(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Could not read the file.');
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $firstLines = implode("\n", array_slice(explode("\n", $raw), 0, 5));
        $delimiter = collect([',' => substr_count($firstLines, ','), ';' => substr_count($firstLines, ';'), "\t" => substr_count($firstLines, "\t")])
            ->sortDesc()->keys()->first();

        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $rows = [];
        while (($row = fgetcsv($fh, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($c) => (string) $c, $row);
        }
        fclose($fh);

        return $rows;
    }

    /** @return list<list<string>> */
    private function readSpout(XlsxReader|OdsReader $reader, string $path): array
    {
        try {
            $reader->open($path);
        } catch (Throwable) {
            throw new RuntimeException('This file could not be opened as a spreadsheet. Is it really an Excel file?');
        }
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($v) => $this->cell($v), $row->toArray());
                    if (count($rows) > self::MAX_ROWS + 50) {
                        break;
                    }
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    private function cell(mixed $v): string
    {
        return match (true) {
            $v === null => '',
            is_bool($v) => $v ? '1' : '0',
            is_int($v) => (string) $v,
            is_float($v) => floor($v) == $v && abs($v) < 1e15 ? (string) (int) $v : rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.'),
            $v instanceof DateTimeInterface => $v->format('Y-m-d'),
            default => (string) $v,
        };
    }
}
