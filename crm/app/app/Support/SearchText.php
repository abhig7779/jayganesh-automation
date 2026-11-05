<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Word-prefix search helpers shared by the product search and its highlighting.
 * "creta mat" finds "7D floor mats (Creta)": every typed word must be the start of some word.
 */
final class SearchText
{
    /**
     * Same rules as resources/js/product-search.js (keep both in step):
     * lowercase; dots dropped ("l.e.d" = "led", "2.0" = "20"); a hyphen/apostrophe inside a word is
     * dropped ("h-4" = "h4"); other punctuation is a space; "7 d" = "7d" and "h 4" = "h4".
     *
     * @return string[]
     */
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

    /** "DHC Gleam-2.0 (H4)" → "dhc gleam 20 h4" */
    public static function normalize(?string $text): string
    {
        return implode(' ', self::tokens($text));
    }

    /** SKU / barcode compare key: lowercase letters and digits only. */
    public static function code(?string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $text));
    }

    /**
     * The words to prefix-match. A trailing plural "s" is dropped so "mats" still finds "mat",
     * and "seats" finds "seat cover".
     *
     * @return list<string>
     */
    public static function words(?string $query): array
    {
        $words = array_values(array_unique(array_filter(explode(' ', self::normalize($query)), fn ($w) => $w !== '')));

        return array_map(
            fn (string $w) => (mb_strlen($w) > 3 && str_ends_with($w, 's') && ! str_ends_with($w, 'ss')) ? mb_substr($w, 0, -1) : $w,
            array_slice($words, 0, 6)
        );
    }

    /** Escape LIKE wildcards. */
    public static function like(string $word): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $word);
    }

    /** Wrap the start of every word that a query word prefixes in <mark>; the rest is escaped. */
    public static function highlight(?string $text, ?string $query): HtmlString
    {
        $text = (string) $text;
        $words = self::words($query);
        if ($text === '' || $words === []) {
            return new HtmlString(e($text));
        }
        usort($words, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $alternatives = implode('|', array_map(fn ($w) => preg_quote($w, '/'), $words));
        $pattern = '/(?<![\p{L}\p{N}])('.$alternatives.')/iu';

        $out = '';
        $offset = 0;
        if (preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as [$match, $pos]) {
                $out .= e(substr($text, $offset, $pos - $offset)).'<mark class="hl">'.e($match).'</mark>';
                $offset = $pos + strlen($match);
            }
        }

        return new HtmlString($out.e(substr($text, $offset)));
    }
}
