<?php

namespace App\Services\Reminders;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\ReminderRule;
use App\Models\ServiceReminder;
use App\Models\Setting;
use App\Models\User;
use App\Services\WhatsApp\WhatsApp;
use App\Support\Quotations\ShopClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service check-ups and Google review requests (CRM-PLAN §3 item 14).
 * - A saved bill creates check-up reminders from the matching rules, plus one review request
 *   the day after a paid bill (at most once per customer every 90 days).
 * - A cancelled bill cancels its open reminders.
 * - Staff send them from "Reminders today": a wa.me link, or a template in Cloud API mode.
 */
class ReminderService
{
    /** At most one review request per customer in this many days. */
    public const REVIEW_GAP_DAYS = 90;

    public const DEFAULT_REVIEW_MESSAGE = "Hello {name}, thank you for choosing {shop} for your {car}. If you were happy with our work, could you spare a minute to leave us a Google review? It really helps a small shop like ours.\n\n{link}\n\nThank you!";

    public function __construct(private readonly WhatsApp $whatsApp) {}

    /** Listener entry for InvoiceSaved. Safe to call twice for the same bill. */
    public function forInvoice(Invoice $invoice): void
    {
        if ($invoice->isCancelled() || ! $invoice->customer_id) {
            return;
        }
        $this->serviceRemindersFor($invoice);
        $this->reviewFor($invoice);
    }

    /** @return int how many reminders were created */
    public function serviceRemindersFor(Invoice $invoice): int
    {
        $invoice->loadMissing('items.product.category');
        $rules = ReminderRule::query()->active()->get();
        $billDate = $this->billDate($invoice);
        $made = 0;

        foreach ($rules as $rule) {
            $item = $invoice->items->first(fn ($i) => $rule->matches($i));
            if (! $item) {
                continue;
            }

            DB::transaction(function () use ($invoice, $rule, $item, $billDate, &$made) {
                // A newer visit for the same thing replaces the older check-ups (a car washed on
                // day 20 doesn't need the day-30 reminder from the last wash).
                ServiceReminder::query()
                    ->where('type', ServiceReminder::TYPE_SERVICE)
                    ->where('customer_id', $invoice->customer_id)
                    ->where('customer_car_id', $invoice->customer_car_id)
                    ->where('reminder_rule_id', $rule->id)
                    ->where('invoice_id', '!=', $invoice->id)
                    ->whereDate('service_on', '<=', $billDate->toDateString())
                    ->whereIn('status', ServiceReminder::OPEN)
                    ->update([
                        'status' => ServiceReminder::SKIPPED,
                        'note' => 'Came back on bill '.$invoice->number,
                        'superseded_by_invoice_id' => $invoice->id,
                        'closed_at' => now(),
                        'updated_at' => now(),
                    ]);

                foreach ($rule->offsets() as $days) {
                    $r = ServiceReminder::query()->firstOrCreate(
                        ['invoice_id' => $invoice->id, 'reminder_rule_id' => $rule->id, 'offset_days' => $days],
                        [
                            'branch_id' => $invoice->branch_id,
                            'type' => ServiceReminder::TYPE_SERVICE,
                            'customer_id' => $invoice->customer_id,
                            'customer_car_id' => $invoice->customer_car_id,
                            'invoice_item_id' => $item->id,
                            'service_name' => $item->name,
                            'service_on' => $billDate->toDateString(),
                            'due_on' => $billDate->copy()->addDays($days)->toDateString(),
                            'status' => ServiceReminder::PENDING,
                        ],
                    );
                    $made += $r->wasRecentlyCreated ? 1 : 0;
                }
            });
        }

        return $made;
    }

    /**
     * A review request the day(s) after a paid bill. Null when switched off, capped or not paid.
     * $paidOn: when an old due was cleared later, count the delay from that day instead.
     */
    public function reviewFor(Invoice $invoice, ?Carbon $paidOn = null): ?ServiceReminder
    {
        if (! Setting::get('reviews.enabled') || $invoice->status !== Invoice::STATUS_PAID || ! $invoice->customer_id) {
            return null;
        }
        if ($this->reviewLink() === '') {
            return null; // nothing to send them to yet: the owner adds the link in Settings
        }
        $customer = Customer::query()->find($invoice->customer_id);
        if (! $customer || ! $customer->mobile || ! $customer->whatsapp_opt_in) {
            return null;
        }

        $from = $paidOn ? $paidOn->copy()->timezone(ShopClock::TZ)->startOfDay() : $this->billDate($invoice);
        $due = $from->addDays(max(0, (int) Setting::get('reviews.delay_days')));

        $recent = ServiceReminder::query()
            ->where('type', ServiceReminder::TYPE_REVIEW)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', ServiceReminder::CANCELLED)
            ->whereDate('due_on', '>', $due->copy()->subDays(self::REVIEW_GAP_DAYS)->toDateString())
            ->whereDate('due_on', '<', $due->copy()->addDays(self::REVIEW_GAP_DAYS)->toDateString())
            ->exists();
        if ($recent) {
            return null;
        }

        return ServiceReminder::query()->create([
            'branch_id' => $invoice->branch_id,
            'type' => ServiceReminder::TYPE_REVIEW,
            'customer_id' => $customer->id,
            'customer_car_id' => $invoice->customer_car_id,
            'invoice_id' => $invoice->id,
            'service_name' => $invoice->items()->value('name'),
            'service_on' => $this->billDate($invoice)->toDateString(),
            'due_on' => $due->toDateString(),
            'status' => ServiceReminder::PENDING,
        ]);
    }

