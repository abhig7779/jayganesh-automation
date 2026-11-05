{{-- Duplicate-number notice under the mobile field (htmx partial from customers.check, also rendered after a failed save). --}}
@if ($existing)
    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-warn-line bg-warn-tint px-3 py-2.5" role="alert">
        <x-icon name="alert" class="size-5 shrink-0 text-warn" />
        <p class="min-w-0 flex-1 text-[13px] font-medium text-ink">
            This number already belongs to <strong class="font-semibold">{{ $existing->name }}</strong>@if ($existing->isArchived()) (archived)@endif.
            @if ($existing->cars->isNotEmpty())
                <span class="text-muted">{{ $existing->cars->first()->model }}@if ($existing->cars->first()->reg_no) · {{ \App\Support\RegNo::pretty($existing->cars->first()->reg_no) }}@endif</span>
            @endif
        </p>
        <a href="{{ route('customers.show', $existing) }}" class="btn-primary min-h-10 px-3 text-[13px] lg:min-h-8">Open profile</a>
    </div>
@endif
