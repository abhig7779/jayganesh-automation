<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Setting;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Support\Facades\URL;

/**
 * The customer-facing bits of a bill: the signed PDF link (7 days, no login), the English
 * WhatsApp message, and the manual wa.me link. Until the WhatsApp module is merged the link is
 * built inline; afterwards App\Services\WhatsApp\WhatsApp::manualLink() is used (CONTRACTS §4).
 */
final class BillShare
{
    public const LINK_DAYS = 7;

    public static function pdfUrl(Invoice $invoice): string
    {
        return URL::temporarySignedRoute('bills.public-pdf', now()->addDays(self::LINK_DAYS), ['invoice' => $invoice->id]);
    }

    public static function message(Invoice $invoice, ?string $pdfUrl = null): string
    {
        $shop = (string) Setting::get('shop.name', null, $invoice->branch_id);
        $first = $invoice->customer_name && $invoice->customer_name !== 'Walk-in customer'
            ? strtok($invoice->customer_name, ' ') : null;

        $lines = [];
        $lines[] = ($first ? "Hello {$first}," : 'Hello,').' thank you for choosing '.$shop.'.';
        $lines[] = "Your bill {$invoice->number} for ".Money::format($invoice->total_paise).' is ready.';
        if ($invoice->totalSavingsPaise() > 0) {
            $lines[] = 'You saved '.Money::format($invoice->totalSavingsPaise()).' on this bill.';
        }
        if ($invoice->due_paise > 0) {
            $lines[] = 'Balance due: '.Money::format($invoice->due_paise).'.';
        }
        $lines[] = 'Download your bill (PDF): '.($pdfUrl ?? self::pdfUrl($invoice));
        $lines[] = 'For warranty, please keep this bill. Call us on '.Setting::get('shop.phone', null, $invoice->branch_id).' for any help.';

        return implode("\n", $lines);
    }

    /** wa.me link with the message, or null for a walk-in bill with no number. */
    public static function whatsappLink(Invoice $invoice): ?string
    {
        if (! Phone::isValidIndianMobile($invoice->customer_mobile)) {
            return null;
        }
        $text = self::message($invoice);
        $whatsApp = 'App\\Services\\WhatsApp\\WhatsApp';
        if (class_exists($whatsApp)) {
            return app($whatsApp)->manualLink($invoice->customer_mobile, $text);
        }

        return 'https://wa.me/'.ltrim($invoice->customer_mobile, '+').'?text='.rawurlencode($text);
    }
}
