{{--
    Action tiles. Phone: New bill as the big yellow tile, the rest in 2 columns.
    PC: one row of equal cards with F-key keycaps; New bill is the ink-filled first card.
--}}
@php
    $tiles = collect($tiles);
    $primary = $tiles->firstWhere('primary', true);
    $rest = $tiles->reject(fn ($t) => $t['primary']);
@endphp
@if ($tiles->isNotEmpty())
<div class="mb-6">
    {{-- phone --}}
    <div class="space-y-3 lg:hidden">
        @if ($primary)
            <x-ui.action-tile primary :href="$primary['url']" :icon="$primary['icon']" :label="$primary['label']" :hint="$primary['hint']" />
        @endif
        <div class="grid grid-cols-2 gap-3">
            @foreach ($rest->values() as $i => $t)
                <x-ui.action-tile :href="$t['url']" :icon="$t['icon']" :label="$t['label']" :hint="$t['hint']" :hint-tone="$t['hintTone']"
                                  @class(['col-span-2' => $loop->last && $rest->count() % 2 === 1]) />
            @endforeach
        </div>
    </div>
    {{-- PC --}}
    <div class="hidden gap-3 lg:grid lg:auto-cols-fr lg:grid-flow-col">
        @foreach ($tiles as $t)
            <x-ui.action-tile :ink="$t['primary']" :href="$t['url']" :icon="$t['icon']" :label="$t['label']" :hint="$t['hint']" :hint-tone="$t['hintTone']" :shortcut="$t['shortcut']" />
        @endforeach
    </div>
</div>
@endif
