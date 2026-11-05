<?php

namespace App\Services\Quotations;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use App\Services\Customers\CustomerService;
use Illuminate\Support\Facades\DB;

/** Enquiries and one-tap "Asked price" logs. Both go straight into the follow-up engine. */
class LeadService
{
    public function __construct(private readonly FollowUpScheduler $scheduler) {}

    /** $data: mobile, name?, interest?, source?, product_id?, price_told_paise?, type? */
    public function create(array $data, User $by): Lead
    {
        $mobile = CustomerService::validMobile((string) $data['mobile']);

        return DB::transaction(function () use ($data, $by, $mobile) {
            $customer = Customer::query()->where('mobile', $mobile)->first();
            $product = ! empty($data['product_id']) ? Product::query()->find($data['product_id']) : null;

            $lead = Lead::create([
                'branch_id' => $by->branch_id ?? $customer?->branch_id ?? Branch::query()->value('id'),
                'mobile' => $mobile,
                'name' => ($data['name'] ?? null) ?: $customer?->name,
                'customer_id' => $customer?->id,
                'type' => $data['type'] ?? Lead::TYPE_ENQUIRY,
                'interest' => ($data['interest'] ?? null) ?: $product?->name,
                'product_id' => $product?->id,
                'price_told_paise' => $data['price_told_paise'] ?? null,
                'source' => array_key_exists($data['source'] ?? '', Lead::SOURCES) ? $data['source'] : 'walk_in_enquiry',
                'assigned_to' => $data['assigned_to'] ?? $by->id,
                'created_by' => $by->id,
            ]);

            $this->scheduler->scheduleLead($lead);

            return $lead;
        });
    }

    /** UX-FLOWS §3.2 step 1: mobile + product + price told, one tap. */
    public function askedPrice(string $mobile, ?int $productId, ?int $pricePaise, ?string $what, User $by, string $source = 'walk_in_enquiry'): Lead
    {
        return $this->create([
            'mobile' => $mobile,
            'type' => Lead::TYPE_PRICE,
            'product_id' => $productId,
            'interest' => $what,
            'price_told_paise' => $pricePaise,
            'source' => $source,
        ], $by);
    }

    public function markLost(Lead $lead, string $reason, ?string $note = null): void
    {
        DB::transaction(function () use ($lead, $reason, $note) {
            $lead->forceFill(['status' => Lead::LOST, 'lost_reason' => $reason, 'lost_note' => $note])->save();
            $this->scheduler->skipPending($lead, note: 'Marked lost');
        });
    }
}
