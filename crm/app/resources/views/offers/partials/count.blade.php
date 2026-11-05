@php
    use App\Services\Offers\SegmentBuilder;
    use App\Support\Money;
    $n = $count['eligible'];
@endphp
<div data-count="{{ $n }}">
    <div class="text-xs font-semibold uppercase tracking-wider text-zinc-600">Who gets it</div>
    @php [$head, $tail] = explode(' · ', SegmentBuilder::countLine($count), 2); @endphp
    <p class="mt-1 tabular-nums" data-count-line><span class="block text-3xl font-bold">{{ $head }}</span> <span class="mt-1 block text-sm font-medium text-zinc-600"><span class="sr-only">· </span>{{ $tail }}</span></p>

    @if (!empty($summary))
        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach ($summary as $chip)
                <span class="badge border border-zinc-300 bg-white text-zinc-800">{{ $chip }}</span>
            @endforeach
        </div>
    @endif

    @if ($n === 0)
        <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">No one matches. Remove a filter to widen the list.</p>
    @elseif (isset($sample) && $sample->isNotEmpty())
        <ul class="mt-4 divide-y divide-zinc-100 border-t border-zinc-100 text-sm">
            @foreach ($sample as $c)
                <li class="flex items-center justify-between gap-2 py-2">
                    <span class="truncate font-medium">{{ $c->name }}</span>
                    <span class="shrink-0 text-xs text-zinc-600">{{ $c->cars->first()?->model ?? 'No car' }} · {{ $c->last_visit_at ? $c->last_visit_at->timezone('Asia/Kolkata')->format('M Y') : 'no visit' }}</span>
                </li>
            @endforeach
        </ul>
        @if ($n > $sample->count())
            <p class="mt-1 text-xs text-zinc-600">and {{ number_format($n - $sample->count()) }} more</p>
        @endif
    @endif
</div>
@if (request()->header('HX-Request'))
    <span id="cost-estimate" hx-swap-oob="true" data-count="{{ $n }}">{{ number_format($n) }} × {{ Money::format($ratePaise, true) }} = <strong class="text-ink">{{ Money::format($n * $ratePaise, true) }}</strong></span>
    <span id="send-count" hx-swap-oob="true">{{ number_format($n) }}</span>
@endif
