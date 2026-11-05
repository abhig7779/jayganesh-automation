<?php

namespace App\Services\Quotations;

use App\Models\FollowUp;
use App\Models\Quotation;
use App\Models\User;
use App\Support\Quotations\ShopClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The owner's "where are quotes leaking" numbers for one month (UX-FLOWS §3.2 step 8).
 * Revised quotes count once (only the latest version). Expired counts as lost ("No reply").
 */
class LeakReport
{
    public function forMonth(?Carbon $month = null): array
    {
        $month ??= ShopClock::now();
        $from = $month->copy()->timezone(ShopClock::TZ)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $today = ShopClock::today();

        $quotes = Quotation::query()
            ->whereNull('revised_to_id')
            ->whereBetween('created_at', [$from->copy()->utc(), $to->copy()->utc()])
            ->get(['id', 'assigned_to', 'status', 'total_paise', 'lost_reason', 'sent_at']);

        // Follow-ups due this month up to today (skipped ones were never needed, so they don't count).
        $followUps = FollowUp::query()
            ->whereDate('due_on', '>=', $from->toDateString())
            ->whereDate('due_on', '<=', min($to->toDateString(), $today))
            ->whereIn('status', [FollowUp::DONE, FollowUp::PENDING])
            ->get(['id', 'assigned_to', 'status', 'due_on', 'done_at'])
            // Today's still-pending ones aren't late yet, so they don't count against anyone.
            ->reject(fn (FollowUp $f) => $f->status === FollowUp::PENDING && $f->due_on->toDateString() >= $today)
            ->values();

        $overdueNow = FollowUp::query()->overdue()->nextPerSubject()->get(['id', 'assigned_to']);

        $lostReasons = $quotes->whereIn('status', [Quotation::LOST, Quotation::EXPIRED])
            ->groupBy(fn ($q) => $q->status === Quotation::EXPIRED ? 'no_reply' : ($q->lost_reason ?: 'other'))
            ->map(fn (Collection $g, $reason) => [
                'reason' => $reason,
                'label' => Quotation::LOST_REASONS[$reason] ?? ucfirst(str_replace('_', ' ', $reason)),
                'count' => $g->count(),
                'paise' => (int) $g->sum('total_paise'),
            ])->sortByDesc('count')->values()->all();

        $staffIds = $quotes->pluck('assigned_to')->merge($followUps->pluck('assigned_to'))->merge($overdueNow->pluck('assigned_to'))->filter()->unique();
        $names = User::query()->whereKey($staffIds)->pluck('name', 'id');
        $staff = $staffIds->map(function ($id) use ($quotes, $followUps, $overdueNow, $names) {
            $mine = $quotes->where('assigned_to', $id);
            $fu = $followUps->where('assigned_to', $id);

            return [
                'user_id' => $id,
                'name' => $names[$id] ?? 'Unknown',
                'quotes' => $mine->count(),
                'followups_done' => $fu->where('status', FollowUp::DONE)->count(),
                'followups_on_time_pct' => self::pct($fu->filter(fn ($f) => self::onTime($f))->count(), $fu->count()),
                'overdue' => $overdueNow->where('assigned_to', $id)->count(),
                'won' => $mine->where('status', Quotation::WON)->count(),
                'conversion_pct' => self::pct($mine->where('status', Quotation::WON)->count(), $mine->count()),
                'won_paise' => (int) $mine->where('status', Quotation::WON)->sum('total_paise'),
            ];
        })->sortByDesc('quotes')->values()->all();

        $won = $quotes->where('status', Quotation::WON);
        $lost = $quotes->whereIn('status', [Quotation::LOST, Quotation::EXPIRED]);

        return [
            'month_label' => $from->format('F Y'),
            'quotes' => $quotes->count(),
            'quotes_sent' => $quotes->whereNotNull('sent_at')->count(),
            'quoted_paise' => (int) $quotes->sum('total_paise'),
            'open' => $quotes->whereIn('status', Quotation::ACTIVE)->count(),
            'followups_due' => $followUps->count(),
            'followups_on_time' => $followUps->filter(fn ($f) => self::onTime($f))->count(),
            'followups_on_time_pct' => self::pct($followUps->filter(fn ($f) => self::onTime($f))->count(), $followUps->count()),
            'overdue_now' => $overdueNow->count(),
            'won' => $won->count(),
            'lost' => $lost->count(),
            'converted_pct' => self::pct($won->count(), $quotes->count()),
            'won_paise' => (int) $won->sum('total_paise'),
            'lost_paise' => (int) $lost->sum('total_paise'),
            'lost_reasons' => $lostReasons,
            'staff' => $staff,
        ];
    }

    /** Done on or before its due day (shop time). */
    public static function onTime(FollowUp $f): bool
    {
        return $f->status === FollowUp::DONE && $f->done_at
            && $f->done_at->copy()->timezone(ShopClock::TZ)->toDateString() <= $f->due_on->toDateString();
    }

    private static function pct(int $part, int $whole): int
    {
        return $whole > 0 ? (int) round($part * 100 / $whole) : 0;
    }
}
