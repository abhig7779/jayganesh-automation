@props(['section'])
{{-- Plain report table. Money right-aligned and tabular; a column with 'bar' gets a small bar against the biggest value. --}}
@php
    $cols = $section['columns'];
    $barKey = collect($cols)->filter(fn ($c) => ! empty($c['bar']))->keys()->first();
    $max = $barKey ? max(1, (int) collect($section['rows'])->map(fn ($r) => abs((int) ($r[$barKey] ?? 0)))->max()) : 1;
    $fmt = function ($v, $type) {
        if ($v === null || $v === '') {
            return ['—', 'text-zinc-500'];
        }
        return match ($type) {
            'money' => [(int) $v < 0 ? '−'.\App\Support\Money::format(-(int) $v) : \App\Support\Money::format((int) $v), (int) $v < 0 ? 'text-red-600' : ''],
            'pct' => [rtrim(rtrim(number_format((float) $v, 1), '0'), '.').'%', (float) $v < 0 ? 'text-red-600' : ''],
            'int', 'qty' => [is_numeric($v) ? (floor((float) $v) == (float) $v ? number_format((float) $v) : number_format((float) $v, 2)) : $v, ''],
            'date' => [preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? \Carbon\Carbon::parse($v)->format('D, j M') : $v, ''],
            default => [$v, ''],
        };
    };
    $right = fn ($type) => in_array($type, ['money', 'int', 'qty', 'pct'], true);
@endphp
<section {{ $attributes->merge(['class' => 'card overflow-hidden p-0 sm:p-0 break-inside-avoid']) }}>
    <h2 class="border-b border-zinc-200 px-4 py-3 text-[15px] font-semibold text-ink sm:px-5">{{ $section['title'] }}</h2>
    @if (empty($section['rows']))
        <x-finance.empty icon="chart" :title="$section['empty'] ?? 'Nothing in this period.'" class="py-8" />
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-max text-left text-[13px] font-medium">
                <thead class="bg-zinc-100 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">
                    <tr>
                        @foreach ($cols as $key => $c)
                            <th scope="col" @class(['whitespace-nowrap px-4 py-2.5 first:pl-4 sm:first:pl-5', 'text-right' => $right($c['type']), 'min-w-48' => $key === $barKey])>{{ $c['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @foreach ($section['rows'] as $row)
                        <tr class="hover:bg-zinc-50">
                            @foreach ($cols as $key => $c)
                                @php [$text, $cls] = $fmt($row[$key] ?? null, $c['type']); @endphp
                                <td @class(['whitespace-nowrap px-4 py-2.5 sm:first:pl-5', 'text-right tabular-nums' => $right($c['type']), $cls, 'text-ink' => ! $cls])>
                                    @if ($key === $barKey)
                                        <div class="flex items-center justify-end gap-3">
                                            <span class="hidden h-2 flex-1 overflow-hidden rounded-sm bg-zinc-100 sm:block print:block" aria-hidden="true">
                                                <span @class(['block h-full rounded-sm', 'bg-ink' => (int) ($row[$key] ?? 0) >= 0, 'bg-red-600' => (int) ($row[$key] ?? 0) < 0]) style="width: {{ round(abs((int) ($row[$key] ?? 0)) * 100 / $max, 1) }}%"></span>
                                            </span>
                                            <span class="w-28 shrink-0 text-right">{{ $text }}</span>
                                        </div>
                                    @else
                                        {{ $text }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                @if (! empty($section['totals']))
                    <tfoot class="border-t-2 border-zinc-300 bg-zinc-50 font-bold">
                        <tr>
                            @foreach ($cols as $key => $c)
                                @php [$text, $cls] = array_key_exists($key, $section['totals']) ? $fmt($section['totals'][$key], $c['type']) : ['', '']; @endphp
                                <td @class(['whitespace-nowrap px-4 py-2.5 sm:first:pl-5', 'text-right tabular-nums' => $right($c['type']), $cls])>
                                    @if ($key === $barKey)<span class="inline-block w-28 text-right">{{ $text }}</span>@else{{ $text }}@endif
                                </td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
</section>
