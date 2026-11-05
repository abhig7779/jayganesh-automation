<x-layouts.app title="Settings">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold">Settings</h1>
            <p class="text-sm text-zinc-500">These details print on bills and quotations. Every change is recorded in the audit log.</p>
        </div>
    </x-slot:header>

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-5">
        @csrf @method('PUT')
        @foreach ($groups as $group => $fields)
            <section class="card">
                <h2 class="mb-4 text-base font-semibold">{{ $group }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($fields as $key => $f)
                        @php $name = str_replace('.', '__', $key); $val = old($name, $values[$key]); @endphp
                        <div @class(['sm:col-span-2' => in_array($f['type'], ['textarea', 'checkbox'])])>
                            @if ($f['type'] === 'checkbox')
                                <label class="flex items-center gap-2 text-sm font-medium">
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <input type="checkbox" name="{{ $name }}" value="1" class="size-4 rounded" @checked($val)> {{ $f['label'] }}
                                </label>
                            @else
                                <label class="label" for="{{ $name }}">{{ $f['label'] }}</label>
                                @if ($f['type'] === 'textarea')
                                    <textarea class="input min-h-24 py-2" id="{{ $name }}" name="{{ $name }}" rows="3">{{ $val }}</textarea>
                                @elseif ($f['type'] === 'select')
                                    <select class="input" id="{{ $name }}" name="{{ $name }}">
                                        @foreach ($f['options'] as $ov => $ol)
                                            <option value="{{ $ov }}" @selected($val === $ov)>{{ $ol }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input class="input" id="{{ $name }}" name="{{ $name }}" type="{{ $f['type'] }}" value="{{ $val }}" @if($f['type'] === 'number') step="any" @endif>
                                @endif
                            @endif
                            @error($name) <p class="error">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
        <div class="sticky bottom-20 flex justify-end lg:bottom-4">
            <button class="btn-primary shadow-lg">Save settings</button>
        </div>
    </form>
</x-layouts.app>
