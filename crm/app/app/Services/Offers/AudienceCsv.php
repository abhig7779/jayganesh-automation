<?php

namespace App\Services\Offers;

use App\Models\Customer;
use Illuminate\Support\Str;

/**
 * CSV rows for the two exports:
 *  - Meta Custom Audience customer list (Ads Manager → Audiences → Custom audience → Customer list),
 *    plain or SHA-256 hashed per Meta's normalisation rules;
 *  - a contacts file for a WhatsApp Business app broadcast list.
 */
final class AudienceCsv
{
    public const META_FIELDS = ['phone', 'fn', 'ln', 'ct', 'st', 'country'];

    public const CITY = 'Pune';

    public const STATE = 'Maharashtra';

    public const COUNTRY = 'in';

    /** +91 98231 07676 → 919823107676 (E.164 digits, no +, as Meta wants). */
    public static function metaPhone(?string $mobile): string
    {
        return preg_replace('/\D+/', '', (string) $mobile);
    }

    /** "Rahul D. Kale" → ['Rahul', 'Kale']; a single word is a first name only. */
    public static function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (! $parts) {
            return ['', ''];
        }
        $first = array_shift($parts);

        return [$first, $parts ? end($parts) : ''];
    }

    /**
     * Meta's normalisation before hashing: trim + lowercase; names and places lose spaces
     * and punctuation; phone keeps digits only (with country code); country is ISO-2.
     */
    public static function normalizeForMeta(string $field, string $value): string
    {
        $v = mb_strtolower(trim($value));

        return match ($field) {
            'phone' => self::metaPhone($v),
            'fn', 'ln', 'ct', 'st' => (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $v),
            'country' => substr((string) preg_replace('/[^a-z]/', '', $v), 0, 2),
            default => $v,
        };
    }

    public static function hash(string $field, string $value): string
    {
        $n = self::normalizeForMeta($field, $value);

        return $n === '' ? '' : hash('sha256', $n);
    }

    /** @param list<string> $fields subset of META_FIELDS; phone is always included */
    public static function metaHeader(array $fields): array
    {
        return array_values(array_filter(self::META_FIELDS, fn ($f) => $f === 'phone' || in_array($f, $fields, true)));
    }

    public static function metaRow(Customer $c, array $header, bool $hashed): array
    {
        [$fn, $ln] = self::splitName($c->name);
        $plain = [
            'phone' => self::metaPhone($c->mobile),
            'fn' => $fn,
            'ln' => $ln,
            'ct' => self::CITY,
            'st' => self::STATE,
            'country' => self::COUNTRY,
        ];

        return array_map(fn ($f) => $hashed ? self::hash($f, $plain[$f]) : $plain[$f], $header);
    }

    public const BROADCAST_HEADER = ['Name', 'Phone 1 - Value', 'Notes'];

    /** Google-Contacts-compatible row, so the list can be imported to the shop phone's contacts. */
    public static function broadcastRow(string $name, string $mobile, string $note = ''): array
    {
        return [Str::of($name)->squish()->toString(), $mobile, $note];
    }

    /** Write rows as CSV to an output stream; cells starting with = + - @ are neutralised. */
    public static function write($handle, array $header, iterable $rows, bool $bom = false): void
    {
        if ($bom) {
            fwrite($handle, "\xEF\xBB\xBF"); // so Excel shows names correctly; Meta's uploader gets none
        }
        fputcsv($handle, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($v) => self::safeCell((string) $v), $row), ',', '"', '');
        }
    }

    private static function safeCell(string $v): string
    {
        // Phone numbers like +919823107676 are allowed; anything else that looks like a formula is quoted.
        if ($v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) && ! preg_match('/^\+\d{6,15}$/', $v)) {
            return "'".$v;
        }

        return $v;
    }
}
