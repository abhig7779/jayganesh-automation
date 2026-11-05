@php use App\Models\Lead; use App\Models\Quotation; use App\Support\Money; use App\Support\Phone; @endphp
<x-layouts.app title="Leads">
<div x-data="{ add: {{ $errors->any() ? 'true' : 'false' }}, source: @js(old('source', 'whatsapp_ad')) }">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-[28px] font-bold leading-tight tracking-[-0.02em] lg:text-[22px]">Leads</h1>
            <p class="text-sm text-zinc-600">Enquiries from ads, calls and the counter. They share the <a href="{{ route('followups.index') }}" class="font-semibold text-ink underline underline-offset-2">follow-up list</a> with quotations.</p>
        </div>
        <button type="button" @click="add = !add; $nextTick(() => add && $refs.mobile.focus())" class="inline-flex min-h-12 items-center gap-2 rounded-lg bg-brand-400 px-4 text-sm font-semibold text-ink hover:bg-[#F2C800] lg:min-h-10">
            <x-quotations.icon name="plus" class="size-5" /> Add lead
        </button>
    </div>

    {{-- Add form --}}
    <form x-show="add" x-cloak method="POST" action="{{ route('leads.store') }}" class="mb-5 rounded-xl border border-zinc-200 bg-white p-4 lg:p-5">
        @csrf
        <div class="grid gap-3 lg:grid-cols-[200px_200px_1fr_140px]">
            <div>
                <label class="mb-1 block text-sm font-semibold text-zinc-700" for="l-mobile">Mobile</label>
                <div class="flex min-h-[52px] items-center rounded-lg border {{ $errors->has('mobile') ? 'border-red-600' : 'border-zinc-300' }} focus-within:ring-2 focus-within:ring-ink lg:min-h-11">
                    <span class="pl-3 pr-1.5 font-semibold text-zinc-600">+91</span>
                    <input id="l-mobile" x-ref="mobile" name="mobile" value="{{ old('mobile') }}" inputmode="numeric" maxlength="14" required placeholder="98231 07676" class="h-full min-w-0 flex-1 bg-transparent pr-3 font-semibold tabular-nums placeholder:font-normal placeholder:text-zinc-500 focus:outline-none">
                </div>
                @error('mobile')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-zinc-700" for="l-name">Name <span class="font-normal text-zinc-600">(optional)</span></label>
                <input id="l-name" name="name" value="{{ old('name') }}" maxlength="120" class="block min-h-[52px] w-full rounded-lg border border-zinc-300 px-3 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink lg:min-h-11">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-zinc-700" for="l-interest">What they asked for</label>
                <input id="l-interest" name="interest" value="{{ old('interest') }}" maxlength="500" required placeholder="PPF for Creta, Android stereo for Swift…" class="block min-h-[52px] w-full rounded-lg border {{ $errors->has('interest') ? 'border-red-600' : 'border-zinc-300' }} px-3 placeholder:text-zinc-500 focus:border-ink focus:outline-none focus:ring-2 focus:ring-ink lg:min-h-11">
                @error('interest')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-zinc-700" for="l-price">Price told</label>
                <div class="flex min-h-[52px] items-center rounded-lg border border-zinc-300 focus-within:ring-2 focus-within:ring-ink lg:min-h-11">
                    <span class="pl-3 pr-1 font-semibold text-zinc-600">₹</span>
                    <input id="l-price" name="price" value="{{ old('price') }}" inputmode="decimal" class="h-full min-w-0 flex-1 bg-transparent pr-3 text-right tabular-nums focus:outline-none">
                </div>
                @error('price')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="mt-3">
            <div class="mb-1 text-sm font-semibold text-zinc-700">Source</div>
            <input type="hidden" name="source" :value="source">
            <div class="flex flex-wrap gap-2">
                @foreach (Lead::SOURCES as $k => $l)
                    <button type="button" @click="source = '{{ $k }}'" class="min-h-10 rounded-full border px-4 text-sm font-semibold" :class="source === '{{ $k }}' ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white'">{{ $l }}</button>
                @endforeach
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <button class="min-h-12 w-full rounded-lg bg-brand-400 px-5 text-[15px] font-semibold text-ink hover:bg-[#F2C800] lg:min-h-10 lg:w-auto">Save lead &amp; plan follow-up</button>
        </div>
    </form>

    {{-- Status chips --}}
    <div class="-mx-4 mb-3 flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:px-0">
        @php $chip = fn ($a) => $a ? 'border-ink bg-ink text-white' : 'border-zinc-300 bg-white text-ink hover:bg-zinc-50'; @endphp
        <a href="{{ route('leads.index') }}" class="inline-flex h-10 shrink-0 items-center rounded-full border px-4 text-sm font-semibold {{ $chip(! $status) }}">All <span class="ml-1.5 opacity-70">{{ $counts->sum() }}</span></a>
        @foreach (Lead::STATUS_LABELS as $k => $l)
            <a href="{{ route('leads.index', ['status' => $k]) }}" class="inline-flex h-10 shrink-0 items-center rounded-full border px-4 text-sm font-semibold {{ $chip($status === $k) }}">{{ $l }} <span class="ml-1.5 opacity-70">{{ $counts[$k] ?? 0 }}</span></a>
        @endforeach
    </div>

    @if ($leads->isEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white px-6 py-12 text-center">
            <x-quotations.icon name="inbox" class="mx-auto size-8 text-zinc-600" />
            <p class="mt-3 text-[15px] font-medium text-zinc-700">No leads {{ $status ? 'with this status' : 'yet' }}. Add enquiries from WhatsApp ads and calls so nobody is forgotten.</p>
            <button type="button" @click="add = true" class="mt-4 inline-flex min-h-11 items-center rounded-lg border border-zinc-300 bg-white px-4 text-sm font-semibold hover:bg-zinc-50">+ Add lead</button>
        </div>
    @else
        <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white">
            <div class="hidden grid-cols-[1.3fr_1.6fr_.8fr_.8fr_1fr_auto] gap-4 bg-zinc-100 px-4 py-3 text-xs font-semibold uppercase tracking-[0.06em] text-zinc-600 lg:grid">
                <span>Customer</span><span>Asked for</span><span>Source</span><span>Status</span><span>Next follow-up</span><span class="w-[300px] text-right">Actions</span>
            </div>
            @foreach ($leads as $lead)
                @php $next = $lead->followUps->first(); $late = $next && $next->isOverdue(); @endphp
                <div x-data="{ lost: false }" @class(['px-4 py-3 lg:grid lg:grid-cols-[1.3fr_1.6fr_.8fr_.8fr_1fr_auto] lg:items-center lg:gap-4', 'bg-amber-50' => $highlight === $lead->id])>
                    <div class="flex items-start justify-between gap-2 lg:block">
                        <div>
                            <div class="text-[15px] font-semibold">{{ $lead->displayName() }}</div>
                            <div class="text-[13px] tabular-nums text-zinc-600">@if ($lead->name || $lead->customer_id){{ Phone::pretty($lead->mobile) }} · @else No name yet · @endif{{ $lead->created_at->timezone('Asia/Kolkata')->format('j M') }}</div>
                        </div>
                        <x-quotations.status :status="$lead->status" class="lg:hidden" />
                    </div>
                    <div class="mt-1 text-sm lg:mt-0">
                        <span class="font-medium">{{ $lead->interest }}</span>
                        @if ($lead->price_told_paise)<span class="tabular-nums text-zinc-600"> · told {{ Money::format($lead->price_told_paise) }}</span>@endif
                        @if ($lead->type === Lead::TYPE_PRICE)<span class="ml-1 rounded border border-zinc-300 px-1 text-[10px] font-semibold uppercase text-zinc-600">Asked price</span>@endif
                    </div>
                    <div class="hidden text-sm text-zinc-700 lg:block">{{ Lead::SOURCES[$lead->source] ?? $lead->source }}</div>
                    <div class="hidden lg:block"><x-quotations.status :status="$lead->status" /></div>
                    <div class="mt-1 text-xs lg:mt-0 lg:text-sm">
                        @if ($next)
                            <span @class(['whitespace-nowrap font-semibold', 'text-red-600' => $late])>{{ $late ? 'Overdue · ' : '' }}{{ $next->due_on->format('D j M') }}</span>
                            <span class="text-zinc-600">· {{ $lead->assignee?->name }}</span>
                        @elseif ($lead->quotation_id)
                            <a class="font-semibold underline" href="{{ route('quotations.show', $lead->quotation_id) }}">See quotation</a>
                        @else
                            <span class="text-zinc-600">{{ $lead->status === 'lost' ? (Quotation::LOST_REASONS[$lead->lost_reason] ?? 'Closed') : '—' }}</span>
                        @endif
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 lg:mt-0 lg:w-[300px] lg:justify-end">
                        @if ($next)
                            <a href="{{ route('followups.whatsapp', $next) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold hover:bg-zinc-50 lg:min-h-9 lg:flex-none"><x-quotations.icon name="chat" class="size-4" />WhatsApp</a>
                            <a href="{{ route('followups.index', ['select' => $next->id]) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-zinc-300 bg-white px-3 text-sm font-semibold hover:bg-zinc-50 lg:min-h-9 lg:flex-none">Log result</a>
                        @endif
                        @if ($canQuote && $lead->isActive())
                            <a href="{{ route('quotations.create', ['lead' => $lead->id]) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg bg-ink px-3 text-sm font-semibold text-white lg:min-h-9 lg:flex-none">Make quotation</a>
                        @endif
                        @if ($lead->isActive())
                            <button type="button" @click="lost = !lost" class="min-h-11 px-2 text-sm font-semibold text-red-600 lg:min-h-9">Lost</button>
                        @endif
                    </div>
                    @if ($lead->isActive())
                        <form x-show="lost" x-cloak method="POST" action="{{ route('leads.lost', $lead) }}" class="col-span-full mt-3 flex flex-wrap gap-2">
                            @csrf
                            <select name="reason" class="min-h-11 rounded-lg border border-zinc-300 bg-white px-3 text-sm">
                                @foreach (Quotation::LOST_REASONS as $k => $l)@if ($k !== 'no_reply')<option value="{{ $k }}">{{ $l }}</option>@endif @endforeach
                            </select>
                            <input name="note" maxlength="300" placeholder="Note (optional)" class="min-h-11 flex-1 rounded-lg border border-zinc-300 px-3 text-sm">
                            <button class="min-h-11 rounded-lg border border-red-600 bg-white px-4 text-sm font-semibold text-red-600">Close as lost</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $leads->links() }}</div>
    @endif
</div>
</x-layouts.app>
