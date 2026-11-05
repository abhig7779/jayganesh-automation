{{--
    Make/model picker with search over App\Support\CarModels (no server round trip).
    @include('customers.partials.car-picker', ['options' => $carOptions, 'value' => old('car'), 'make' => old('car_make'), 'id' => 'car'])
    Sends `car` (the text) and `car_make` (set when a suggestion is picked).
--}}
<div x-data="{
        q: @js((string) ($value ?? '')), make: @js((string) ($make ?? '')), open: false, active: 0,
        all: @js($options),
        get matches() {
            const words = this.q.toLowerCase().split(/\s+/).filter(Boolean);
            if (!words.length) return [];
            return this.all.filter(o => words.every(w => o.label.toLowerCase().includes(w))).slice(0, 8);
        },
        pick(o) { this.q = o.label; this.make = o.make; this.open = false; },
     }" class="relative" @click.outside="open = false">
    <label for="{{ $id }}" class="label">Car model <span class="font-normal text-muted">(optional)</span></label>
    <div class="relative">
        <x-icon name="car" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted lg:left-3 lg:size-[18px]" />
        <input id="{{ $id }}" name="car" type="text" x-model="q" autocomplete="off" class="input pl-11 lg:pl-9" placeholder="Search: Creta, Nexon, Innova…"
               @input="open = true; active = 0; make = ''" @focus="open = q.length > 0"
               @keydown.arrow-down.prevent="active = Math.min(active + 1, matches.length - 1)" @keydown.arrow-up.prevent="active = Math.max(active - 1, 0)"
               @keydown.enter="if (open && matches[active]) { $event.preventDefault(); pick(matches[active]) }" @keydown.escape="open = false"
               role="combobox" aria-autocomplete="list" :aria-expanded="(open && matches.length > 0).toString()" aria-controls="{{ $id }}-list">
    </div>
    <input type="hidden" name="car_make" :value="make">
    <ul id="{{ $id }}-list" x-show="open && matches.length" x-cloak role="listbox"
        class="absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-xl border border-line bg-surface py-1 shadow-pop">
        <template x-for="(o, i) in matches" :key="o.label">
            <li role="option" :aria-selected="(i === active).toString()" @mousedown.prevent="pick(o)" @mouseenter="active = i"
                :class="i === active ? 'bg-sunken' : ''" class="flex min-h-11 cursor-pointer items-center justify-between gap-3 px-3 lg:min-h-9">
                <span class="text-[15px] font-semibold lg:text-sm" x-text="o.model"></span>
                <span class="text-xs font-medium text-muted" x-text="o.make"></span>
            </li>
        </template>
    </ul>
    @error('car') <p class="error">{{ $message }}</p> @enderror
    @error('make') <p class="error">{{ $message }}</p> @enderror
</div>
