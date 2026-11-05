@php use App\Support\Money; @endphp
{{-- Owner/Admin: where quotes leak this month (UX-FLOWS §3.2 step 8) --}}
<section class="mb-5 rounded-xl border border-zinc-200 bg-white">
    <button type="button" @click="report = !report" class="flex w-full items-center justify-between px-4 py-3 text-left lg:px-5">
        <span class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Quote follow-up report · {{ $r['month_label'] }}</span>
        <x-quotations.icon name="chevron-down" class="size-4 text-zinc-600 transition" ::class="report && 'rotate-180'" />
    </button>
    <div x-show="report" class="border-t border-zinc-200">
        <div class="grid grid-cols-2 divide-zinc-200 lg:grid-cols-5 lg:divide-x">
            @php
                $tiles = [
                    ['Quotes made', $r['quotes'], $r['quotes_sent'].' sent on WhatsApp · '.Money::format($r['quoted_paise']), null],
                    ['Follow-ups on time', $r['followups_on_time_pct'].'%', $r['followups_on_time'].' of '.$r['followups_due'].' due so far', $r['followups_on_time_pct'] < 70 && $r['followups_due'] ? 'bad' : null],
                    ['Overdue now', $r['overdue_now'], $r['overdue_now'] ? 'customers waiting for a call' : 'nobody waiting', $r['overdue_now'] ? 'bad' : 'ok'],
                    ['Converted', $r['converted_pct'].'%', $r['won'].' won · '.$r['open'].' still open', null],
                    ['Won vs lost', Money::format($r['won_paise']), Money::format($r['lost_paise']).' lost or expired', null],
                ];
            @endphp
            @foreach ($tiles as [$label, $value, $sub, $tone])
                <div class="border-b border-zinc-200 px-4 py-4 lg:border-b-0 lg:px-5">
                    <div class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">{{ $label }}</div>
                    <div @class(['mt-1 text-2xl font-bold tabular-nums tracking-[-0.01em]', 'text-red-600' => $tone === 'bad', 'text-green-700' => $tone === 'ok'])>{{ $value }}</div>
                    <div class="mt-0.5 text-xs font-medium text-zinc-600">{{ $sub }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-0 border-t border-zinc-200 lg:grid-cols-[1fr_2fr] lg:divide-x lg:divide-zinc-200">
            <div class="min-w-0 px-4 py-4 lg:px-5">
                <div class="mb-2 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Why we lost</div>
                @forelse ($r['lost_reasons'] as $lr)
                    <div class="flex items-center justify-between border-b border-zinc-200 py-2 text-sm last:border-0">
                        <span class="font-medium">{{ $lr['label'] }}</span>
                        <span class="tabular-nums text-zinc-700"><strong class="text-ink">{{ $lr['count'] }}</strong> · {{ Money::format($lr['paise']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-zinc-600">No quotes lost this month.</p>
                @endforelse
            </div>
            <div class="min-w-0 overflow-x-auto px-4 py-4 lg:px-5">
                <div class="mb-2 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">By staff</div>
                @if ($r['staff'])
                    <table class="w-full min-w-[440px] text-[13px]">
                        <thead class="text-left text-xs text-zinc-600">
                            <tr><th class="py-1.5 font-semibold">Name</th><th class="py-1.5 text-right font-semibold">Quotes</th><th class="py-1.5 text-right font-semibold">Follow-ups done</th><th class="py-1.5 text-right font-semibold">On time</th><th class="py-1.5 text-right font-semibold">Overdue</th><th class="py-1.5 text-right font-semibold">Converted</th><th class="py-1.5 text-right font-semibold">Won ₹</th></tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach ($r['staff'] as $s)
                                <tr>
                                    <td class="py-2 font-semibold">{{ $s['name'] }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $s['quotes'] }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $s['followups_done'] }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $s['followups_on_time_pct'] }}%</td>
                                    <td @class(['py-2 text-right tabular-nums', 'font-semibold text-red-600' => $s['overdue']])>{{ $s['overdue'] }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $s['conversion_pct'] }}%</td>
                                    <td class="py-2 text-right tabular-nums">{{ Money::format($s['won_paise']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-zinc-600">No quotes made this month yet.</p>
                @endif
            </div>
        </div>
    </div>
</section>
