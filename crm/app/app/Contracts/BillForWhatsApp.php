<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * What the WhatsApp module needs from a bill to send the e-bill. Kept small so the listener
 * does not depend on the billing module's Invoice class (built on another branch).
 * App\Services\WhatsApp\InvoiceMessage adapts a real Invoice to this.
 */
interface BillForWhatsApp
{
    /** Customer mobile in E.164, or null when the bill has no number (walk-in). */
    public function to(): ?string;

    public function customerName(): string;

    /** Bill number, e.g. JG-2526-0142. */
    public function number(): string;

    /** e.g. ₹12,500 */
    public function totalFormatted(): string;

    /** e.g. "Paid", "Balance due ₹2,000" */
    public function statusText(): string;

    /** Absolute path of the bill PDF, or null if it cannot be made. May render it (slow). */
    public function pdfPath(): ?string;

    /** File name the customer sees in WhatsApp, e.g. JG-2526-0142.pdf */
    public function pdfName(): string;

    /** The record the message belongs to (stored on message_log.related). */
    public function related(): ?Model;

    /** True for a cancelled bill (nothing is sent). */
    public function isCancelled(): bool;
}
