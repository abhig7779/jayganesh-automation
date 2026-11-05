<?php

namespace App\Services\Finance;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Writes a ReportBuilder report to .xlsx (one sheet per section). Money goes in as rupee numbers
 * with two decimals so the CA can add them up in Excel; paise stay integers everywhere else.
 */
final class XlsxExport
{
    /** Write the report to a temp file and return its path. */
    public static function write(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jgx').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        $bold = (new Style)->setFontBold();
        $head = (new Style)->setFontBold()->setBackgroundColor('F4F4F5');
        $money = (new Style)->setFormat('#,##0.00');
        $moneyBold = (new Style)->setFormat('#,##0.00')->setFontBold();

        $sections = $report['sections'] ?: [['title' => $report['title'], 'columns' => ['info' => ['label' => 'Info', 'type' => 'text']], 'rows' => [], 'totals' => null, 'empty' => 'Nothing to export.']];
        $usedNames = [];
        foreach (array_values($sections) as $i => $section) {
            if ($i > 0) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $writer->getCurrentSheet()->setName(self::sheetName($section['title'], $usedNames));

            $writer->addRow(Row::fromValues([$report['title'].' · '.$section['title']], $bold));
            $writer->addRow(Row::fromValues([$report['period'] ?? '']));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(array_column($section['columns'], 'label'), $head));

            foreach ($section['rows'] as $row) {
                $writer->addRow(self::row($section['columns'], $row, $money));
            }
            if (! $section['rows']) {
                $writer->addRow(Row::fromValues([$section['empty'] ?? 'Nothing in this period.']));
            }
            if (! empty($section['totals'])) {
                $writer->addRow(self::row($section['columns'], $section['totals'], $moneyBold, $bold));
            }
        }
        $writer->close();

        return $path;
    }

    public static function download(array $report, string $filename): BinaryFileResponse
    {
        return response()->download(self::write($report), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private static function row(array $columns, array $values, Style $moneyStyle, ?Style $textStyle = null): Row
    {
        $cells = [];
        foreach ($columns as $key => $col) {
            $v = $values[$key] ?? null;
            $cells[] = match (true) {
                $v === null => Cell::fromValue('', $textStyle),
                $col['type'] === 'money' => Cell::fromValue(round(((int) $v) / 100, 2), $moneyStyle),
                in_array($col['type'], ['int', 'qty', 'pct'], true) && is_numeric($v) => Cell::fromValue($v + 0, $textStyle),
                default => Cell::fromValue((string) $v, $textStyle),
            };
        }

        return new Row($cells);
    }

    private static function sheetName(string $title, array &$used): string
    {
        $name = substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $title) ?: 'Sheet', 0, 28);
        $base = $name;
        $n = 2;
        while (in_array($name, $used, true)) {
            $name = $base.' '.$n++;
        }
        $used[] = $name;

        return $name;
    }
}
