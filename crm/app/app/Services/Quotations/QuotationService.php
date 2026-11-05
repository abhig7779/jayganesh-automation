<?php

namespace App\Services\Quotations;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Customers\CustomerService;
use App\Support\ClientKey;
use App\Support\Quotations\ShopClock;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Every quotation write goes through here: create, revise, win, expire, reassign, convert. */
class QuotationService
{
    public function __construct(
        private readonly CustomerService $customers,
        private readonly FollowUpScheduler $scheduler,
    ) {}

    public static function canEditPrice(User $user): bool
    {
        return $user->can('prices.manage') || $user->hasAnyRole(['owner', 'admin']);
    }

    /**
     * $data: mobile + name (or customer_id), customer_car_id or car[make, model, reg_no],
     * items[product_id, qty, unit_price_paise?, name?], valid_days?, notes?, lead_id?,
     * revised_from_id?, source?, sent (bool, recorded as sent on WhatsApp now).
     */
    public function create(array $data, User $by): Quotation
    {
        $items = $this->resolveItems($data['items'] ?? [], $by);
        $key = $data['client_key'] ?? null;

        // A retried save (slow network, double tap) returns the first quotation (audit L2).
        return ClientKey::once(Quotation::class, $by->branch_id, $key, fn () => DB::transaction(function () use ($data, $by, $items, $key) {
            $customer = ! empty($data['customer_id'])
                ? Customer::query()->findOrFail($data['customer_id'])
                : $this->customers->findOrCreateByMobile((string) ($data['mobile'] ?? ''), ['name' => $data['name'] ?? null, 'source' => $data['customer_source'] ?? 'walk_in'], $by);

            $carId = $data['customer_car_id'] ?? null;
            if ($carId && ! $customer->cars()->whereKey($carId)->exists()) {
                $carId = null;
            }
            if (! $carId && filled(Arr::get($data, 'car.model'))) {
                $carId = $this->customers->addCar($customer, [
                    'make' => Arr::get($data, 'car.make') ?: '—',
                    'model' => Arr::get($data, 'car.model'),
                    'reg_no' => Arr::get($data, 'car.reg_no'),
                ], $by)->id;
            }

            $revisedFrom = ! empty($data['revised_from_id']) ? Quotation::query()->lockForUpdate()->find($data['revised_from_id']) : null;
            $branchId = $by->branch_id ?? $customer->branch_id;
            $validDays = (int) ($data['valid_days'] ?? Setting::get('billing.quotation_valid_days', null, $branchId) ?? 7);
            $totals = QuoteTotals::calculate($items);

            $q = Quotation::create([
                'number' => QuotationNumber::next($branchId),
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'customer_car_id' => $carId,
                'lead_id' => $data['lead_id'] ?? $revisedFrom?->lead_id,
                'assigned_to' => $revisedFrom?->assigned_to ?? $by->id,
                'created_by' => $by->id,
                'status' => Quotation::OPEN,
                'valid_until' => ShopClock::todayDate()->addDays(max(1, $validDays))->toDateString(),
                'taxable_paise' => $totals['taxable_paise'],
                'gst_paise' => $totals['gst_paise'],
                'total_paise' => $totals['total_paise'],
                'savings_paise' => $totals['savings_paise'],
                'version' => $revisedFrom ? $revisedFrom->version + 1 : 1,
                'revised_from_id' => $revisedFrom?->id,
                'source' => $data['source'] ?? ($data['lead_id'] ?? null ? 'lead' : 'walk_in'),
                'notes' => $data['notes'] ?? null,
                'sent_at' => ! empty($data['sent']) ? now() : null,
                'client_key' => $key ?: null,
            ]);

            foreach ($items as $i => $line) {
                $q->items()->create($line + $totals['lines'][$i] + ['position' => $i]);
            }

            $this->scheduler->scheduleQuotation($q);

            if ($revisedFrom) {
                $this->scheduler->skipPending($revisedFrom, note: 'Revised as '.$q->number);
                $revisedFrom->forceFill([
                    'revised_to_id' => $q->id,
                    'status' => $revisedFrom->isActive() ? Quotation::EXPIRED : $revisedFrom->status,
                    'expired_at' => $revisedFrom->isActive() ? now() : $revisedFrom->expired_at,
                ])->save();
            }

            if ($q->lead_id && ($lead = Lead::query()->find($q->lead_id))) {
                $this->scheduler->skipPending($lead, note: 'Quotation '.$q->number);
                $lead->forceFill(['status' => Lead::QUOTE_SENT, 'quotation_id' => $q->id, 'customer_id' => $lead->customer_id ?? $customer->id])->save();
            }

            return $q->load('items', 'customer', 'car');
        }));
    }

    /** Recorded when staff press "Send on WhatsApp". */
    public function markSent(Quotation $q): void
    {
        if (! $q->sent_at) {
            $q->forceFill(['sent_at' => now()])->save();
        }
    }

    public function reassign(Quotation|Lead $subject, User $to): void
    {
        DB::transaction(function () use ($subject, $to) {
            $subject->forceFill(['assigned_to' => $to->id])->save();
            $subject->followUps()->pending()->update(['assigned_to' => $to->id]);
        });
    }

    /** The session hand-off billing reads on bills.create (crm/CONTRACTS.md §3). */
    public function billPrefill(Quotation $q): array
    {
        $q->loadMissing('items');

        return [
            'customer_id' => (int) $q->customer_id,
            'customer_car_id' => $q->customer_car_id ? (int) $q->customer_car_id : null,
            'quotation_id' => (int) $q->id,
            'items' => $q->items->filter(fn ($i) => $i->product_id)->map(fn ($i) => [
                'product_id' => (int) $i->product_id,
                'qty' => (int) $i->qty,
                'unit_price_paise' => (int) $i->unit_price_paise,
            ])->values()->all(),
        ];
    }

