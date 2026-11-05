<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Activity\ActivityPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/** Activity log (UX-FLOWS page 18): who changed what, when. Owner and Admin (audit.view). */
class ActivityController extends Controller
{
    public function __invoke(Request $request)
    {
        $filters = [
            'user' => $request->integer('user') ?: null,
            'type' => is_string($request->query('type')) && $request->query('type') !== '' ? $request->query('type') : null,
            'from' => $this->date($request->query('from')),
            'to' => $this->date($request->query('to')),
        ];

        $tz = 'Asia/Kolkata';
        $rows = Activity::query()
            ->with(['causer', 'subject'])
            ->when($filters['user'], fn ($q, $u) => $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $u))
            ->when($filters['type'], fn ($q, $t) => $q->where('log_name', $t))
            ->when($filters['from'], fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d, $tz)->startOfDay()->utc()))
            ->when($filters['to'], fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d, $tz)->endOfDay()->utc()))
            ->orderByDesc('id')
            ->paginate(50)->withQueryString();

        $types = Activity::query()->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name');

        return view('activity.index', [
            'rows' => $rows,
            'present' => new ActivityPresenter,
            'filters' => $filters,
            'types' => $types,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
