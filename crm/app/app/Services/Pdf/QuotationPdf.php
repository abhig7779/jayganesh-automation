<?php

namespace App\Services\Pdf;

use App\Models\Quotation;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

/**
 * A4 quotation PDF (black logo band, customer + car, items with MRP / you save, GST-inclusive
 * totals, valid until, shop terms). Mirrors billing's InvoicePdf::render(): returns the
 * absolute path of the saved file in storage/app/private/quotations/.
 */
class QuotationPdf
{
    public static function render(Quotation $q): string
    {
        $q->loadMissing('items', 'customer', 'car', 'branch', 'assignee');

        $pdf = Pdf::loadView('quotations.pdf', [
            'q' => $q,
            'shop' => [
                'name' => Setting::get('shop.name'),
                'address' => Setting::get('shop.address'),
                'phone' => Setting::get('shop.phone'),
                'email' => Setting::get('shop.email'),
                'gstin' => Setting::get('tax.gstin'),
            ],
            'terms' => array_filter(array_map('trim', preg_split('/\r?\n/', (string) Setting::get('quotations.terms')))),
            'logo' => self::logoDataUri(),
        ])->setPaper('a4')->setOption(['isFontSubsettingEnabled' => true, 'defaultFont' => 'DejaVu Sans']);

        $dir = storage_path('app/private/quotations');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/'.$q->number.'.pdf';
        file_put_contents($path, $pdf->output());

        return $path;
    }

    private static function logoDataUri(): ?string
    {
        $file = public_path('img/logo.png');

        return is_file($file) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($file)) : null;
    }
}
