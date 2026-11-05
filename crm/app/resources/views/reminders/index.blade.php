<x-layouts.app title="Reminders">
@php
    $link = fn (array $over) => route('reminders.index', array_filter(array_merge(['show' => $show, 'type' => $type, 'q' => $q], $over), fn ($v) => $v !== null && $v !== '' && $v !== 'today'));
    $subtitle = $counts['today']
        ? $counts['today'].' to send today'.($counts['overdue'] ? ' · '.$counts['overdue'].' overdue' : '')
        : 'Service check-ups and Google review requests, from saved bills.';
@endphp

    <x-ui.page-header title="Reminders today" :subtitle="$subtitle">
        <x-slot:actions>
            @can('settings.manage')
                <a href="{{ route('reminders.rules.index') }}" class="btn-ghost"><x-icon name="cog" class="size-4" /> Reminder rules</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if ($reviewLinkMissing)
        <div class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn">
            <x-icon name="alert" class="size-5 shrink-0" />
            <span class="flex-1 font-medium">Review requests are waiting for your Google review link.</span>
            @can('settings.manage')
                <a href="{{ route('settings.edit') }}" class="font-semibold underline underline-offset-4">Add it in Settings</a>
            @else
                <span>Ask the owner to add it in Settings.</span>
            @endcan
        </div>
    @endif

    @if ($errors->has('reminder'))
        <div role="alert" class="mb-4 rounded-xl border border-bad-line bg-bad-tint px-4 py-3 text-sm font-medium text-bad">{{ $errors->first('reminder') }}</div>
    @endif

    {{-- Filters: which list, which kind, search --}}
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <nav class="-mx-4 flex gap-1 overflow-x-auto px-4 [scrollbar-width:none] lg:mx-0 lg:px-0" aria-label="Which reminders">
            <div class="flex shrink-0 gap-1 rounded-lg bg-sunken p-1">
                @foreach ($views as $key => $label)
                    @php $n = $counts[$key] ?? null; @endphp
                    <a href="{{ $link(['show' => $key, 'page' => null]) }}" @class([
                        'flex h-9 items-center gap-1.5 rounded-md px-3 text-sm font-semibold whitespace-nowrap',
                        'border border-line bg-surface text-ink' => $show === $key,
                        'text-muted hover:text-ink' => $show !== $key,
                    ]) @if ($show === $key) aria-current="page" @endif>
                        {{ $label }}
                        @if ($n)<span class="tnum {{ $key === 'today' && $counts['overdue'] ? 'text-bad' : 'text-muted' }}">{{ $n }}</span>@endif
                    </a>
                @endforeach
            </div>
            <div class="flex shrink-0 gap-1 lg:ml-3">
                @foreach ([null => 'All', 'service' => 'Check-ups', 'review' => 'Reviews'] as $key => $label)
                    <a href="{{ $link(['type' => $key ?: null, 'page' => null]) }}" @class([
                        'flex h-11 items-center rounded-full border px-3.5 text-[13px] font-semibold whitespace-nowrap lg:h-9',
                        'border-ink bg-ink text-white' => ($type ?? '') === (string) $key,
                        'border-line-strong bg-surface text-ink hover:bg-sunken' => ($type ?? '') !== (string) $key,
                    ])>{{ $label }}</a>
                @endforeach
            </div>
        </nav>
        <form method="GET" action="{{ route('reminders.index') }}" class="relative lg:w-80" role="search">
            @if ($show !== 'today')<input type="hidden" name="show" value="{{ $show }}">@endif
            @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-faint" />
            <input name="q" value="{{ $q }}" class="input pl-9" placeholder="Name, mobile or car number" aria-label="Search reminders">
        </form>
    </div>

    @if (! $rows)
        <div class="card">
            @if ($q !== '')
                <x-ui.empty-state icon="search" :text="'Nothing matches “'.$q.'” in this list.'">
                    <a class="btn-ghost" href="{{ $link(['q' => null]) }}">Clear search</a>
                </x-ui.empty-state>
            @elseif ($show === 'today')
                <x-ui.empty-state icon="check" text="Nobody to remind today. New check-ups and review requests appear here on their day.">
                    <a class="btn-ghost" href="{{ $link(['show' => 'upcoming']) }}">See the next 30 days</a>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state icon="calendar" text="Nothing in this list yet." />
            @endif
        </div>
    @else
        {{-- PC: one table, overdue first --}}
        <div class="hidden overflow-hidden rounded-xl border border-line bg-surface lg:block">
            <table class="w-full text-left text-[13px]">
                <thead class="bg-sunken text-xs font-semibold tracking-[0.06em] text-muted uppercase">
                    <tr>
                        <th class="w-32 px-5 py-3">{{ in_array($show, ['sent', 'closed'], true) ? 'Was due' : 'Due' }}</th>
                        <th class="px-3 py-3">Customer · car</th>
                        <th class="px-3 py-3">What's due</th>
                        <th class="w-32 px-3 py-3">Last service</th>
                        <th class="w-[390px] px-5 py-3 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as $row)
                        <tr id="reminder-{{ $row['id'] }}" class="align-top hover:bg-[#FAFAFA]">
                            <td @class(['border-l-[3px] px-5 py-3', 'border-l-bad' => $row['overdue'], 'border-l-transparent' => ! $row['overdue']])>
                                <div @class(['font-semibold whitespace-nowrap', 'text-bad' => $row['overdue'], 'text-ink' => ! $row['overdue']])>{{ $row['due_label'] }}</div>
                                <div class="text-xs text-muted">{{ \Illuminate\Support\Carbon::parse($row['due_on'])->format('j M') }}</div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($row['customer_url'])
                                        <a href="{{ $row['customer_url'] }}" class="text-[15px] font-semibold text-ink hover:underline">{{ $row['name'] }}</a>
                                    @else
                                        <span class="text-[15px] font-semibold">{{ $row['name'] }}</span>
                                    @endif
                                    @if ($row['plate'])<span class="rounded border border-line-strong px-1.5 text-[11px] font-semibold tracking-wide tnum">{{ $row['plate'] }}</span>@endif
                                </div>
                                <div class="mt-0.5 text-muted">{{ $row['car'] }}</div>
                                @if ($row['mobile'])<div class="text-muted tnum whitespace-nowrap">{{ $row['mobile'] }}</div>@endif
                                @if ($row['opted_out'])<x-ui.status-badge tone="warn" class="mt-1">Opted out of WhatsApp: call instead</x-ui.status-badge>@endif
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    <x-ui.status-badge :tone="$row['type'] === 'review' ? 'ink' : 'muted'">{{ $row['type_label'] }}</x-ui.status-badge>
                                    <span class="font-semibold text-ink">{{ $row['what'] }}</span>
                                </div>
                                @if ($row['service'] && $row['type'] === 'service')<div class="mt-0.5 text-muted">{{ $row['service'] }}</div>@endif
                                @if ($row['sent_label'])<div class="mt-0.5 text-xs text-muted">{{ $row['sent_label'] }}</div>@endif
                                <details class="mt-1 text-xs">
                                    <summary class="cursor-pointer font-semibold text-ink-2 select-none">See message</summary>
                                    <p class="mt-1.5 max-w-xl rounded-lg border border-line bg-canvas px-3 py-2 whitespace-pre-line text-ink-2">{{ $row['message'] }}</p>
                                </details>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap text-ink-2">
                                {{ $row['last_service'] ?? '—' }}
                                @if ($row['bill_url'])<div><a href="{{ $row['bill_url'] }}" class="text-xs font-semibold text-muted underline underline-offset-2 hover:text-ink">See bill</a></div>@endif
                            </td>
                            <td class="px-5 py-3">@include('reminders._actions', ['row' => $row])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Phone: one card per customer, big buttons --}}
        <div class="space-y-2 lg:hidden">
            @foreach ($rows as $row)
                <article @class(['rounded-xl border bg-surface p-4', 'border-bad-line' => $row['overdue'], 'border-line' => ! $row['overdue']])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate text-[15px] font-semibold">{{ $row['name'] }}</span>
                                @if ($row['plate'])<span class="rounded border border-line-strong px-1.5 text-[11px] font-semibold tracking-wide tnum">{{ $row['plate'] }}</span>@endif
                            </div>
                            <div class="mt-0.5 text-[13px] text-ink-2">{{ collect([$row['car'], $row['what']])->filter()->implode(' · ') }}</div>
                            <div class="mt-0.5 text-[13px] text-muted">Last service {{ $row['last_service'] ?? '—' }}</div>
                        </div>
                        <div @class(['shrink-0 text-right text-[13px] font-semibold', 'text-bad' => $row['overdue'], 'text-ink' => ! $row['overdue']])>
                            {{ $row['due_label'] }}
                            <div><x-ui.status-badge :tone="$row['type'] === 'review' ? 'ink' : 'muted'">{{ $row['type_label'] }}</x-ui.status-badge></div>
                        </div>
                    </div>
                    @if ($row['opted_out'])<p class="mt-2 text-[13px] font-medium text-warn">Opted out of WhatsApp: call instead.</p>@endif
                    <div class="mt-3">@include('reminders._actions', ['row' => $row, 'phone' => true])</div>
                </article>
            @endforeach
        </div>

        @if ($page->hasPages())<div class="mt-4">{{ $page->links() }}</div>@endif
    @endif
</x-layouts.app>
