{{--
    Home: Sales / Counter (UX-FLOWS §2, approved design D0 desktop / M1 phone). Also used for the
    Marketing role. Every section is gated by permission, not role name, so Extra access shows
    up here on its own and the counter never sees leads/offers unless the owner gives them.
    Follow-ups due today go above the stats; with none due they drop below as a calm "all done".
--}}
@php $followUpsFirst = $followUps !== null && $followUps['count'] > 0; @endphp
<x-layouts.app title="Home">
    @include('home.partials.greeting')
    @include('home.partials.tiles')
    @can('reminders.manage')
        <x-reminders.home-block :user="$user" />
    @endcan

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px] xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="min-w-0 space-y-5">
            @if ($followUpsFirst)
                @include('home.partials.followups')
            @endif

            {{-- phone: My today sits under follow-ups, above the lists --}}
            @can('invoices.create')
                <div class="lg:hidden">@include('home.partials.my-today')</div>
            @endcan

            @if (! empty($waiting))
                <x-ui.panel title="Waiting on me" flush>
                    <div class="divide-y divide-line">
                        @foreach ($waiting as $w)
                            <x-ui.list-row :href="$w['url']" :icon="$w['icon']" :tone="$w['tone']" :title="$w['title']" :subtitle="$w['subtitle'] ?? null" />
                        @endforeach
                    </div>
                </x-ui.panel>
            @endif

            @unless ($followUpsFirst)
                @include('home.partials.followups')
            @endunless

            @can('invoices.view')
                @include('home.partials.recent-bills')
            @endcan
        </div>

        <aside class="min-w-0 space-y-5">
            @can('invoices.create')
                <div class="hidden lg:block">@include('home.partials.my-today')</div>
            @endcan
            @include('home.partials.side-panels')
            @if ($offers !== null)
                @include('home.partials.offers')
            @endif
        </aside>
    </div>
</x-layouts.app>
