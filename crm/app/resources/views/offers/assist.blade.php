<x-layouts.app :title="'Send: '.$offer->title">
    <x-slot:header>
        <div>
            <a href="{{ route('offers.show', $offer) }}" class="text-sm font-medium text-zinc-600 hover:text-ink">← {{ $offer->title }}</a>
            <h1 class="mt-1 text-2xl font-bold">Send one by one</h1>
            <p class="text-sm text-zinc-600">Open WhatsApp, press Send there, come back and press “Sent”. Your progress is saved after every customer.</p>
        </div>
    </x-slot:header>

    <div id="assist-panel">
        @include('offers.partials.assist-panel')
    </div>
</x-layouts.app>
