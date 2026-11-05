{{--
    <x-reminders.home-block :user="$user" />
    "Reminders today" for the Home screen: count + the first 5 rows (overdue first). Renders nothing
    for people without reminders.manage or when the list is empty.
--}}
@props(['user', 'limit' => 5])
@php
    $count = \App\Support\Dashboard\Reminders::countForUser($user);
    $rows = $count ? \App\Support\Dashboard\Reminders::rowsForUser($user, $limit) : [];
@endphp
@if ($count)
    <x-ui.panel title="Reminders today" :subtitle="$count.' to send · check-ups and review requests'" flush>
        <x-slot:aside><a class="btn-quiet lg:min-h-8" href="{{ route('reminders.index') }}">See all</a></x-slot:aside>
        <ul class="divide-y divide-line">
            @foreach ($rows as $row)
                <li class="flex items-center justify-between gap-3 px-4 py-3 lg:px-5">
                    <div class="min-w-0">
                        <div class="truncate text-[15px] font-semibold">{{ $row['name'] }}@if ($row['car'])<span class="font-medium text-muted"> · {{ $row['car'] }}</span>@endif</div>
                        <div class="truncate text-[13px] text-muted">{{ $row['what'] }}</div>
                    </div>
                    <span @class(['shrink-0 text-[13px] font-semibold', 'text-bad' => $row['overdue'], 'text-ink' => ! $row['overdue']])>{{ $row['due_label'] }}</span>
                </li>
            @endforeach
        </ul>
    </x-ui.panel>
@endif
