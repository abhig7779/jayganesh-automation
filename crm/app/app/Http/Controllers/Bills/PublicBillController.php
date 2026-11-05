<?php

namespace App\Http\Controllers\Bills;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Pdf\InvoicePdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * The PDF link sent to customers on WhatsApp. No login: the URL is signed and expires after
 * 7 days (BillShare::LINK_DAYS); a tampered or expired link gets 403 from the `signed` middleware.
 */
class PublicBillController extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        return response()->file(InvoicePdf::render($invoice), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$invoice->fileName().'"',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
