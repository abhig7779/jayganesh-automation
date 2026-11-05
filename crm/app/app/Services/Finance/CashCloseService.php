<?php

namespace App\Services\Finance;

use App\Models\CashClose;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * End-of-day cash close (UX-FLOWS.md §3.8).
 * Expected cash = opening float + cash payments that day − cash expenses that day.
 */
final class CashCloseService
{
    public const DEFAULT_FLOAT_PAISE = 200000;

    public const DEFAULT_OWNER_WHATSAPP = '+91 98231 07676';

    public function openingFloat(?int $branchId = null): int
    {
        // Empty in Settings = the default ₹2,000.
        return (int) (Setting::get('finance.opening_float_paise', null, $branchId) ?? self::DEFAULT_FLOAT_PAISE);
    }

    /** @return array{opening_float_paise:int, cash_in_paise:int, cash_out_paise:int, expected_cash_paise:int} */
    public function expected(string $date, ?int $branchId = null): array
    {
        $day = Period::day($date);
        $float = $this->openingFloat($branchId);
        $in = SalesQueries::cashReceived($day, $branchId);
        $out = ExpenseQueries::total($day, $branchId, 'cash');

        return [
            'opening_float_paise' => $float,
            'cash_in_paise' => $in,
            'cash_out_paise' => $out,
            'expected_cash_paise' => $float + $in - $out,
        ];
    }

    /** The numbers that go into the owner's WhatsApp summary. */
    public function daySummary(string $date, ?int $branchId = null): array
    {
        $day = Period::day($date);
        $s = SalesQueries::summary($day, $branchId);

        return [
            'sales_paise' => $s['sales_paise'],
            'bills' => $s['bills'],
            'modes' => $s['modes'],
            'dues_collected_paise' => $s['dues_collected_paise'],
            'expenses_paise' => ExpenseQueries::total($day, $branchId),
            'cancelled_count' => $s['cancelled_count'],
            'billing_installed' => SalesQueries::installed(),
        ];
    }

    /** Save (or redo) the close for one day. Throws a validation error when a difference has no note. */
    public function close(User $by, string $date, int $countedPaise, ?string $note, ?int $branchId = null): CashClose
    {
        $branchId ??= $by->branch_id;
        $note = trim((string) $note) ?: null;
        $exp = $this->expected($date, $branchId);
        $difference = $countedPaise - $exp['expected_cash_paise'];

        if ($difference !== 0 && $note === null) {
            throw ValidationException::withMessages([
                'note' => 'The cash is '.($difference < 0 ? 'short' : 'over').' by '.Money::format(abs($difference)).'. Write a short note saying why.',
            ]);
        }

        return DB::transaction(fn () => CashClose::query()->updateOrCreate(
            ['branch_id' => $branchId, 'date' => CarbonImmutable::parse($date)->toDateString()],
            $exp + [
                'counted_cash_paise' => $countedPaise,
                'difference_paise' => $difference,
                'note' => $note,
                'summary' => $this->daySummary($date, $branchId),
                'closed_by' => $by->id,
            ],
        ));
    }

    public static function signedMoney(int $paise): string
    {
        return match (true) {
            $paise > 0 => '+'.Money::format($paise),
            $paise < 0 => '−'.Money::format(-$paise),
            default => Money::format(0),
        };
    }

    /** "Day close 5 Nov: Sales ₹X (n bills) · Cash ₹ · UPI ₹ · Card ₹ · Dues collected ₹ · Expenses ₹ · Cash difference ₹" */
    public function whatsappText(CashClose $close): string
    {
        $s = $close->summary ?? $this->daySummary($close->date->toDateString(), $close->branch_id);
        $m = $s['modes'] ?? [];
        $parts = [
            'Sales '.Money::format((int) ($s['sales_paise'] ?? 0)).' ('.($s['bills'] ?? 0).' '.((($s['bills'] ?? 0) === 1) ? 'bill' : 'bills').')',
            'Cash '.Money::format((int) ($m['cash'] ?? 0)),
            'UPI '.Money::format((int) ($m['upi'] ?? 0)),
            'Card '.Money::format((int) ($m['card'] ?? 0)),
        ];
        if (($m['bank'] ?? 0) > 0) {
            $parts[] = 'Bank '.Money::format((int) $m['bank']);
        }
        $parts[] = 'Dues collected '.Money::format((int) ($s['dues_collected_paise'] ?? 0));
        $parts[] = 'Expenses '.Money::format((int) ($s['expenses_paise'] ?? 0));
        $parts[] = 'Cash difference '.self::signedMoney($close->difference_paise);

        $text = 'Day close '.$close->date->format('j M').': '.implode(' · ', $parts);
        if ($close->difference_paise !== 0 && $close->note) {
            $text .= "\nNote: ".$close->note;
        }

        return $text;
    }

    public function ownerNumber(?int $branchId = null): string
    {
        return (string) (Setting::get('finance.owner_whatsapp', null, $branchId) ?: self::DEFAULT_OWNER_WHATSAPP);
    }

    public function whatsappLink(CashClose $close): string
    {
        return self::waLink($this->ownerNumber($close->branch_id), $this->whatsappText($close));
    }

    /** wa.me link. Until the WhatsApp module is merged we build it inline (CONTRACTS.md hand-off 4). */
    public static function waLink(?string $phone, string $text): string
    {
        $digits = '';
        if ($phone) {
            try {
                $digits = ltrim((string) Phone::normalize($phone), '+');
            } catch (\Throwable) {
                $digits = preg_replace('/\D/', '', $phone);
            }
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }
}
