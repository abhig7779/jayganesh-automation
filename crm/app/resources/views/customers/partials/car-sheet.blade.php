{{-- Add or edit a car (bottom sheet on phone, dialog on PC). $car = null for Add. --}}
@php
    $sheet = $car ? 'edit-car-'.$car->id : 'add-car';
    $isThis = old('_sheet') === $sheet;
    $action = $car ? route('customers.cars.update', [$customer, $car]) : route('customers.cars.store', $customer);
@endphp
<x-ui.bottom-sheet :name="$sheet" :title="$car ? 'Edit car' : 'Add car'" :open="$isThis && $errors->any()"
                   :form="['action' => $action, 'method' => $car ? 'PUT' : 'POST']">
    <input type="hidden" name="_sheet" value="{{ $sheet }}">
    <div class="space-y-4 pt-1">
        @include('customers.partials.car-picker', [
            'options' => $carOptions,
            'value' => $isThis ? old('car') : ($car ? $car->make.' '.$car->model : ''),
            'make' => $isThis ? old('car_make') : ($car?->make ?? ''),
            'id' => $sheet.'-model',
        ])
        <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
                <label for="{{ $sheet }}-reg" class="label">Car number <span class="font-normal text-muted">(optional)</span></label>
                <input id="{{ $sheet }}-reg" name="reg_no" class="input tnum uppercase placeholder:normal-case" maxlength="20" autocomplete="off" autocapitalize="characters"
                       value="{{ $isThis ? old('reg_no') : \App\Support\RegNo::pretty($car?->reg_no) }}" placeholder="MH12 AB 1234">
                @if ($isThis) @error('reg_no') <p class="error">{{ $message }}</p> @enderror @endif
            </div>
            <div>
                <label for="{{ $sheet }}-year" class="label">Year</label>
                <input id="{{ $sheet }}-year" name="year" type="text" inputmode="numeric" maxlength="4" class="input tnum" value="{{ $isThis ? old('year') : $car?->year }}" placeholder="{{ now()->year }}">
                @if ($isThis) @error('year') <p class="error">{{ $message }}</p> @enderror @endif
            </div>
            <div>
                <label for="{{ $sheet }}-colour" class="label">Colour</label>
                <input id="{{ $sheet }}-colour" name="colour" class="input" maxlength="40" value="{{ $isThis ? old('colour') : $car?->colour }}" placeholder="White">
            </div>
        </div>
    </div>
    <x-slot:footer>
        <button class="btn-accent w-full">{{ $car ? 'Save car' : 'Add car' }}</button>
    </x-slot:footer>
</x-ui.bottom-sheet>
