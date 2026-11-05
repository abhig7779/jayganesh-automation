@php
    use App\Support\Activity\ActivityPresenter;
    $hasFilters = $filters['user'] || $filters['type'] || $filters['from'] || $filters['to'];
@endphp
<x-layouts.app title="Activity log">
    <x-ui.page-header title="Activity log" subtitle="Who changed what, and when. Nothing here can be edited." />

    <form method="GET" action="{{ route('activity.index') }}" class="mb-4 grid grid-cols-2 gap-2 lg:grid-cols-[1fr_1fr_150px_150px_auto_auto]">
        <select name="user" class="input col-span-2 lg:col-span-1" aria-label="Who" data-autosubmit>
            <option value="">Everyone</option>
            @foreach ($users as $u) <option value="{{ $u->id }}" @selected($filters['user'] === $u->id)>{{ $u->name }}</option> @endforeach
        </select>
        <select name="type" class="input col-span-2 lg:col-span-1" aria-label="Type" data-autosubmit>
            <option value="">All types</option>
            @foreach ($types as $t) <option value="{{ $t }}" @selected($filters['type'] === $t)>{{ ActivityPresenter::typeLabel($t) }}</option> @endforeach
        </select>
        <input type="date" name="from" value="{{ $filters['from'] }}" class="input" aria-label="From date">
        <input type="date" name="to" value="{{ $filters['to'] }}" class="input" aria-label="To date">
        <button class="btn-ghost col-span-2 lg:col-span-1"><x-icon name="filter" class="size-5" /> Filter</button>
        @if ($hasFilters) <a href="{{ route('activity.index') }}" class="btn-quiet">Clear</a> @endif
    </form>

    <section class="overflow-hidden rounded-xl border border-line bg-surface">
        @if ($rows->isEmpty())
            <x-ui.empty-state icon="history" :text="$hasFilters ? 'Nothing matches these filters.' : 'No activity yet. Changes to products, bills, staff and more show up here.'">
                @if ($hasFilters) <a href="{{ route('activity.index') }}" class="btn-ghost">Clear filters</a> @endif
            </x-ui.empty-state>
        @else
            <ul class="divide-y divide-line">
                @foreach ($rows as $a)
                    @php $changes = $present->changes($a); $when = $a->created_at->timezone('Asia/Kolkata'); @endphp
                    <li class="grid grid-cols-1 gap-x-4 gap-y-1 px-4 py-3 lg:grid-cols-[150px_170px_1fr] lg:px-5">
                        <div class="text-[13px] text-muted lg:pt-0.5">
                            <time datetime="{{ $a->created_at->toIso8601String() }}" title="{{ $when->format('j M Y, g:i:s a') }}">{{ $when->isToday() ? 'Today' : ($when->isYesterday() ? 'Yesterday' : $when->format('j M Y')) }}, {{ $when->format('g:i a') }}</time>
                        </div>
                        <div class="flex items-center gap-2 lg:items-start">
                            @if ($a->causer)
                                <x-ui.avatar-initials :name="$a->causer->name" size="sm" />
                                <span class="truncate text-sm font-semibold text-ink">{{ $a->causer->name }}</span>
                            @else
                                <span class="text-sm font-medium text-muted">System</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.status-badge tone="muted">{{ ActivityPresenter::typeLabel($a->log_name) }}</x-ui.status-badge>
                                <span class="text-sm font-medium text-ink">{{ $present->what($a) }}</span>
                            </div>
                            @if ($changes)
                                <dl class="mt-1.5 grid grid-cols-[minmax(110px,auto)_1fr] gap-x-3 gap-y-0.5 text-[13px]">
                                    @foreach (array_slice($changes, 0, 8) as $c)
                                        <dt class="text-muted">{{ $c['label'] }}</dt>
                                        <dd class="min-w-0 break-words text-ink-2">
                                            @if ($c['before'] !== null)
                                                <span class="text-muted line-through decoration-line-strong">{{ $c['before'] }}</span>
                                                <span aria-label="changed to" class="px-1 text-faint">→</span>
                                            @endif
                                            <span class="font-semibold text-ink">{{ $c['after'] }}</span>
                                        </dd>
                                    @endforeach
                                </dl>
                                @if (count($changes) > 8)
                                    <p class="mt-1 text-xs text-muted">and {{ count($changes) - 8 }} more fields</p>
                                @endif
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($rows->hasPages())
                <div class="border-t border-line px-4 py-3">{{ $rows->links() }}</div>
            @endif
        @endif
    </section>
</x-layouts.app>
