<?php

namespace App\Services\Offers;

use App\Jobs\SendOfferMessage;
use App\Models\Customer;
use App\Models\Offer;
use App\Models\OfferRecipient;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Creates offers, freezes their recipient list, and runs the three send methods. */
class OfferService
{
    /** WhatsApp Business app broadcast lists hold at most 256 contacts. */
    public const BROADCAST_LIST_LIMIT = 256;

    public function __construct(
        private readonly SegmentBuilder $segments,
        private readonly WhatsAppBridge $whatsapp,
    ) {}

    public function createDraft(array $data, User $by): Offer
    {
        $filters = $this->segments->normalize($data['filters'] ?? []);
        $count = $this->segments->count($filters);

        return Offer::create([
            'branch_id' => $by->branch_id,
            'title' => $data['title'],
            'message' => $data['message'],
            'filters' => $filters,
            'segment_id' => $data['segment_id'] ?? null,
            'status' => 'draft',
            'recipient_count' => $count['eligible'],
            'opted_out_excluded' => $count['opted_out'],
            'created_by' => $by->id,
        ]);
    }

    /**
     * Freeze the audience and start the chosen method.
     *  - broadcast: recipients are listed; the offer becomes "exported" on the first CSV download;
     *  - assisted:  recipients wait as "pending" for the one-by-one sender;
     *  - automatic: needs the WhatsApp module in Cloud API mode + an approved MARKETING template;
     *               recipients are queued (optionally at scheduled_at).
     */
    public function launch(Offer $offer, string $method, User $by, array $options = []): Offer
    {
        if (! $offer->isDraft()) {
            throw ValidationException::withMessages(['method' => 'This offer has already been started.']);
        }
        if (! array_key_exists($method, Offer::METHODS)) {
            throw ValidationException::withMessages(['method' => 'Choose how to send the offer.']);
        }

        $template = null;
        $scheduledAt = null;
        if ($method === 'automatic') {
            if (! $this->whatsapp->automaticAvailable()) {
                throw ValidationException::withMessages(['method' => 'Automatic sending needs the WhatsApp Cloud API. Use a free method.']);
            }
            $template = trim((string) ($options['template_name'] ?? ''));
            if (! preg_match('/^[a-z0-9_]{1,80}$/', $template)) {
                throw ValidationException::withMessages(['template_name' => 'Pick the approved marketing template.']);
            }
            if (empty($options['confirm_cost'])) {
                throw ValidationException::withMessages(['confirm_cost' => 'Tick the box to confirm the cost.']);
            }
            if (! empty($options['scheduled_at'])) {
                $scheduledAt = Carbon::parse($options['scheduled_at'], 'Asia/Kolkata')->setTimezone(config('app.timezone'));
                if ($scheduledAt->isPast()) {
                    $scheduledAt = null; // "schedule" in the past = send now
                }
            }
        }

        DB::transaction(function () use ($offer, $method, $template, $scheduledAt) {
            $this->snapshotRecipients($offer, $method === 'automatic' ? 'queued' : 'pending');
            $count = $offer->recipients()->count();
            $offer->fill([
                'send_method' => $method,
                'template_name' => $template,
                'scheduled_at' => $scheduledAt,
                'status' => $method === 'broadcast' ? 'draft' : 'sending',
                'recipient_count' => $count,
                'estimated_cost_paise' => $method === 'automatic' ? WhatsAppBridge::estimateCostPaise($count) : null,
            ])->save();
        });

        if ($method === 'automatic') {
            $offer->recipients()->where('status', 'queued')->pluck('id')->each(function (int $id) use ($scheduledAt) {
                $job = SendOfferMessage::dispatch($id);
                if ($scheduledAt) {
                    $job->delay($scheduledAt);
                }
            });
        }

        return $offer->fresh();
    }

    /** Copy the current audience into offer_recipients (the "who got it" record). */
    public function snapshotRecipients(Offer $offer, string $status = 'pending'): void
    {
        $count = $this->segments->count($offer->filters ?? []);
        $offer->opted_out_excluded = $count['opted_out'];

        $now = now();
        $this->segments->query($offer->filters ?? [])
            ->select(['id', 'name', 'mobile'])
            ->chunkById(500, function ($chunk) use ($offer, $status, $now) {
                OfferRecipient::query()->insertOrIgnore($chunk->map(fn (Customer $c) => [
                    'offer_id' => $offer->id,
                    'customer_id' => $c->id,
                    'mobile' => $c->mobile,
                    'name' => $c->name,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    /** Broadcast CSV downloaded: mark the offer and its recipients as exported. */
    public function markExported(Offer $offer): void
    {
        if ($offer->send_method !== 'broadcast') {
            return;
        }
        $offer->recipients()->where('status', 'pending')->update(['status' => 'exported', 'done_at' => now()]);
        $offer->forceFill(['status' => 'exported', 'exported_at' => $offer->exported_at ?? now()])->save();
        $offer->refreshCounts();
    }

    /** One-by-one sender: "Sent ✓" or "Skip" for one customer. Progress is saved at once. */
    public function mark(OfferRecipient $recipient, string $status, User $by): void
    {
        if (! in_array($status, ['sent', 'skipped', 'pending'], true)) {
            throw ValidationException::withMessages(['status' => 'Unknown status.']);
        }
        $recipient->forceFill([
            'status' => $status,
            'done_at' => $status === 'pending' ? null : now(),
            'done_by' => $status === 'pending' ? null : $by->id,
        ])->save();

        $offer = $recipient->offer;
        if ($status === 'pending' && $offer->status === 'sent') {
            $offer->status = 'sending';
            $offer->completed_at = null;
        }
        $offer->refreshCounts();
    }

    /** Next customer for the one-by-one sender, or null when everyone is done. */
    public function nextPending(Offer $offer): ?OfferRecipient
    {
        return $offer->recipients()->where('status', 'pending')->orderBy('id')->first();
    }

    /** Template parameters for automatic sends: {{1}} = first name, {{2}} = the offer text. */
    public static function templateParams(Offer $offer, Customer $customer): array
    {
        $values = OfferMessage::values($customer);
        // Meta rejects new lines, tabs and 4+ spaces inside template parameters.
        $text = preg_replace('/\s*\R+\s*/u', ' · ', OfferMessage::render($offer->message, $customer));
        $text = preg_replace('/[\t ]{2,}/', ' ', (string) $text);

        return [$values['name'], mb_substr(trim((string) $text), 0, 1000)];
    }
}
