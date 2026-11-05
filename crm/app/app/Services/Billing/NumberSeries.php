<?php

namespace App\Services\Billing;

use App\Models\InvoiceSeries;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Gap-free document numbers: {prefix}-{fy}-{0001}, e.g. JG-2627-0001 (ARCHITECTURE.md §4).
 *
 * Must be called inside the transaction that saves the document. The series row is locked
 * with SELECT … FOR UPDATE, so two counters saving together queue up instead of clashing, and
 * if the bill transaction rolls back, the number goes back with it (no gaps). Numbers restart
 * every April (Indian financial year).
 */
class NumberSeries
{
    public const TYPE_INVOICE = 'invoice';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    /** 5 Nov 2026 → "2627"; 15 Mar 2026 → "2526". Worked out in India time (audit L5). */
    public static function financialYear(CarbonInterface $date): string
    {
        $date = $date->copy()->setTimezone('Asia/Kolkata');
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%02d%02d', $start % 100, ($start + 1) % 100);
    }

    /** @return array{number:string, financial_year:string} */
    public function next(int $branchId, string $type = self::TYPE_INVOICE, ?CarbonInterface $date = null): array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('NumberSeries::next() must run inside the transaction that saves the document.');
        }
        $fy = self::financialYear($date ?? now());

        $series = $this->lockedRow($branchId, $fy, $type)
            ?? $this->createRow($branchId, $fy, $type);

        $n = $series->next_number;
        $series->next_number = $n + 1;
        $series->save();

        return ['number' => sprintf('%s-%s-%04d', $series->prefix, $fy, $n), 'financial_year' => $fy];
    }

    private function lockedRow(int $branchId, string $fy, string $type): ?InvoiceSeries
    {
        return InvoiceSeries::query()
            ->where(['branch_id' => $branchId, 'financial_year' => $fy, 'type' => $type])
            ->lockForUpdate()
            ->first();
    }

    private function createRow(int $branchId, string $fy, string $type): InvoiceSeries
    {
        $prefix = (string) (Setting::get('billing.invoice_prefix', null, $branchId) ?: 'JG');
        if ($type === self::TYPE_CREDIT_NOTE) {
            $prefix .= 'CN';
        }
        // insertOrIgnore: if another counter created the row a moment ago, keep theirs.
        InvoiceSeries::query()->insertOrIgnore([
            'branch_id' => $branchId, 'financial_year' => $fy, 'type' => $type,
            'prefix' => $prefix, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->lockedRow($branchId, $fy, $type);
    }
}
