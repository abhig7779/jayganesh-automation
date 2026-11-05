<x-layouts.app title="System health">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-bold">System health</h1>
            <p class="text-sm text-zinc-500">Checked {{ $checkedAt->format('d M Y, H:i') }} IST · PHP {{ $php }}. Refresh the page to check again.</p>
        </div>
    </x-slot:header>

    <div @class([
        'card mb-4 font-semibold',
        'border-green-200 bg-green-50 text-green-800' => $allOk,
        'border-red-200 bg-red-50 text-red-800' => ! $allOk,
    ])>
        {{ $allOk ? 'OK: everything is healthy.' : 'FAIL: something needs attention. See the red lines below.' }}
    </div>

    <ul class="card divide-y divide-zinc-100 p-0 sm:p-0">
        @foreach ($results as $r)
            <li class="flex items-start gap-3 px-4 py-3 sm:px-5">
                <span @class(['badge mt-0.5 shrink-0', 'bg-green-100 text-green-800' => $r['ok'], 'bg-red-100 text-red-800' => ! $r['ok']])>
                    {{ $r['ok'] ? 'OK' : 'FAIL' }}
                </span>
                <div class="min-w-0">
                    <div class="font-medium">{{ $r['label'] }}</div>
                    <div class="break-words text-sm text-zinc-500">{{ $r['detail'] }}</div>
                </div>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
