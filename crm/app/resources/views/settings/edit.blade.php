<x-layouts.app title="Settings">
    <x-ui.page-header title="Settings" subtitle="These details print on bills and quotations. Every change is recorded in the activity log." />

    <form method="POST" action="{{ route('settings.update') }}" class="max-w-4xl">
        @csrf @method('PUT')
        <div class="space-y-5">
            @foreach ($groups as $group => $fields)
                <x-ui.panel :title="$group">
                    <div class="grid gap-x-5 gap-y-4 sm:grid-cols-2">
                        @foreach ($fields as $key => $f)
                            @php $name = str_replace('.', '__', $key); $val = old($name, $values[$key]); @endphp
                            <div @class(['sm:col-span-2' => in_array($f['type'], ['textarea', 'checkbox'])])>
                                @if ($f['type'] === 'checkbox')
                                    <label class="flex min-h-11 items-center gap-3 text-[15px] font-medium lg:text-sm">
                                        <input type="hidden" name="{{ $name }}" value="0">
                                        <input type="checkbox" name="{{ $name }}" value="1" class="size-5 accent-ink" @checked($val)> {{ $f['label'] }}
                                    </label>
                                @else
                                    <label class="label" for="{{ $name }}">{{ $f['label'] }}</label>
                                    @if ($f['type'] === 'textarea')
                                        <textarea class="input min-h-24 py-2.5" id="{{ $name }}" name="{{ $name }}" rows="3">{{ $val }}</textarea>
                                    @elseif ($f['type'] === 'select')
                                        <select class="input" id="{{ $name }}" name="{{ $name }}">
                                            @foreach ($f['options'] as $ov => $ol)
                                                <option value="{{ $ov }}" @selected($val === $ov)>{{ $ol }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input class="input" id="{{ $name }}" name="{{ $name }}" type="{{ $f['type'] }}" value="{{ $val }}" @if ($f['type'] === 'number') step="any" inputmode="decimal" @endif>
                                    @endif
                                @endif
                                @error($name) <p class="error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </x-ui.panel>
            @endforeach
        </div>
        <div class="sticky bottom-[calc(env(safe-area-inset-bottom)+72px)] z-10 -mx-4 mt-5 border-t border-line bg-canvas px-4 py-3 lg:bottom-0 lg:mx-0 lg:flex lg:items-center lg:gap-3 lg:rounded-b-xl lg:px-0">
            <button class="btn-primary w-full lg:w-auto">Save settings</button>
            <span class="hidden text-xs text-muted lg:inline">Changes apply to new bills and quotations.</span>
        </div>
    </form>
</x-layouts.app>
