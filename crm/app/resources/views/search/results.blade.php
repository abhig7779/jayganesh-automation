{{--
    Grouped search results. Used by the top-bar dropdown (htmx, $compact = true) and the full
    search page. Every result is an <a data-search-result> so ↑ ↓ Enter work (resources/js/app.js).
--}}
@php
    $digits = preg_replace('/\D+/', '', $q);
    $canAdd = auth()->user()->can('customers.manage');
@endphp
@if ($q === '')
    @if (empty($compact))
        <x-ui.empty-state icon="search" text="Type a mobile number, name, car number, bill, quote or job card number, or a product." />
    @endif
@elseif (empty($groups))
    <div @class(['px-4 py-5 text-center' => ! empty($compact)])>
        @if (! empty($compact))
            <p class="text-sm font-medium text-ink-2">Nothing found for “{{ $q }}”.</p>
            @if ($canAdd && strlen($digits) >= 10)
                <a href="{{ route('customers.create', ['mobile' => $digits]) }}" data-search-result class="btn-ghost mt-3 min-h-9 text-[13px]"><x-icon name="user-plus" class="size-4" /> Add {{ substr($digits, -10, 5) }} {{ substr($digits, -5) }} as a new customer</a>
            @endif
        @else
            <x-ui.empty-state icon="search" text="Nothing found for “{{ $q }}”. Check the spelling or the number.">
                @if ($canAdd)
                    <a href="{{ route('customers.create', strlen($digits) >= 10 ? ['mobile' => $digits] : []) }}" class="btn-ghost"><x-icon name="user-plus" class="size-5" /> Add as a new customer</a>
                @endif
            </x-ui.empty-state>
        @endif
    </div>
@else
    <div @class(['py-1.5' => ! empty($compact)])>
        @foreach ($groups as $key => $group)
            <div @class(['border-t border-line pt-1.5' => ! $loop->first && ! empty($compact), 'mb-5' => empty($compact)])>
                <div @class(['section-label', 'px-4 pt-1.5 pb-1 text-[11px]' => ! empty($compact), 'mb-2' => empty($compact)])>{{ $group['label'] }}</div>
                <div @class(['overflow-hidden rounded-xl border border-line bg-surface divide-y divide-line' => empty($compact)])>
                    @foreach ($group['items'] as $item)
                        @php $tag = $item['url'] ? 'a' : 'div'; @endphp
                        <{{ $tag }} @if ($item['url']) href="{{ $item['url'] }}" data-search-result role="option" aria-selected="false" @endif
                            @class(['flex items-center gap-3 px-4', 'min-h-12 py-2 hover:bg-sunken' => ! empty($compact), 'min-h-16 py-3 hover:bg-hover' => empty($compact)])>
                            @if (! empty($item['avatar']))
                                <x-ui.avatar-initials :name="$item['avatar']" :size="empty($compact) ? 'md' : 'sm'" />
                            @else
                                <span @class(['inline-flex shrink-0 items-center justify-center rounded-md bg-sunken text-ink-2', 'size-8' => ! empty($compact), 'size-10 rounded-lg' => empty($compact)])><x-icon :name="$item['icon'] ?? 'search'" class="size-4" /></span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-ink">{{ $item['title'] }}</span>
                                <span class="block truncate text-xs font-medium text-muted">{{ $item['subtitle'] }}</span>
                            </span>
                            @if ($item['meta']) <span class="tnum shrink-0 text-[13px] font-semibold text-ink-2">{{ $item['meta'] }}</span> @endif
                        </{{ $tag }}>
                    @endforeach
                </div>
            </div>
        @endforeach
        @if (! empty($compact))
            <a href="{{ route('search', ['q' => $q, 'stay' => 1]) }}" class="flex min-h-10 items-center justify-between border-t border-line px-4 text-xs font-semibold text-muted hover:text-ink">
                See all results <span class="flex items-center gap-1"><span class="kbd">↑</span><span class="kbd">↓</span> move <span class="kbd">↵</span> open</span>
            </a>
        @endif
    </div>
@endif
