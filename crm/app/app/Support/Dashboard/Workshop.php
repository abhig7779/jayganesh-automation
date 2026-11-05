<?php

namespace App\Support\Dashboard;

use App\Models\JobCard;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Workshop numbers for the shell's Home (DESIGN.md §7: "Cars in workshop", Technician home).
 * The shell's HomeData can call these behind a class_exists() guard.
 */
final class Workshop
{
    /**
     * counts keys: waiting, working, qc, ready, delivered_today.
     *
     * @return array{counts: array<string,int>, in_shop: int, late: Collection<int, JobCard>, board_url: string}
     */
    public static function summary(?int $branchId = null): array
    {
        $base = JobCard::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $byStatus = (clone $base)->open()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $counts = [];
        foreach ([JobCard::WAITING, JobCard::WORKING, JobCard::QC, JobCard::READY] as $s) {
            $counts[$s] = (int) ($byStatus[$s] ?? 0);
        }
        $counts['delivered_today'] = (clone $base)->where('status', JobCard::DELIVERED)
            ->where('delivered_at', '>=', JobCard::todayStart())->count();

        $late = (clone $base)->whereIn('status', [JobCard::WAITING, JobCard::WORKING, JobCard::QC])
            ->whereNotNull('promised_at')->where('promised_at', '<', now())
            ->with(['car', 'customer', 'technician'])->orderBy('promised_at')->limit(10)->get();

        return [
            'counts' => $counts,
            'in_shop' => array_sum(array_intersect_key($counts, array_flip(JobCard::OPEN))),
            'late' => $late,
            'board_url' => route('jobcards.index'),
        ];
    }

    /** The technician's open cars, soonest promise first, with items and photos loaded. No prices are needed. */
    public static function forTechnician(User $user): Collection
    {
        return JobCard::query()->forTechnician($user)
            ->whereIn('status', [JobCard::WAITING, JobCard::WORKING, JobCard::QC, JobCard::READY])
            ->with(['car', 'customer', 'items', 'photos'])
            ->orderByRaw("CASE status WHEN 'working' THEN 0 WHEN 'waiting' THEN 1 WHEN 'qc' THEN 2 ELSE 3 END")
            ->orderByRaw('promised_at IS NULL')->orderBy('promised_at')
            ->get();
    }
}
