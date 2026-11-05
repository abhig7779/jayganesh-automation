<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    /**
     * Nothing is mass assignable (audit L16): money, totals and status are written only by the
     * billing services with forceCreate / forceFill, so a future update($request->validated())
     * can't set paid_paise or status.
     */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'returned_qty' => 'integer',
            'unit_price_paise' => 'integer',
            'list_price_paise' => 'integer',
            'mrp_paise' => 'integer',
            'price_includes_gst' => 'boolean',
            'discount_paise' => 'integer',
            'gst_rate' => 'decimal:2',
            'taxable_paise' => 'integer',
            'cgst_paise' => 'integer',
            'sgst_paise' => 'integer',
            'igst_paise' => 'integer',
            'line_total_paise' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxPaise(): int
    {
        return $this->cgst_paise + $this->sgst_paise + $this->igst_paise;
    }

    /** GST-inclusive price of one unit as charged (before discounts). */
    public function unitInclPaise(): int
    {
        return intdiv($this->taxable_paise + $this->taxPaise() + $this->discount_paise, max(1, $this->qty));
    }

    /** MRP to show struck through, only when it is above our price. */
    public function shownMrpPaise(): ?int
    {
        return $this->mrp_paise && $this->mrp_paise > $this->unitInclPaise() ? $this->mrp_paise : null;
    }

    public function returnableQty(): int
    {
        return max(0, $this->qty - $this->returned_qty);
    }

    /** "18" rather than "18.00" for display. */
    public function rateLabel(): string
    {
        return rtrim(rtrim((string) $this->gst_rate, '0'), '.');
    }
}
