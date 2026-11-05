<x-layouts.app :title="$product->name">
    <x-slot:header>
        <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-zinc-700 hover:text-ink">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
            Stock &amp; prices
        </a>
    </x-slot:header>

    <div class="max-w-2xl overflow-hidden rounded-xl border border-zinc-200 bg-white">
        @include('products.partials.detail')
    </div>
</x-layouts.app>