    /**
     * Auto-win (listener for App\Events\InvoiceSaved). The linked quote wins; otherwise every
     * open/visit-booked/expired quote of that customer from the last 30 days wins. Leads of
     * that customer (by id or mobile) from the last 30 days win too. Their follow-ups stop.
     *
     * @return int how many quotes + leads were marked won
     */
    public function autoWin(int $invoiceId, ?int $customerId, ?int $quotationId): int
    {
        return DB::transaction(function () use ($invoiceId, $customerId, $quotationId) {
            $won = 0;
            $since = now()->subDays(30);
            $quotes = collect();

            if ($quotationId && ($linked = Quotation::query()->find($quotationId))) {
                $quotes->push($linked);
                $customerId ??= $linked->customer_id;
            } elseif ($customerId) {
                $quotes = Quotation::query()->where('customer_id', $customerId)
                    ->whereIn('status', [...Quotation::ACTIVE, Quotation::EXPIRED])
                    ->whereNull('revised_to_id')
                    ->where('created_at', '>=', $since)->get();
            }

            foreach ($quotes as $q) {
                if ($q->status === Quotation::WON) {
                    continue;
                }
                $q->forceFill(['status' => Quotation::WON, 'won_invoice_id' => $invoiceId, 'won_at' => now()])->save();
                $this->scheduler->skipPending($q, note: 'Bill made');
                $won++;
            }

            if ($customerId) {
                $mobile = Customer::query()->whereKey($customerId)->value('mobile');
                $leads = Lead::query()
                    ->whereNotIn('status', [Lead::WON, Lead::LOST])
                    ->where(fn ($w) => $w->where('customer_id', $customerId)->when($mobile, fn ($w) => $w->orWhere('mobile', $mobile)))
                    ->where(fn ($w) => $w->where('created_at', '>=', $since)->orWhereIn('quotation_id', $quotes->pluck('id')))
                    ->get();
                foreach ($leads as $lead) {
                    $lead->forceFill(['status' => Lead::WON, 'won_invoice_id' => $invoiceId, 'won_at' => now(), 'customer_id' => $lead->customer_id ?? $customerId])->save();
                    $this->scheduler->skipPending($lead, note: 'Bill made');
                    $won++;
                }
            }

            return $won;
        });
    }

    /**
     * quotations:expire — quotes past valid_until with no win become Expired. The last
     * planned follow-up stays (the Day-10 "last try"); earlier pending ones are dropped.
     */
    public function expireDue(): int
    {
        $today = ShopClock::today();
        $count = 0;
        Quotation::query()->whereIn('status', Quotation::ACTIVE)->whereDate('valid_until', '<', $today)
            ->each(function (Quotation $q) use (&$count) {
                DB::transaction(function () use ($q) {
                    $last = $q->followUps()->pending()->orderByDesc('due_on')->orderByDesc('id')->first();
                    if ($last) {
                        $this->scheduler->skipPending($q, except: $last->id, note: 'Quotation expired');
                    }
                    $q->forceFill(['status' => Quotation::EXPIRED, 'expired_at' => now()])->save();
                });
                $count++;
            });

        return $count;
    }

    /**
     * Turns posted lines into priced lines. Prices come from the product (made GST-inclusive);
     * only Owner/Admin or prices.manage may type their own price or a free-text line.
     */
    private function resolveItems(array $rows, User $by): array
    {
        $canEdit = self::canEditPrice($by);
        $products = Product::query()->whereKey(collect($rows)->pluck('product_id')->filter())->get()->keyBy('id');
        $items = [];
        foreach (array_values($rows) as $row) {
            $qty = max(1, min(999, (int) ($row['qty'] ?? 1)));
            $product = isset($row['product_id']) ? $products->get((int) $row['product_id']) : null;
            if (! $product && ! ($canEdit && filled($row['name'] ?? null))) {
                continue;
            }
            $rate = $product?->gst_rate ?? ($row['gst_rate'] ?? 18);
            $listPrice = $product ? QuoteTotals::inclusive((int) $product->price_paise, $rate, (bool) $product->price_includes_gst) : 0;
            $price = ($canEdit && isset($row['unit_price_paise']) && $row['unit_price_paise'] !== '' && $row['unit_price_paise'] !== null)
                ? max(0, (int) $row['unit_price_paise'])
                : $listPrice;

            $items[] = [
                'product_id' => $product?->id,
                'name' => $product?->name ?? mb_substr((string) $row['name'], 0, 160),
                'hsn' => $product?->hsn,
                'qty' => $qty,
                'unit_price_paise' => $price,
                'mrp_paise' => $product?->mrp_paise ? QuoteTotals::inclusive((int) $product->mrp_paise, $rate, true) : null,
                'gst_rate' => $rate,
            ];
        }
        if (! $items) {
            throw ValidationException::withMessages(['items' => 'Add at least one item to the quotation.']);
        }

        return $items;
    }

    /** Mark a quotation lost by hand (from the detail page), closing its follow-ups. */
    public function markLost(Quotation $q, string $reason, ?string $note = null): void
    {
        DB::transaction(function () use ($q, $reason, $note) {
            $q->forceFill(['status' => Quotation::LOST, 'lost_reason' => $reason, 'lost_note' => $note, 'lost_at' => now()])->save();
            $this->scheduler->skipPending($q, note: 'Marked lost');
        });
    }

    public static function pendingFollowUp(Quotation|Lead $subject): ?FollowUp
    {
        return $subject->followUps()->pending()->orderBy('due_on')->orderBy('id')->first();
    }
}
