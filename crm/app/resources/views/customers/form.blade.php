{{-- Add / edit customer. Mobile first, with an instant duplicate check; one optional car on Add. --}}
@php
    $editing = $customer->exists;
    $oldMobile = preg_replace('/\D+/', '', (string) old('mobile', $prefillMobile));
    $oldMobile = strlen($oldMobile) > 10 ? substr($oldMobile, -10) : $oldMobile;
    $pretty = strlen($oldMobile) > 5 ? substr($oldMobile, 0, 5).' '.substr($oldMobile, 5) : $oldMobile;
    $duplicate = $errors->has('mobile') && strlen($oldMobile) === 10
        ? \App\Models\Customer::query()->where('mobile', '+91'.$oldMobile)->when($editing, fn ($q) => $q->whereKeyNot($customer->id))->with('cars')->first()
        : null;
    $hasMore = $editing || old('area') || old('alt_mobile') || old('email') || old('notes') || $errors->hasAny(['area', 'alt_mobile', 'email', 'notes']);
@endphp
<x-layouts.app :title="$editing ? 'Edit customer' : 'Add customer'">
    <x-ui.page-header :title="$editing ? 'Edit '.$customer->name : 'Add customer'"
                      :subtitle="$editing ? null : 'Mobile number first. Only the number and name are needed.'"
                      :back="$editing ? route('customers.show', $customer) : route('customers.index')"
                      :back-label="$editing ? $customer->name : 'Customers'" />

    <form method="POST" action="{{ $editing ? route('customers.update', $customer) : route('customers.store') }}" class="max-w-2xl" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        @if ($next) <input type="hidden" name="next" value="{{ $next }}"> @endif

        <div class="card space-y-5">
            {{-- Mobile --}}
            <div x-data="{
                    v: @js($pretty),
                    fmt(raw) { let d = raw.replace(/\D/g, ''); if (d.length > 10 && (d.startsWith('91') || d.startsWith('0'))) d = d.slice(-10); d = d.slice(0, 10); return d.length > 5 ? d.slice(0, 5) + ' ' + d.slice(5) : d; },
                    get digits() { return this.v.replace(/\D/g, '') },
                 }">
                <label for="mobile" class="label">Mobile number</label>
                <div class="relative">
                    <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 border-r border-line pr-2.5 text-base font-semibold text-muted lg:text-sm">+91</span>
                    <input id="mobile" name="mobile" type="tel" inputmode="numeric" autocomplete="off" maxlength="16" required
                           x-model="v" @input="v = fmt($event.target.value)" @paste="$nextTick(() => v = fmt(v))"
                           @class(['input tnum pl-16 text-[17px] font-semibold tracking-wide lg:pl-14 lg:text-[15px]', 'border-bad' => $errors->has('mobile')])
                           placeholder="98231 07676" @unless ($editing) autofocus @endunless
                           hx-get="{{ route('customers.check') }}" hx-trigger="input changed delay:250ms" hx-target="#mobile-duplicate"
                           hx-vals='{"ignore": "{{ $customer->id ?? 0 }}"}' aria-describedby="mobile-help">
                </div>
                <p id="mobile-help" class="mt-1.5 text-[13px] font-medium" :class="digits.length > 0 && digits.length < 10 ? 'text-warn' : 'text-muted'"
                   x-text="digits.length > 0 && digits.length < 10 ? 'Mobile number needs 10 digits (' + digits.length + ' so far)' : 'Any format works: 98231 07676, +91…, 0982…'"></p>
                @error('mobile') @unless ($duplicate) <p class="error">{{ $message }}</p> @endunless @enderror
                <div id="mobile-duplicate" aria-live="polite">@include('customers.partials.duplicate', ['existing' => $duplicate])</div>
            </div>

            {{-- Name --}}
            <div>
                <label for="name" class="label">Name</label>
                <input id="name" name="name" class="input" value="{{ old('name', $customer->name) }}" required maxlength="120" autocomplete="off" autocapitalize="words" placeholder="Rahul Pawar">
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>

            {{-- Source --}}
            <div>
                <span class="label" id="source-label">How did they hear about us?</span>
                <x-ui.segmented name="source" :options="\App\Models\Customer::SOURCE_LABELS" :value="old('source', $customer->source ?? 'walk_in')" wrap label="How did they hear about us?" class="w-full" />
                @error('source') <p class="error">{{ $message }}</p> @enderror
            </div>

            @unless ($editing)
                <div class="grid gap-4 border-t border-line pt-5 sm:grid-cols-[minmax(0,1fr)_200px]">
                    @include('customers.partials.car-picker', ['options' => $carOptions, 'value' => old('car'), 'make' => old('car_make'), 'id' => 'car'])
                    <div>
                        <label for="reg_no" class="label">Car number <span class="font-normal text-muted">(optional)</span></label>
                        <input id="reg_no" name="reg_no" class="input tnum uppercase placeholder:normal-case" value="{{ old('reg_no') }}" maxlength="20" autocomplete="off" autocapitalize="characters" placeholder="MH12 AB 1234">
                        @error('reg_no') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endunless

            {{-- More details --}}
            <details class="group border-t border-line pt-4" @if ($hasMore) open @endif>
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between text-[15px] font-semibold lg:min-h-8 lg:text-sm [&::-webkit-details-marker]:hidden">
                    More details <span class="text-[13px] font-medium text-muted">area, second number, email, notes</span>
                    <x-icon name="chevron-down" class="size-5 text-muted transition-transform group-open:rotate-180" />
                </summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="area" class="label">Area</label>
                        <input id="area" name="area" class="input" value="{{ old('area', $customer->area) }}" maxlength="120" placeholder="Hadapsar, Pune">
                        @error('area') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="alt_mobile" class="label">Second mobile</label>
                        <input id="alt_mobile" name="alt_mobile" type="tel" inputmode="numeric" class="input tnum" value="{{ old('alt_mobile', $customer->alt_mobile ? substr($customer->alt_mobile, 3) : '') }}" maxlength="16">
                        @error('alt_mobile') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="email" class="label">Email</label>
                        <input id="email" name="email" type="email" inputmode="email" class="input" value="{{ old('email', $customer->email) }}" maxlength="150">
                        @error('email') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="notes" class="label">Notes</label>
                        <textarea id="notes" name="notes" rows="3" class="input min-h-24 py-2.5" maxlength="2000" placeholder="Prefers evening calls, fleet owner…">{{ old('notes', $customer->notes) }}</textarea>
                        @error('notes') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex min-h-11 items-center gap-3 text-[15px] font-medium sm:col-span-2 lg:text-sm">
                        <input type="checkbox" name="whatsapp_opt_in" value="1" class="size-5 rounded border-line-strong accent-ink" @checked(old('whatsapp_opt_in', $customer->whatsapp_opt_in ?? true))>
                        Send bills and offers on WhatsApp
                    </label>
                </div>
            </details>
        </div>

        {{-- Save: sticky above the tab bar on the phone --}}
        <div class="sticky bottom-[calc(env(safe-area-inset-bottom)+72px)] z-10 -mx-4 mt-4 border-t border-line bg-canvas px-4 py-3 lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:px-0 lg:py-0">
            <div class="flex items-center gap-3">
                <button class="btn-accent w-full lg:w-auto">{{ $editing ? 'Save changes' : ($next === 'bill' ? 'Save & start bill' : 'Save customer') }}</button>
                <a href="{{ $editing ? route('customers.show', $customer) : route('customers.index') }}" class="btn-quiet hidden lg:inline-flex">Cancel</a>
                <span class="ml-auto hidden text-xs text-muted lg:inline">Press Enter to save</span>
            </div>
        </div>
    </form>
</x-layouts.app>
