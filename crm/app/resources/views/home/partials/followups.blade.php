{{--
    "Follow-ups today" (UX-FLOWS §3.2, approved D0 / D5 / M1). Rows come from the quotations
    module (Dashboard\FollowUps via HomeData::followUpsToday): overdue first with a red edge,
    then today's. WhatsApp and Call go through the module's routes so the contact is logged;
    "Log result" opens the work queue with that row selected.
    $followUps = null when this person can't chase quotes or leads.
--}}
@if ($followUps !== null)
    <div x-data="{ filter: 'all' }">
    <x-ui.panel title="Follow-ups today" flush>
        <x-slot:aside>
            @if ($followUps['count'])
                <span class="badge badge-bad">{{ $followUps['count'] }} due @if ($followUps['overdue']) · {{ $followUps['overdue'] }} overdue @endif</span>
            @endif
            @if ($followUps['overdue'] && $followUps['overdue'] < $followUps['count'])
                <div class="hidden rounded-lg bg-sunken p-0.5 sm:flex">
                    <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-ink text-white' : 'text-muted hover:text-ink'" class="rounded-md px-2.5 py-1 text-xs font-semibold">All</button>
                    <button type="button" @click="filter = 'overdue'" :class="filter === 'overdue' ? 'bg-ink text-white' : 'text-muted hover:text-ink'" class="rounded-md px-2.5 py-1 text-xs font-semibold">Overdue ({{ $followUps['overdue'] }})</button>
                </div>
            @endif
            @if ($followUps['url'] && $followUps['count'])
                <a href="{{ $followUps['url'] }}" class="text-[13px] font-semibold text-ink hover:underline">See all</a>
            @endif
        </x-slot:aside>
        @if (empty($followUps['rows']))
            <x-ui.empty-state icon="check" text="No follow-ups due today. Nice work." compact />
        @else
            <ul class="divide-y divide-line">
                @foreach ($followUps['rows'] as $f)
                    <li @class(['relative flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 lg:px-5', 'bg-bad-tint/40 before:absolute before:inset-y-0 before:left-0 before:w-[3px] before:bg-bad' => $f['overdue']])
                        x-show="filter === 'all' || {{ $f['overdue'] ? 'true' : 'false' }}" data-followup="{{ $f['id'] }}" @if ($f['overdue']) data-overdue @endif>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[15px] font-semibold lg:text-sm">
                                {{ $f['name'] }}@if ($f['car'])<span class="font-medium text-muted"> · {{ $f['car'] }}</span>@endif
                            </div>
                            <div class="truncate text-[13px] font-medium text-ink-2">{{ $f['detail'] }}</div>
                            <div @class(['mt-0.5 flex items-center gap-1 truncate text-xs font-semibold', 'text-bad' => $f['overdue'], 'text-muted' => ! $f['overdue']])>
                                <x-icon :name="$f['overdue'] ? 'alert' : 'clock'" class="size-3.5 shrink-0" /> <span class="truncate">{{ $f['when'] }}</span>
                            </div>
                        </div>
                        <div class="grid w-full grid-cols-3 gap-2 sm:flex sm:w-auto sm:items-center">
                            <a href="{{ $f['whatsapp'] }}" target="_blank" rel="noopener" class="btn min-h-12 border border-ok-line bg-surface px-3 text-ok hover:bg-ok-tint lg:min-h-8 lg:text-[13px]"><x-icon name="whatsapp" class="size-4" /> WhatsApp</a>
                            <a href="{{ $f['call'] }}" class="btn-ghost min-h-12 px-3 lg:min-h-8 lg:text-[13px]"><x-icon name="phone" class="size-4" /> Call</a>
                            @if ($f['url'])
                                <a href="{{ $f['url'] }}" class="btn min-h-12 bg-ink px-3 text-white hover:bg-zinc-800 lg:min-h-8 lg:text-[13px]">Log result</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($followUps['count'] > count($followUps['rows']) && $followUps['url'])
                <a href="{{ $followUps['url'] }}" class="block border-t border-line px-4 py-2.5 text-[13px] font-semibold text-ink hover:bg-hover lg:px-5">
                    {{ $followUps['count'] - count($followUps['rows']) }} more due · open the follow-up queue
                </a>
            @endif
        @endif
    </x-ui.panel>
    </div>
@endif