    /** Listener entry for InvoiceCancelled. Brings back the check-ups this bill had replaced. */
    public function cancelForInvoice(Invoice $invoice): int
    {
        return DB::transaction(function () use ($invoice) {
            $n = ServiceReminder::query()->where('invoice_id', $invoice->id)
                ->whereIn('status', ServiceReminder::OPEN)
                ->update([
                    'status' => ServiceReminder::CANCELLED,
                    'note' => 'Bill '.$invoice->number.' cancelled',
                    'closed_at' => now(),
                    'updated_at' => now(),
                ]);

            ServiceReminder::query()->where('superseded_by_invoice_id', $invoice->id)
                ->where('status', ServiceReminder::SKIPPED)
                ->update([
                    'status' => ServiceReminder::PENDING,
                    'note' => null,
                    'superseded_by_invoice_id' => null,
                    'closed_at' => null,
                    'updated_at' => now(),
                ]);

            return $n;
        });
    }

    /** Daily: pending reminders whose day has come become due. */
    public function markDue(): int
    {
        return ServiceReminder::query()->where('status', ServiceReminder::PENDING)
            ->whereDate('due_on', '<=', ShopClock::today())
            ->update(['status' => ServiceReminder::DUE, 'updated_at' => now()]);
    }

    /** The WhatsApp text staff send (manual mode), with the placeholders filled in. */
    public function message(ServiceReminder $r): string
    {
        $template = $r->isReview()
            ? (trim((string) Setting::get('reviews.message')) ?: self::DEFAULT_REVIEW_MESSAGE)
            : (trim((string) $r->rule?->message) ?: "Hello {name}, it is time for the check-up after the {service} on your {car} ({date}). Reply here to book a time.\n\nThank you, {shop}");

        $text = strtr($template, $this->placeholders($r));

        return trim(preg_replace("/[ \t]+\n/", "\n", preg_replace('/ {2,}/', ' ', $text)));
    }

    /** {name} {car} {service} {date} {shop} {link} */
    public function placeholders(ServiceReminder $r): array
    {
        $r->loadMissing(['customer', 'car', 'rule']);

        return [
            '{name}' => trim((string) $r->customer?->name) ?: 'there',
            '{car}' => $r->carLabel() ?: 'car',
            '{service}' => $r->service_name ?: ($r->rule?->name ?? 'service'),
            '{date}' => $r->service_on?->format('j M Y') ?? '',
            '{shop}' => (string) Setting::get('shop.name'),
            '{link}' => $this->reviewLink(),
        ];
    }

    public function reviewLink(): string
    {
        return trim((string) (Setting::get('reviews.google_link') ?: Setting::get('shop.maps_url') ?: ''));
    }

    public function manualLink(ServiceReminder $r): ?string
    {
        $mobile = $r->customer?->mobile;

        return $mobile ? $this->whatsApp->manualLink($mobile, $this->message($r)) : null;
    }

    /** The approved template name for this reminder, or '' when none is set. */
    public function templateName(ServiceReminder $r): string
    {
        return trim((string) Setting::get($r->isReview() ? 'whatsapp.template_review' : 'reminders.template_service'));
    }

    /** Cloud API mode with an approved template name: the button sends instead of opening WhatsApp. */
    public function sendsAutomatically(ServiceReminder $r): bool
    {
        return $this->whatsApp->isAutomatic() && $this->templateName($r) !== '';
    }

    /** Template body values, in order: review = name, link; check-up = name, car, service, date. */
    public function templateParams(ServiceReminder $r): array
    {
        $p = $this->placeholders($r);

        return $r->isReview()
            ? [$p['{name}'], $p['{link}']]
            : [$p['{name}'], $p['{car}'], $p['{service}'], $p['{date}']];
    }

    public function sendAutomatic(ServiceReminder $r, User $by): MessageLog
    {
        $log = $this->whatsApp->sendTemplate(
            (string) $r->customer->mobile, $this->templateName($r), $this->templateParams($r), null, null, $r,
        );
        if ($log->status !== 'failed') {
            $this->markSent($r, $by, 'whatsapp_auto', $log->id);
        }

        return $log;
    }

    public function markSent(ServiceReminder $r, User $by, string $channel, ?int $logId = null): void
    {
        $r->forceFill([
            'status' => ServiceReminder::SENT,
            'channel' => $channel,
            'sent_at' => now(),
            'sent_by' => $by->id,
            'message_log_id' => $logId,
        ])->save();
    }

    public function close(ServiceReminder $r, User $by, string $status, ?string $note = null): void
    {
        $r->forceFill([
            'status' => $status,
            'closed_at' => now(),
            'closed_by' => $by->id,
            'note' => $note !== null ? mb_substr($note, 0, 200) : $r->note,
        ])->save();
    }

    private function billDate(Invoice $invoice): Carbon
    {
        return ($invoice->created_at ?? now())->copy()->timezone(ShopClock::TZ)->startOfDay();
    }
}
