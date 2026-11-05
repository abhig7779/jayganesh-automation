<?php

namespace App\Services\Billing;

use App\Models\Setting;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** UPI "scan to pay" QR with the amount filled in (upi://pay?pa=…&pn=…&am=…&cu=INR). */
final class UpiQr
{
    public static function upiId(?int $branchId = null): ?string
    {
        $id = Setting::get('billing.upi_id', null, $branchId);

        return filled($id) ? (string) $id : null;
    }

    public static function uri(int $amountPaise, ?string $note = null, ?int $branchId = null): ?string
    {
        $pa = self::upiId($branchId);
        if (! $pa) {
            return null;
        }
        $params = [
            'pa' => $pa,
            'pn' => (string) (Setting::get('billing.upi_name', null, $branchId) ?: Setting::get('shop.name')),
            'am' => sprintf('%d.%02d', intdiv($amountPaise, 100), $amountPaise % 100),
            'cu' => 'INR',
        ];
        if ($note) {
            $params['tn'] = mb_substr($note, 0, 60);
        }

        return 'upi://pay?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /** Inline SVG markup (black on white), or null when no UPI ID is set. */
    public static function svg(int $amountPaise, ?string $note = null, int $size = 220, ?int $branchId = null): ?string
    {
        $uri = self::uri($amountPaise, $note, $branchId);

        return $uri ? self::svgFor($uri, $size) : null;
    }

    public static function svgFor(string $text, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd));
        $svg = $writer->writeString($text);

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    /** data: URI for dompdf, which renders SVG images. */
    public static function dataUri(int $amountPaise, ?string $note = null, ?int $branchId = null): ?string
    {
        $uri = self::uri($amountPaise, $note, $branchId);
        if (! $uri) {
            return null;
        }
        $writer = new Writer(new ImageRenderer(new RendererStyle(300, 1), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($uri));
    }
}
