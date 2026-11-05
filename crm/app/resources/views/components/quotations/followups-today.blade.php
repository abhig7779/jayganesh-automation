@props(['user', 'limit' => 5])
@php
    // "Follow-ups today" block for the Home screen (UX-FLOWS §2). Usage: <x-quotations.followups-today :user="$user" />
    $rows = \App\Support\Dashboard\FollowUps::rowsForUser($user, $limit);
    $total = \App\Support\Dashboard\FollowUps::countForUser($user);
    $overdue = \App\Support\Dashboard\FollowUps::overdueCountForUser($user);
@endphp
@if ($total > 0)
<section {{ $attributes->merge(['class' => 'rounded-xl border border-zinc-200 bg-white']) }}>
    <div class="flex items-center justify-between px-4 py-3">
        <h2 class="flex items-center gap-2 text-[15px] font-semibold">Follow-ups today
            <span class="rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-600">{{ $total }} due{{ $overdue ? ' · '.$overdue.' overdue' : '' }}</span>
        </h2>
        <a href="{{ route('followups.index') }}" class="text-sm font-semibold underline-offset-2 hover:underline">See all</a>
    </div>
    <ul class="divide-y divide-zinc-200 border-t border-zinc-200">
        @foreach ($rows as $r)
            <li @class(['flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between', 'border-l-[3px] border-l-red-600' => $r['overdue']])>
                <div class="min-w-0">
                    <div class="truncate text-[15px] font-semibold">{{ $r['name'] }}@if ($r['car'])<span class="font-medium text-zinc-600"> · {{ $r['car'] }}</span>@endif</div>
                    <div class="truncate text-[13px] text-zinc-700"><span class="font-semibold text-ink">{{ $r['kind_label'] }} {{ $r['value'] }}</span> · {{ $r['asked'] }}</div>
                    <div @class(['text-xs font-semibold', 'text-red-600' => $r['overdue'], 'text-zinc-600' => ! $r['overdue']])>{{ $r['overdue'] ? 'Overdue '.str_replace(' late', '', $r['due_label']).' · ' : '' }}{{ $r['meta'] }}</div>
                </div>
                <div class="grid shrink-0 grid-cols-3 gap-2 sm:flex">
                    <a href="{{ $r['wa_url'] }}" target="_blank" rel="noopener" class="inline-flex min-h-12 items-center justify-center gap-1.5 rounded-lg border border-green-700 px-3 text-sm font-semibold text-green-700 sm:min-h-9"><x-quotations.icon name="chat" class="size-4" />WhatsApp</a>
                    <a href="{{ $r['call_url'] }}" class="inline-flex min-h-12 items-center justify-center gap-1.5 rounded-lg border border-zinc-300 px-3 text-sm font-semibold sm:min-h-9"><x-quotations.icon name="phone" class="size-4" />Call</a>
                    <a href="{{ route('followups.index', ['select' => $r['id']]) }}" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-ink px-3 text-sm font-semibold text-white sm:min-h-9">Log result</a>
                </div>
            </li>
        @endforeach
    </ul>
</section>
@endif
