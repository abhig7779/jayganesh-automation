<?php

namespace App\Services\WhatsApp;

use App\Contracts\BillForWhatsApp;
use App\Models\Invoice;
use App\Services\Billing\BillShare;
use App\Services\Pdf\InvoicePdf;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;

/**
 * Adapts billing's Invoice to BillForWhatsApp: the customer mobile/name snapshot on the bill,
 * number, total, status and due. The PDF is InvoicePdf::render(); text() is the same English
 * message the manual "Send on WhatsApp" link uses (BillShare::message, includes "You saved ₹X").
 */
final class InvoiceMessage implements BillForWhatsApp
{
    private ?string $pdf = null;

    private function __construct(private readonly Invoice $invoice) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        return new self($invoice);
    }

    public function to(): ?string
    {
        $e164 = Phone::normalize($this->invoice->customer_mobile ?: $this->invoice->customer?->mobile);

        return Phone::isValidIndianMobile($e164) ? $e164 : null;
    }

    public function customerName(): string
    {
        $name = trim((string) ($this->invoice->customer_name ?: $this->invoice->customer?->name));

        return $name !== '' ? $name : 'Customer';
    }

    public function number(): string
    {
        return $this->invoice->number;
    }

    public function totalFormatted(): string
    {
        return Money::format($this->invoice->total_paise);
    }

    public function statusText(): string
    {
        return match (true) {
            $this->invoice->isCancelled() => 'Cancelled',
            $this->invoice->due_paise > 0 => 'Balance due '.Money::format($this->invoice->due_paise),
            default => 'Paid',
        };
    }

    /** The full customer message, for a manual link or a free-text send. */
    public function text(): string
    {
        return BillShare::message($this->invoice);
    }

    public function pdfPath(): ?string
    {
        if ($this->pdf !== null) {
            return $this->pdf;
        }
        $path = InvoicePdf::render($this->invoice);

        return $this->pdf = is_file($path) ? $path : null;
    }

    public function pdfName(): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $this->number());

        return ($safe !== '' ? $safe : 'invoice').'.pdf';
    }

    public function related(): ?Model
    {
        return $this->invoice;
    }

    public function isCancelled(): bool
    {
        return $this->invoice->isCancelled();
    }
}
