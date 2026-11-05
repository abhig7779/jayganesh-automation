@props(['period', 'action', 'keep' => []])
{{-- Date range presets (segmented) + custom range. $keep = extra query params to carry along. --}}
@php
    $presets = \App\Services\Finance\Period::PRESETS;
@endphp
<div x-data="{ custom: {{ $period->preset === 'custom' ? 'true' : 'false' }} }" {{ $attributes->merge(['class' => 'print:hidden']) }}>
    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <div class="inline-flex min-w-max gap-1 rounded-lg bg-zinc-200/70 p-1" role="group" aria-label="Date range">
            @foreach ($presets as $key => $label)
                @if ($key === 'custom')
                    <button type="button" @click="custom = !custom" @class([
                        'min-h-10 rounded-md px-3 text-sm',
                        'border border-zinc-200 bg-white font-semibold text-ink' => $period->preset === 'custom',
                        'font-medium text-zinc-600 hover:text-ink' => $period->preset !== 'custom',
                    ])>{{ $label }}</button>
                @else
                    <a href="{{ $action.'?'.http_build_query(array_merge($keep, ['preset' => $key])) }}" @class([
                        'inline-flex min-h-10 items-center rounded-md px-3 text-sm',
                        'border border-zinc-200 bg-white font-semibold text-ink' => $period->preset === $key,
                        'font-medium text-zinc-600 hover:text-ink' => $period->preset !== $key,
                    ])>{{ $label }}</a>
                @endif
            @endforeach
        </div>
    </div>
    <form x-cloak x-show="custom" method="GET" action="{{ $action }}" class="mt-3 flex flex-wrap items-end gap-2">
        @foreach ($keep as $k => $v)
            @if ($v !== null && $v !== '')
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endif
        @endforeach
        <input type="hidden" name="preset" value="custom">
        <div>
            <label class="label" for="period-from">From</label>
            <input class="input w-40" type="date" id="period-from" name="from" value="{{ $period->fromDate() }}" max="{{ \App\Services\Finance\Period::today()->toDateString() }}">
        </div>
        <div>
            <label class="label" for="period-to">To</label>
            <input class="input w-40" type="date" id="period-to" name="to" value="{{ $period->toDate() }}" max="{{ \App\Services\Finance\Period::today()->toDateString() }}">
        </div>
        <button class="btn-ghost">Show</button>
    </form>
</div>
