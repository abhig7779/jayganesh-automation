@php use App\Support\Money; use App\Support\Phone; use App\Models\FollowUp; use App\Models\Quotation; use App\Services\Quotations\QuoteTotals; @endphp
<x-layouts.app :title="$q->number">
<div x-data="{ log: false, lost: false, outcome: null, date: '', get dated() { return this.outcome === 'coming_on' || this.outcome === 'call_later' } }">

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('quotations.index') }}" class="mb-1 inline-flex items-center gap-1 text-sm font-semibold text-zinc-600 hover:text-ink"><x-quotations.icon name="chevron-left" class="size-4" />Quotations</a>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-[28px] font-bold leading-tight tracking-[-0.02em] tabular-nums lg:text-[22px]">{{ $q->number }}</h1>
                <x-quotations.status :status="$q->status" />
                @if ($q->version > 1)<span class="text-sm text-zinc-600">Revision {{ $q->version }} of {{ $q->revisedFrom?->number }}</span>@endif
            </div>
            <p class="text-sm text-zinc-600">Made {{ $q->created_at->timezone('Asia/Kolkata')->format('j M Y, g:i a') }} by {{ $q->creator?->name ?? '—' }} · valid until <strong class="text-ink">{{ $q->valid_until->format('j M') }}</strong> · follow-ups by <strong class="text-ink">{{ $q->assignee?->name ?? '—' }}</strong></p>
        </div>
    </div>

    @if (request('sent') || session('wa_url'))
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
            <span>Quotation saved. WhatsApp opened in a new tab with the message and PDF link. Day 1, 3, 6 and 10 follow-ups are planned.</span>
            <a href="{{ session('wa_url') ?: route('quotations.whatsapp', $q) }}" target="_blank" rel="noopener" class="font-semibold underline">Open WhatsApp again</a>
        </div>
    @endif
    @if ($q->revisedTo)
        <div class="mb-4 rounded-lg border border-zinc-300 bg-zinc-100 px-4 py-3 text-sm font-medium">This quote was replaced by <a class="font-semibold underline" href="{{ route('quotations.show', $q->revised_to_id) }}">{{ $q->revisedTo->number }}</a>.</div>
    @endif

    <div class="grid grid-cols-[minmax(0,1fr)] gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-5">
            {{-- Customer --}}
            <section class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-4 lg:p-5">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-zinc-100 text-[13px] font-bold">{{ collect(explode(' ', $q->customer->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                    <div>
                        <div class="text-[17px] font-semibold">{{ $q->customer->name }}</div>
                        <div class="text-[13px] font-medium text-zinc-600"><span class="tabular-nums">{{ Phone::pretty($q->customer->mobile) }}</span>@if ($q->car) · {{ trim($q->car->make.' '.$q->car->model) }} {{ \App\Support\RegNo::pretty($q->car->reg_no) }}@endif</div>
                    </div>
                </div>
                @if (Route::has('customers.show'))<a href="{{ route('customers.show', $q->customer) }}" class="text-sm font-semibold underline-offset-2 hover:underline">Customer profile</a>@endif
            </section>

            {{-- Items --}}
            <section class="overflow-hidden rounded-xl border border-zinc-200 bg-white">
                <table class="w-full text-[13px]">
                    <thead class="bg-zinc-100 text-left text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">
                        <tr><th class="px-4 py-3">Item</th><th class="px-2 py-3 text-center">Qty</th><th class="hidden px-4 py-3 text-right sm:table-cell">Price</th><th class="px-4 py-3 text-right">Amount</th></tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200">
                        @foreach ($q->items as $item)
                            @php $save = QuoteTotals::lineSavings($item->qty, $item->unit_price_paise, $item->mrp_paise); @endphp
                            <tr>
                                <td class="px-4 py-3"><div class="text-sm font-semibold">{{ $item->name }}</div><div class="text-xs text-zinc-600">GST {{ rtrim(rtrim((string) $item->gst_rate, '0'), '.') }}%<span class="sm:hidden"> · {{ Money::format($item->unit_price_paise) }} each</span></div></td>
                                <td class="px-2 py-3 text-center tabular-nums">{{ $item->qty }}</td>
                                <td class="hidden px-4 py-3 text-right tabular-nums sm:table-cell">
                                    @if ($save)<div class="text-xs text-zinc-500 line-through">MRP {{ Money::format($item->mrp_paise) }}</div>@endif
                                    {{ Money::format($item->unit_price_paise) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums"><div class="text-sm font-semibold">{{ Money::format($item->line_total_paise) }}</div>@if ($save)<div class="text-xs font-semibold text-green-700">You save {{ Money::format($save) }}</div>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="border-t border-zinc-200 px-4 py-4">
                    <div class="ml-auto max-w-xs space-y-1 text-sm tabular-nums">
                        @if ($q->savings_paise)<div class="flex justify-between text-zinc-600"><span>MRP total</span><span class="line-through">{{ Money::format($q->total_paise + $q->savings_paise) }}</span></div>@endif
                        <div class="flex justify-between text-zinc-600"><span>Taxable value</span><span>{{ Money::format($q->taxable_paise, true) }}</span></div>
                        <div class="flex justify-between text-zinc-600"><span>GST</span><span>{{ Money::format($q->gst_paise, true) }}</span></div>
                        <div class="flex items-baseline justify-between border-t-2 border-ink pt-2"><span class="font-semibold">Total incl. GST</span><span class="text-2xl font-bold tracking-[-0.01em]">{{ Money::format($q->total_paise) }}</span></div>
                    </div>
                    @if ($q->savings_paise)
                        <div class="mt-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm font-semibold text-green-700">MRP {{ Money::format($q->total_paise + $q->savings_paise) }} → our price {{ Money::format($q->total_paise) }}, you save {{ Money::format($q->savings_paise) }}</div>
                    @endif
                    @if ($q->notes)<p class="mt-3 text-sm text-zinc-700"><span class="font-semibold">Note:</span> {{ $q->notes }}</p>@endif
                </div>
            </section>

            {{-- Timeline --}}
            <section class="rounded-xl border border-zinc-200 bg-white p-4 lg:p-5">
                <h2 class="mb-3 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Timeline</h2>
                <ol class="relative space-y-4 border-l border-zinc-200 pl-5">
                    @foreach ($timeline as $e)
                        <li class="relative">
                            <span @class(['absolute -left-[27px] top-0.5 grid size-4 place-items-center rounded-full border-2 bg-white',
                                'border-green-700' => $e['tone'] === 'ok', 'border-red-600' => $e['tone'] === 'bad', 'border-ink' => $e['tone'] === 'ink', 'border-zinc-400' => $e['tone'] === 'muted'])></span>
                            <div class="text-sm font-semibold">{{ $e['title'] }}</div>
                            <div class="text-xs text-zinc-600">{{ $e['at']->timezone('Asia/Kolkata')->format('D j M, g:i a') }}@if ($e['text']) · {{ $e['text'] }}@endif</div>
                        </li>
                    @endforeach
                    @foreach ($q->followUps->where('status', FollowUp::PENDING) as $f)
                        <li class="relative">
                            <span class="absolute -left-[27px] top-0.5 size-4 rounded-full border-2 border-dashed {{ $f->isOverdue() ? 'border-red-600' : 'border-zinc-400' }} bg-white"></span>
                            <div class="text-sm font-semibold {{ $f->isOverdue() ? 'text-red-600' : 'text-zinc-700' }}">{{ $f->stepLabel() }} follow-up {{ $f->isOverdue() ? '· overdue' : '· planned' }}</div>
                            <div class="text-xs text-zinc-600">{{ $f->due_on->format('D j M') }} · {{ $f->assignee?->name ?? $q->assignee?->name }}</div>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        {{-- ===== Actions ===== --}}
        <aside class="order-first space-y-4 lg:order-none lg:sticky lg:top-6 lg:self-start">
            <section class="rounded-xl border border-zinc-200 bg-white p-4">
                @if ($next)
                    <div class="mb-3 text-sm">
                        <span class="text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Next follow-up</span>
                        <div @class(['mt-0.5 text-[17px] font-semibold', 'text-red-600' => $next->isOverdue()])>{{ $next->stepLabel() }} · {{ $next->due_on->format('D j M') }}{{ $next->isOverdue() ? ' · overdue' : '' }}</div>
                    </div>
                @endif
                @can('quotations.manage')
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="flex min-h-12 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white text-sm font-semibold hover:bg-zinc-50 lg:min-h-10"><x-quotations.icon name="chat" />WhatsApp</a>
                        <a href="{{ $next ? route('followups.call', $next) : $tel }}" class="flex min-h-12 items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white text-sm font-semibold hover:bg-zinc-50 lg:min-h-10"><x-quotations.icon name="phone" />Call</a>
                    </div>
                    @if ($next)
                        <button type="button" @click="log = !log" class="mt-2 flex min-h-12 w-full items-center justify-center rounded-lg bg-ink text-sm font-semibold text-white lg:min-h-10">Log result</button>
                        <form x-show="log" x-cloak method="POST" action="{{ route('followups.log', $next) }}" class="mt-3 space-y-3 border-t border-zinc-200 pt-3">
                            @csrf
                            <input type="hidden" name="outcome" :value="outcome">
                            <div class="grid grid-cols-2 gap-2">
                                @foreach (['coming_on', 'call_later', 'no_answer', 'wants_better_price', 'bought_elsewhere', 'not_interested', 'wrong_number'] as $o)
                                    <button type="button" @click="outcome = '{{ $o }}'; if (dated && !date) date = '{{ now('Asia/Kolkata')->addDay()->toDateString() }}'"
                                            class="min-h-11 rounded-lg border px-2 text-left text-[13px] font-semibold"
                                            :class="outcome === '{{ $o }}' ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white {{ in_array($o, FollowUp::LOST_OUTCOMES, true) ? 'text-red-700' : '' }}'">{{ FollowUp::OUTCOMES[$o] }}</button>
                                @endforeach
                            </div>
                            <input x-show="dated" type="date" name="date" x-model="date" min="{{ now('Asia/Kolkata')->toDateString() }}" class="min-h-11 w-full rounded-lg border border-zinc-300 px-3 text-sm">
                            <input name="note" maxlength="500" placeholder="Note (optional)" class="min-h-11 w-full rounded-lg border border-zinc-300 px-3 text-sm placeholder:text-zinc-500">
                            @error('outcome')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            @error('date')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                            <button :disabled="!outcome" class="flex min-h-12 w-full items-center justify-center rounded-lg bg-brand-400 text-sm font-semibold text-ink disabled:opacity-50">Save result</button>
                        </form>
                    @endif
                @endcan
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-4">
                @can('quotations.manage')
                    <form method="POST" action="{{ route('quotations.convert', $q) }}">
                        @csrf
                        <button class="flex min-h-12 w-full items-center justify-center gap-2 rounded-lg {{ $q->status === 'won' ? 'border border-zinc-300 bg-white' : 'bg-brand-400 hover:bg-[#F2C800]' }} text-[15px] font-semibold text-ink lg:min-h-11">
                            <x-quotations.icon name="receipt" /> Convert to bill
                        </button>
                    </form>
                    @unless ($billingReady)<p class="mt-1.5 text-xs text-zinc-600">Billing coming soon. The items will carry over to the bill.</p>@endunless
                @endcan
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="{{ route('quotations.pdf', $q) }}" target="_blank" class="flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-zinc-300 bg-white text-sm font-semibold hover:bg-zinc-50"><x-quotations.icon name="download" class="size-4" />PDF</a>
                    @can('quotations.manage')
                        <a href="{{ route('quotations.create', ['from' => $q->id]) }}" class="flex min-h-11 items-center justify-center gap-1.5 rounded-lg border border-zinc-300 bg-white text-sm font-semibold hover:bg-zinc-50"><x-quotations.icon name="pencil" class="size-4" />Revise</a>
                    @endcan
                </div>
                @can('quotations.manage')
                    @if ($q->isActive() || $q->status === 'expired')
                        <button type="button" @click="lost = !lost" class="mt-3 w-full text-center text-sm font-semibold text-red-600 hover:underline">Mark as lost</button>
                        <form x-show="lost" x-cloak method="POST" action="{{ route('quotations.lost', $q) }}" class="mt-2 space-y-2">
                            @csrf
                            <select name="reason" class="min-h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm">
                                @foreach (Quotation::LOST_REASONS as $k => $l)@if ($k !== 'no_reply')<option value="{{ $k }}">{{ $l }}</option>@endif @endforeach
                            </select>
                            <input name="note" maxlength="300" placeholder="Where / at what price? (optional)" class="min-h-11 w-full rounded-lg border border-zinc-300 px-3 text-sm placeholder:text-zinc-500">
                            <button class="min-h-11 w-full rounded-lg border border-red-600 bg-white text-sm font-semibold text-red-600">Close as lost</button>
                        </form>
                    @endif
                @endcan
            </section>

            @if ($staff->isNotEmpty())
                <section class="rounded-xl border border-zinc-200 bg-white p-4">
                    <form method="POST" action="{{ route('quotations.reassign', $q) }}" class="flex items-end gap-2">
                        @csrf
                        <label class="flex-1">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Follow-ups by</span>
                            <select name="user_id" class="min-h-11 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm">
                                @foreach ($staff as $u)<option value="{{ $u->id }}" @selected($u->id === $q->assigned_to)>{{ $u->name }}</option>@endforeach
                            </select>
                        </label>
                        <button class="min-h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold hover:bg-zinc-50">Reassign</button>
                    </form>
                </section>
            @endif

            <section class="rounded-xl border border-zinc-200 bg-white p-4">
                <div class="mb-2 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600">Message {{ $next ? 'for '.$next->stepLabel() : '' }}</div>
                <div class="max-h-48 overflow-y-auto whitespace-pre-line rounded-lg bg-[#FAFAF9] p-3 text-[13px] leading-5 text-zinc-800">{{ $message }}</div>
            </section>
        </aside>
    </div>
</div>
</x-layouts.app>
