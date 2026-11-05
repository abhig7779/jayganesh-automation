<?php

namespace App\Services\Leads;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadIntake;
use App\Models\User;
use App\Services\Customers\CustomerService;
use App\Services\Quotations\FollowUpScheduler;
use App\Support\Quotations\ShopClock;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Enquiries that arrive by themselves (website form, Meta lead ads) become Leads in the
 * quotations module's list, with the same Day-0 follow-up as a lead typed in by hand.
 *
 * The same mobile again within 7 days (config leads.dedupe_days) is added to that open lead as
 * a note instead of making a second one, so staff don't call the same person twice.
 * Won or lost leads are not reopened: a new enquiry after that is a new lead.
 */
class LeadIntakeService
{
    /** API source => Lead source (Lead::SOURCES). */
    public const SOURCES = [
        'website' => 'website',
        'meta_lead_ad' => 'meta_lead_ad',
        'instagram' => 'instagram',
        'other' => 'other',
    ];

    public const UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public function __construct(private readonly FollowUpScheduler $scheduler) {}

    /**
     * $data: mobile, name?, interest?, car?, message?, source, utm_*?, page_url?
     * $meta: channel (api|meta_webhook), external_id?, ip?, token_id?, payload?
     *
     * @return array{lead: Lead, merged: bool, intake: LeadIntake}
     */
    public function receive(array $data, array $meta, ?User $by = null): array
    {
        $by ??= IntegrationUser::get();
        $mobile = CustomerService::validMobile((string) ($data['mobile'] ?? ''));
        $source = self::SOURCES[$data['source'] ?? 'other'] ?? 'other';
        $clean = fn (?string $v, int $max) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $v))) !== '' ? Str::limit($v, $max, '') : null;

        $name = $clean($data['name'] ?? null, 120);
        $interest = $clean($data['interest'] ?? null, 300);
        $car = $clean($data['car'] ?? null, 120);
        $message = $clean($data['message'] ?? null, 2000);

        return DB::transaction(function () use ($data, $meta, $by, $mobile, $source, $name, $interest, $car, $message) {
            $since = now()->subDays((int) config('leads.dedupe_days', 7));
            $open = Lead::query()
                ->where('mobile', $mobile)
                ->where('created_at', '>=', $since)
                ->whereNotIn('status', [Lead::WON, Lead::LOST])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $summary = $this->summary($interest, $car, $message);

            if ($open) {
                $note = '+ '.Carbon::now()->timezone(ShopClock::TZ)->format('j M, g:i a').' ('.(Lead::SOURCES[$source] ?? $source).'): '.($summary ?: 'asked again');
                $open->interest = trim(($open->interest ? $open->interest."\n" : '').$note);
                if (! $open->name && $name) {
                    $open->name = $name;
                }
                $open->save();
                $lead = $open;
                $merged = true;
            } else {
                $customer = Customer::query()->where('mobile', $mobile)->first();
                $lead = Lead::create([
                    'branch_id' => $customer?->branch_id ?? $by->branch_id ?? Branch::query()->value('id'),
                    'mobile' => $mobile,
                    'name' => $name ?: $customer?->name,
                    'customer_id' => $customer?->id,
                    'type' => Lead::TYPE_ENQUIRY,
                    'interest' => $summary,
                    'source' => $source,
                    'status' => Lead::NEW,
                    'assigned_to' => null, // Owner/Admin see unassigned leads and hand them out
                    'created_by' => $by->id,
                ]);
                $this->scheduler->scheduleLead($lead); // Day 0, then the usual 1/3/6/10
                $merged = false;
            }

            $intake = LeadIntake::create([
                'lead_id' => $lead->id,
                'channel' => $meta['channel'] ?? 'api',
                'source' => $source,
                'external_id' => $meta['external_id'] ?? null,
                'merged' => $merged,
                'mobile' => $mobile,
                'name' => $name,
                'interest' => $interest,
                'car' => $car,
                'message' => $message,
                'page_url' => isset($data['page_url']) ? Str::limit((string) $data['page_url'], 300, '') : null,
                'ip' => $meta['ip'] ?? null,
                'token_id' => $meta['token_id'] ?? null,
                'payload' => $meta['payload'] ?? null,
            ] + collect(Arr::only($data, self::UTM))->map(fn ($v) => $v !== null ? Str::limit((string) $v, 150, '') : null)->all());

            return ['lead' => $lead, 'merged' => $merged, 'intake' => $intake];
        });
    }

    /** "PPF · Car: Hyundai Creta · Want full body, price?" */
    private function summary(?string $interest, ?string $car, ?string $message): ?string
    {
        $parts = array_filter([$interest, $car ? 'Car: '.$car : null, $message]);

        return $parts ? implode(' · ', $parts) : null;
    }
}
