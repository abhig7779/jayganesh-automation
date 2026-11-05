<?php

namespace App\Support\Dashboard;

use App\Models\ServiceReminder;
use App\Models\User;
use App\Services\Reminders\ReminderService;
use App\Support\Phone;
use App\Support\Quotations\ShopClock;
use App\Support\RegNo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * "Reminders today" for the Home screen and the menu badge. The shell's HomeData can call:
 *   Reminders::countForUser($user)    → int (due today + overdue; 0 without reminders.manage)
 *   Reminders::rowsForUser($user, 5)  → plain rows, overdue first
 * or render `<x-reminders.home-block :user="$user" />`. Safe before the migration runs.
 */
final class Reminders
{
    public static function countForUser(User $user): int
    {
        if (! $user->can('reminders.manage') || ! Schema::hasTable('service_reminders')) {
            return 0;
        }

        return ServiceReminder::query()->dueByToday()->count();
    }

    public static function overdueCountForUser(User $user): int
    {
        if (! $user->can('reminders.manage') || ! Schema::hasTable('service_reminders')) {
            return 0;
        }

        return ServiceReminder::query()->overdue()->count();
    }

    public static function rowsForUser(User $user, int $limit = 5): array
    {
        if (! $user->can('reminders.manage') || ! Schema::hasTable('service_reminders')) {
            return [];
        }

        return ServiceReminder::query()->dueByToday()
            ->with(['customer', 'car', 'rule'])
            ->orderBy('due_on')->orderBy('id')->limit($limit)->get()
            ->map(fn (ServiceReminder $r) => self::row($r))->all();
    }

    /** One plain row for the list, the Home block or a test. */
    public static function row(ServiceReminder $r): array
    {
        $service = app(ReminderService::class);
        $today = ShopClock::todayDate();
        $due = Carbon::parse($r->due_on->toDateString(), ShopClock::TZ);
        $late = (int) $due->diffInDays($today, false);
        $customer = $r->customer;
        $optedOut = $customer && (! $customer->whatsapp_opt_in || $customer->opted_out_at !== null);

        return [
            'id' => $r->id,
            'type' => $r->type,
            'type_label' => $r->isReview() ? 'Review' : 'Check-up',
            'status' => $r->status,
            'status_label' => ServiceReminder::STATUS_LABELS[$r->status] ?? $r->status,
            'open' => $r->isOpen(),
            'name' => $customer?->name ?? 'Customer',
            'mobile' => $customer?->mobile ? Phone::pretty($customer->mobile) : '',
            'car' => $r->carLabel(),
            'plate' => $r->car?->reg_no ? RegNo::pretty($r->car->reg_no) : null,
            'what' => $r->whatLabel(),
            'service' => (string) $r->service_name,
            'last_service' => $r->service_on?->format('j M Y'),
            'due_on' => $r->due_on->toDateString(),
            'due_label' => match (true) {
                $late > 1 => $late.' days late',
                $late === 1 => '1 day late',
                $late === 0 => 'Today',
                $late === -1 => 'Tomorrow',
                default => $due->format('D j M'),
            },
            'overdue' => $r->isOpen() && $late > 0,
            'sent_label' => $r->sent_at ? 'Sent '.$r->sent_at->timezone(ShopClock::TZ)->format('j M, g:i a').($r->sender ? ' by '.$r->sender->name : '') : null,
            'note' => $r->note,
            'opted_out' => $optedOut,
            'has_mobile' => (bool) $customer?->mobile,
            'auto' => $service->sendsAutomatically($r),
            'message' => $service->message($r),
            'wa_url' => route('reminders.whatsapp', $r),
            'send_url' => route('reminders.send', $r),
            'tel' => $customer?->mobile ? 'tel:'.$customer->mobile : null,
            'done_url' => route('reminders.done', $r),
            'skip_url' => route('reminders.skip', $r),
            'customer_url' => $customer && Route::has('customers.show') ? route('customers.show', $customer) : null,
            'bill_url' => $r->invoice_id && Route::has('bills.show') ? route('bills.show', $r->invoice_id) : null,
        ];
    }
}
