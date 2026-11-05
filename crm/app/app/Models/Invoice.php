<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bill. Money columns are integer paise. Never deleted: cancelling sets status = cancelled
 * and keeps the number (ARCHITECTURE.md §4). Written only by App\Services\Billing\InvoiceService.
 */
class Invoice extends Model
{
    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_DUE = 'due';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        'paid' => 'Paid',
        'partial' => 'Part paid',
        'due' => 'Due',
        'cancelled' => 'Cancelled',
    ];

    public const KIND_TAX_INVOICE = 'tax_invoice';

    public const KIND_BILL_OF_SUPPLY = 'bill_of_supply';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'gross_paise' => 'integer',
            'subtotal_paise' => 'integer',
            'cgst_paise' => 'integer',
            'sgst_paise' => 'integer',
            'igst_paise' => 'integer',
            'discount_paise' => 'integer',
            'mrp_savings_paise' => 'integer',
            'round_off_paise' => 'integer',
            'total_paise' => 'integer',
            'paid_paise' => 'integer',
            'due_paise' => 'integer',
            'credited_paise' => 'integer',
            'send_whatsapp' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(CustomerCar::class, 'customer_car_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** Payments made directly on this bill (at the counter, or refunds). */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Every rupee applied to this bill, including dues collected later across several bills. */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isTaxInvoice(): bool
    {
        return $this->kind === self::KIND_TAX_INVOICE;
    }

    public function taxPaise(): int
    {
        return $this->cgst_paise + $this->sgst_paise + $this->igst_paise;
    }

    /** MRP savings + any extra discount: the "You save ₹X" number. */
    public function totalSavingsPaise(): int
    {
        return (int) $this->mrp_savings_paise + (int) $this->discount_paise;
    }

    /** What the items would cost at MRP (where there is one): before-round total + savings. */
    public function mrpValuePaise(): int
    {
        return $this->total_paise - $this->round_off_paise + $this->totalSavingsPaise();
    }

    /** "Total savings ₹2,600 (MRP ₹16,298 → you pay ₹13,698)", or null when nothing was saved. */
    public function savingsLine(): ?string
    {
        if ($this->totalSavingsPaise() <= 0) {
            return null;
        }

        return 'Total savings '.Money::format($this->totalSavingsPaise())
            .' (MRP '.Money::format($this->mrpValuePaise()).' → you pay '.Money::format($this->total_paise).')';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    /** Receipts applied to this bill grouped by mode: ['cash' => 500000, 'upi' => 869800]. */
    public function paidByMode(): array
    {
        return $this->allocations->loadMissing('payment')
            ->filter(fn (PaymentAllocation $a) => $a->payment && $a->payment->kind === Payment::KIND_RECEIPT)
            ->groupBy(fn (PaymentAllocation $a) => $a->payment->mode)
            ->map(fn ($g) => (int) $g->sum('amount_paise'))
            ->all();
    }

    /** "Cash ₹5,000 + UPI ₹8,698" */
    public function paymentSummary(): string
    {
        $parts = collect($this->paidByMode())
            ->map(fn ($paise, $mode) => (Payment::MODE_LABELS[$mode] ?? $mode).' '.Money::format($paise));

        return $parts->isEmpty() ? 'Not paid yet' : $parts->implode(' + ');
    }

    public function scopeNotCancelled(Builder $q): Builder
    {
        return $q->where('status', '!=', self::STATUS_CANCELLED);
    }

    /** Sales staff see only their own bills unless they have reports.all_staff (UX-FLOWS §1 page 5). */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->can('reports.all_staff') ? $q : $q->where('created_by', $user->id);
    }

    public function scopeUnpaid(Builder $q): Builder
    {
        return $q->notCancelled()->where('due_paise', '>', 0);
    }

    public function fileName(): string
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '-', $this->number).'.pdf';
    }
}
