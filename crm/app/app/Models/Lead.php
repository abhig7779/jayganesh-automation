<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An enquiry: from an ad, a call, the website, or a quick "Asked price" at the counter
 * (type price_enquiry). Shares the follow-up engine with quotations (UX-FLOWS §3.10).
 */
class Lead extends Model
{
    use LogsActivity;

    public const TYPE_ENQUIRY = 'enquiry';

    public const TYPE_PRICE = 'price_enquiry';

    public const SOURCES = [
        'walk_in_enquiry' => 'Walk-in enquiry',
        'phone_call' => 'Phone call',
        'whatsapp_ad' => 'WhatsApp ad',
        'instagram' => 'Instagram',
        'website' => 'Website',
        'referral' => 'Referral',
        'meta_lead_ad' => 'Meta lead ad', // [lead intake] website form + Meta lead ads (LEADS-API.md)
        'other' => 'Other',               // [lead intake]
    ];

    public const NEW = 'new';

    public const CONTACTED = 'contacted';

    public const QUOTE_SENT = 'quote_sent';

    public const VISIT_BOOKED = 'visit_booked';

    public const WON = 'won';

    public const LOST = 'lost';

    public const STATUS_LABELS = [
        self::NEW => 'New',
        self::CONTACTED => 'Contacted',
        self::QUOTE_SENT => 'Quote sent',
        self::VISIT_BOOKED => 'Visit booked',
        self::WON => 'Won',
        self::LOST => 'Lost',
    ];

    public const ACTIVE = [self::NEW, self::CONTACTED, self::VISIT_BOOKED];

    protected $fillable = [
        'branch_id', 'mobile', 'name', 'customer_id', 'type', 'interest', 'product_id', 'price_told_paise',
        'source', 'status', 'assigned_to', 'created_by', 'quotation_id', 'visit_on', 'lost_reason',
        'lost_note', 'won_invoice_id', 'won_at',
    ];

    protected $attributes = ['status' => self::NEW, 'type' => self::TYPE_ENQUIRY, 'source' => 'walk_in_enquiry'];

    protected function casts(): array
    {
        return [
            'price_told_paise' => 'integer',
            'visit_on' => 'date',
            'won_at' => 'datetime',
        ];
    }

    protected function mobile(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => Phone::normalize($v));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'subject');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->customer?->name ?: Phone::pretty($this->mobile));
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return Quotation::seesAll($user) ? $q : $q->where('assigned_to', $user->id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'assigned_to', 'lost_reason', 'quotation_id'])
            ->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('leads');
    }
}
