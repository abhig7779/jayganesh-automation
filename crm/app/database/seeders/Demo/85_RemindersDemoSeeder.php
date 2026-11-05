<?php

namespace Database\Seeders\Demo;

use App\Models\Invoice;
use App\Models\ReminderRule;
use App\Models\ServiceReminder;
use App\Models\Setting;
use App\Models\User;
use App\Services\Reminders\ReminderService;
use App\Support\Quotations\ShopClock;
use Illuminate\Database\Seeder;

/**
 * Demo reminders from the demo bills (local only, crm/CONTRACTS.md). The demo bills are all from
 * the last 30 days, so besides what the InvoiceSaved listener already made, it adds check-ups as if
 * a few of those customers had the same work done 6 or 12 months ago, so "Reminders today" has
 * real-looking overdue, due and upcoming rows. Older review requests are marked sent or done.
 */
class RemindersDemoSeeder extends Seeder
{
    public function run(ReminderService $reminders): void
    {
        if (ServiceReminder::query()->whereNull('invoice_id')->exists()) {
            return; // already seeded
        }
        // Demo only: a placeholder review link so review requests show. The owner sets the real one.
        if (blank(Setting::get('reviews.google_link'))) {
            Setting::put('reviews.google_link', 'https://g.page/r/jay-ganesh-demo/review');
        }

        $bills = Invoice::query()->notCancelled()->whereNotNull('customer_id')->with(['items.product.category', 'customer', 'car'])
            ->orderBy('created_at')->get();
        if ($bills->isEmpty()) {
            return;
        }
        foreach ($bills as $bill) {
            $reminders->forInvoice($bill); // idempotent: fills in anything the listener missed
        }

        $rules = ReminderRule::query()->active()->get();
        $today = ShopClock::todayDate();
        $owner = User::query()->role('owner')->first();
        // Earlier visits: customers from the demo bills who did NOT have this work on a recent
        // bill (else the new bill would have replaced the old check-up). Due from 6 days ago to
        // 3 weeks ahead, each offset of the rule in turn.
        $spread = [-6, -3, -1, 0, 0, 1, 3, 6, 10, 15, 21];
        $services = [
            'Ceramic coating check-up' => ['Ceramic coating 9H (full car)', 'Graphene coating (full car)'],
            'PPF inspection' => ['Garware PPF full car (sedan / SUV)', '3M PPF bonnet + front bumper'],
            'Wash / detailing' => ['Full car detailing (interior + exterior)', 'Foam wash + interior vacuum'],
            'Wiper blades' => ['Bosch wiper blades (pair)'],
        ];
        $customers = $bills->unique('customer_id')->values();
        $n = 0;
        foreach ($rules as $rule) {
            $names = $services[$rule->name] ?? [$rule->name];
            $had = ServiceReminder::query()->where('reminder_rule_id', $rule->id)->pluck('customer_id')->all();
            $pick = $customers->reject(fn ($b) => in_array($b->customer_id, $had, true))->slice($n % 5, 4)->values();
            foreach ($pick as $k => $bill) {
                $offsets = $rule->offsets();
                $days = $offsets[$k % count($offsets)];
                $due = $today->copy()->addDays($spread[$n % count($spread)]);
                ServiceReminder::query()->create([
                    'branch_id' => $bill->branch_id,
                    'type' => ServiceReminder::TYPE_SERVICE,
                    'customer_id' => $bill->customer_id,
                    'customer_car_id' => $bill->customer_car_id,
                    'reminder_rule_id' => $rule->id,
                    'offset_days' => $days,
                    'service_name' => $names[$k % count($names)],
                    'service_on' => $due->copy()->subDays($days)->toDateString(),
                    'due_on' => $due->toDateString(),
                    'status' => ServiceReminder::PENDING,
                ]);
                $n++;
            }
        }

        // Older review requests were handled already: most sent, some done, a couple skipped.
        ServiceReminder::query()->where('type', ServiceReminder::TYPE_REVIEW)
            ->whereDate('due_on', '<', $today->copy()->subDays(3)->toDateString())
            ->whereIn('status', ServiceReminder::OPEN)->get()
            ->each(function (ServiceReminder $r, int $i) use ($owner, $reminders) {
                if (! $owner) {
                    return;
                }
                $r->forceFill(['sent_at' => $r->due_on->copy()->setTime(11, 15), 'sent_by' => $owner->id, 'channel' => 'whatsapp'])->save();
                match ($i % 5) {
                    0, 1 => $r->forceFill(['status' => ServiceReminder::SENT])->save(),
                    4 => $reminders->close($r, $owner, ServiceReminder::SKIPPED, 'Owner called instead'),
                    default => $reminders->close($r, $owner, ServiceReminder::DONE),
                };
            });

        $reminders->markDue();
    }
}
