<?php

namespace App\Services\Quotations;

use App\Models\Setting;
use App\Support\Quotations\ShopClock;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free quotation numbers per branch and financial year: JGQ-2627-0001.
 * Must be called inside the transaction that saves the quotation, so a rollback
 * also rolls back the counter (no gaps) and the row lock stops two counters
 * taking the same number.
 */
final class QuotationNumber
{
    public static function next(int $branchId): string
    {
        $fy = ShopClock::financialYear();

        DB::table('quotation_counters')->insertOrIgnore([
            'branch_id' => $branchId, 'fy' => $fy, 'last_no' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $row = DB::table('quotation_counters')->where(['branch_id' => $branchId, 'fy' => $fy])->lockForUpdate()->first();
        $no = $row->last_no + 1;
        DB::table('quotation_counters')->where('id', $row->id)->update(['last_no' => $no, 'updated_at' => now()]);

        $prefix = (string) (Setting::get('billing.quotation_prefix', null, $branchId) ?: 'JGQ');

        return sprintf('%s-%s-%04d', $prefix, $fy, $no);
    }
}
