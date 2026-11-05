{{-- Car model (searchable list) + plate, for a new customer or a new car. --}}
<div class="relative {{ ($inline ?? false) ? '' : 'grid gap-2 '.($pc ? 'grid-cols-2' : '') }}" @click.outside="modelOpen = false">
    <div class="relative">
        <label class="mb-1 block text-xs font-semibold text-zinc-700">Car model</label>
        <input x-model="newCust.model" @focus="modelOpen = true" @input="modelOpen = true; newCust.make = ''" @keydown.escape.stop="modelOpen = false"
               @keydown.enter.prevent="modelMatches[0] && pickModel(modelMatches[0])"
               autocomplete="off" placeholder="Search model, e.g. Creta" class="input {{ $pc ? '!min-h-9 !text-sm' : '!min-h-12' }}">
        <ul x-show="modelOpen && modelMatches.length" class="absolute inset-x-0 top-full z-30 mt-1 max-h-56 overflow-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-[0_8px_24px_rgba(0,0,0,.08)]">
            <template x-for="m in modelMatches" :key="m.label">
                <li><button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-zinc-50" @click="pickModel(m)"><span class="text-zinc-600" x-text="m.make"></span> <span class="font-semibold text-ink" x-text="m.model"></span></button></li>
            </template>
        </ul>
    </div>
    @unless ($inline ?? false)
        <div>
            <label class="mb-1 block text-xs font-semibold text-zinc-700">Plate (optional)</label>
            <input x-model="newCust.reg" autocomplete="off" placeholder="MH12 AB 1234" class="input uppercase {{ $pc ? '!min-h-9 !text-sm' : '!min-h-12' }}">
        </div>
    @endunless
</div>
@if ($inline ?? false)
    <div class="{{ $pc ? 'col-span-1' : '' }}">
        <label class="mb-1 block text-xs font-semibold text-zinc-700">Plate (optional)</label>
        <input x-model="newCust.reg" autocomplete="off" placeholder="MH12 AB 1234" class="input uppercase {{ $pc ? '!min-h-9 !text-sm' : '!min-h-12' }}">
    </div>
@endif
