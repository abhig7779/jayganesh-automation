<?php

namespace App\Services\Pdf;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Billing\GstCalculator;
use App\Services\Billing\UpiQr;
use App\Support\AmountInWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

/**
 * The A4 GST tax invoice (or bill of supply) as a PDF (crm/CONTRACTS.md hand-off 2).
 * render() returns the absolute path of the saved file in storage/app/private/invoices/{fy}/.
 * WhatsApp attaches that file; the bill screens stream it.
 */
class InvoicePdf
{
    public static function render(Invoice $invoice): string
    {
        $path = self::path($invoice);
        if (File::exists($path) && File::lastModified($path) >= $invoice->updated_at?->getTimestamp()) {
            return $path;
        }
        File::ensureDirectoryExists(dirname($path));
        File::put($path, self::output($invoice));

        return $path;
    }

    /** Fresh PDF bytes (no caching). */
    public static function output(Invoice $invoice): string
    {
        return Pdf::loadView('bills.pdf', self::data($invoice))
            ->setPaper('a4')
            ->setOption(['dpi' => 96, 'defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false])
            ->output();
    }

    public static function path(Invoice $invoice): string
    {
        return storage_path('app/private/invoices/'.$invoice->financial_year.'/'.$invoice->fileName());
    }

    public static function data(Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'customer', 'car', 'creator', 'allocations.payment', 'branch']);
        $b = $invoice->branch_id;
        $logo = public_path('img/logo.png');

        return [
            'invoice' => $invoice,
            'shop' => [
                'name' => Setting::get('shop.name', null, $b),
                'address' => Setting::get('shop.address', null, $b),
                'phone' => Setting::get('shop.phone', null, $b),
                'email' => Setting::get('shop.email', null, $b),
                'gstin' => $invoice->seller_gstin,
                'state_code' => Setting::get('tax.state_code', null, $b),
                'footer' => Setting::get('billing.invoice_footer', null, $b),
                'upi_id' => UpiQr::upiId($b),
            ],
            'logo' => File::exists($logo) ? 'data:image/png;base64,'.base64_encode(File::get($logo)) : null,
            'hsn' => GstCalculator::hsnSummary($invoice->items->map->toArray()),
            'words' => AmountInWords::rupees($invoice->total_paise),
            'payments' => $invoice->allocations->filter(fn ($a) => $a->payment)->map(fn ($a) => [
                'date' => $a->payment->created_at,
                'mode' => $a->payment->kind === Payment::KIND_REFUND ? 'Refund ('.$a->payment->modeLabel().')' : $a->payment->modeLabel(),
                'reference' => $a->payment->reference,
                'amount' => $a->amount_paise,
            ])->values(),
            'qr' => ! $invoice->isCancelled() && $invoice->due_paise > 0 ? UpiQr::dataUri($invoice->due_paise, $invoice->number, $b) : null,
        ];
    }
}
