<?php

namespace App\Support;

/**
 * Text normalising for product search. resources/js/product-search.js applies exactly the same
 * rules in the browser; keep both in step (tests/Unit/SearchTextTest.php pins them down).
 *
 * - lowercase; dots dropped ("l.e.d" = "led", "2.0" = "20");
 * - a hyphen or apostrophe between letters/digits is dropped ("h-4" = "h4"); a slash is a space;
 * - any other punctuation is a space;
 * - a number followed by a lone letter joins up ("7 d" = "7d"), and so does a lone letter
 *   followed by a number ("h 4" = "h4").
 */
final class SearchText
{
    /** @return string[] */
    public static function tokens(?string $text): array
    {
        $s = mb_strtolower((string) $text);
        $s = str_replace('.', '', $s);
        $s = preg_replace('/(?<=[\p{L}\p{N}])[\-\'’](?=[\p{L}\p{N}])/u', '', $s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s);
        $raw = preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY);

        $out = [];
        for ($i = 0; $i < count($raw); $i++) {
            $t = $raw[$i];
            $next = $raw[$i + 1] ?? null;
            if ($next !== null && (
                (ctype_digit($t) && preg_match('/^\p{L}$/u', $next)) ||
                (preg_match('/^\p{L}$/u', $t) && ctype_digit($next))
            )) {
                $out[] = $t.$next;
                $i++;

                continue;
            }
            $out[] = $t;
        }

        return $out;
    }

    public static function normalize(?string $text): string
    {
        return implode(' ', self::tokens($text));
    }

    /** SKU / barcode compare key: lowercase letters and digits only. */
    public static function code(?string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $text));
    }
}
