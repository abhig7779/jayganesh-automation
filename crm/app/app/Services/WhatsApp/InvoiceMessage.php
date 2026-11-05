<?php

namespace App\Services\WhatsApp;

use App\Contracts\BillForWhatsApp;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;

/**
 * Adapts the billing module's Invoice to BillForWhatsApp without depending on its class.
 *
 * Fields read (crm/CONTRACTS.md): the customer mobile snapshot (`customer_mobile`, falling back
 * to `customer->mobile`), the name snapshot (`customer_name` / `customer->name`), `number`,
 * `total_paise`, `status`, and the balance (`balance_paise` or `due_paise`) for part-paid bills.
 * The PDF comes from App\Services\Pdf\InvoicePdf::render($invoice), only if that class exists.
 *
 * LEAD, after merging billing: check these names against the real invoices table and adjust
 * the small getters below if billing named a column differently.
 */
final class InvoiceMessage implements BillForWhatsApp
{
    public const PDF_CLASS = 'App\\Services\\Pdf\\InvoicePdf';

    private ?string $pdf = null;

    private function __construct(private readonly object $invoice) {}

    public static function fromInvoice(object $invoice): self
    {
        return new self($invoice);
    }

    public function to(): ?string
    {
        $mobile = $this->field('customer_mobile') ?? $this->customer()?->mobile;
        $e164 = Phone::normalize($mobile);

        return Phone::isValidIndianMobile($e164) ? $e164 : null;
    }

    public function customerName(): string
    {
        $name = trim((string) ($this->field('customer_name') ?? $this->customer()?->name ?? ''));

        return $name !== '' ? $name : 'Customer';
    }

    public function number(): string
    {
        return (string) ($this->field('number') ?? $this->field('id'));
    }

    public function totalFormatted(): string
    {
        return Money::format((int) ($this->field('total_paise') ?? 0));
    }

    public function statusText(): string
    {
        $balance = $this->field('balance_paise') ?? $this->field('due_paise');
        $status = strtolower((string) $this->field('status'));

        return match (true) {
            $status === 'cancelled' => 'Cancelled',
            $status === 'paid' || ($balance !== null && (int) $balance <= 0) => 'Paid',
            $balance !== null => 'Balance due '.Money::format((int) $balance),
            in_array($status, ['partial', 'part_paid'], true) => 'Part paid',
            in_array($status, ['unpaid', 'due', 'credit'], true) => 'Payment due',
            default => 'Paid',
        };
    }

    public function pdfPath(): ?string
    {
        if ($this->pdf !== null) {
            return $this->pdf;
        }
        if (! class_exists(self::PDF_CLASS) || ! method_exists(self::PDF_CLASS, 'render')) {
            return null;
        }

        $method = new \ReflectionMethod(self::PDF_CLASS, 'render');
        $path = $method->isStatic()
            ? (self::PDF_CLASS)::render($this->invoice)
            : app(self::PDF_CLASS)->render($this->invoice);

        return $this->pdf = ($path && is_file($path)) ? $path : null;
    }

    public function pdfName(): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $this->number());

        return ($safe !== '' ? $safe : 'invoice').'.pdf';
    }

    public function related(): ?Model
    {
        return $this->invoice instanceof Model ? $this->invoice : null;
    }

    public function isCancelled(): bool
    {
        return strtolower((string) $this->field('status')) === 'cancelled';
    }

    private function field(string $name): mixed
    {
        return $this->invoice instanceof Model
            ? $this->invoice->getAttribute($name)
            : ($this->invoice->{$name} ?? null);
    }

    private function customer(): ?object
    {
        if ($this->invoice instanceof Model) {
            return method_exists($this->invoice, 'customer') ? $this->invoice->customer : null;
        }

        return $this->invoice->customer ?? null;
    }
}
