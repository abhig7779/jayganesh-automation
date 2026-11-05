{{-- "Follow-ups today" (UX-FLOWS §3.2). $followUps = null while the quotations module isn't merged. --}}
@if ($followUps === null)
    <x-ui.panel title="Follow-ups today" subtitle="Quotes and enquiries to chase">
        <x-slot:aside><x-ui.status-badge status="soon" /></x-slot:aside>
        @include('home.partials.soon', ['icon' => 'chat', 'text' => 'Switches on with Quotations. Every quote and price enquiry gets a follow-up date, and the ones due today show here, overdue first.'])
    </x-ui.panel>
@else
    <div x-data="{ filter: 'all' }">
    <x-ui.panel title="Follow-ups today" flush>
        <x-slot:aside>
            @if ($followUps['count'])
                <span class="badge badge-bad">{{ $followUps['count'] }} due @if ($followUps['overdue']) · {{ $followUps['overdue'] }} overdue @endif</span>
            @endif
            @if ($followUps['overdue'])
                <div class="hidden rounded-lg bg-sunken p-0.5 sm:flex">
                    <button type="button" @click="filter = 'all'" :class="filter === 'all' ? 'bg-ink text-white' : 'text-muted hover:text-ink'" class="rounded-md px-2.5 py-1 text-xs font-semibold">All</button>
                    <button type="button" @click="filter = 'overdue'" :class="filter === 'overdue' ? 'bg-ink text-white' : 'text-muted hover:text-ink'" class="rounded-md px-2.5 py-1 text-xs font-semibold">Overdue ({{ $followUps['overdue'] }})</button>
                </div>
            @endif
        </x-slot:aside>
        @if (empty($followUps['rows']))
            <x-ui.empty-state icon="check" text="No follow-ups due today. Nice work." compact />
        @else
            <ul class="divide-y divide-line">
                @foreach ($followUps['rows'] as $f)
                    <li @class(['relative flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 lg:px-5', 'before:absolute before:inset-y-0 before:left-0 before:w-[3px] before:bg-bad' => $f['overdue']])
                        x-show="filter === 'all' || {{ $f['overdue'] ? 'true' : 'false' }}">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[15px] font-semibold lg:text-sm">{{ $f['name'] }}</div>
                            <div class="truncate text-[13px] font-medium text-muted">{{ $f['detail'] }}</div>
                            <div @class(['mt-0.5 flex items-center gap-1 text-xs font-semibold', 'text-bad' => $f['overdue'], 'text-muted' => ! $f['overdue']])>
                                <x-icon :name="$f['overdue'] ? 'alert' : 'clock'" class="size-3.5" /> {{ $f['when'] }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($f['whatsapp'])
                                <a href="{{ $f['whatsapp'] }}" target="_blank" rel="noopener" class="btn min-h-10 border border-ok-line bg-surface px-3 text-ok hover:bg-ok-tint lg:min-h-8 lg:text-[13px]"><x-icon name="whatsapp" class="size-4" /> WhatsApp</a>
                            @endif
                            @if ($f['call'])
                                <a href="{{ $f['call'] }}" class="btn-ghost min-h-10 px-3 lg:min-h-8 lg:text-[13px]"><x-icon name="phone" class="size-4" /> Call</a>
                            @endif
                            @if ($f['url'])
                                <a href="{{ $f['url'] }}" class="btn-primary min-h-10 px-3 lg:min-h-8 lg:text-[13px]">Log result</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.panel>
    </div>
@endif
