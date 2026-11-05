{{--
    Home: Sales / Counter (UX-FLOWS §2, approved design D0 desktop / M1 phone). Also used for the
    Marketing role. Every section is gated by permission, not role name, so Extra access shows
    up here on its own and the counter never sees leads/offers unless the owner gives them.
--}}
<x-layouts.app title="Home">
    @include('home.partials.greeting')
    @include('home.partials.tiles')

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px] xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="min-w-0 space-y-5">
            @can('quotations.view')
                @include('home.partials.followups')
            @endcan

            @if (! empty($waiting))
                <x-ui.panel title="Waiting on me" flush>
                    <div class="divide-y divide-line">
                        @foreach ($waiting as $w)
                            <x-ui.list-row :href="$w['url']" :icon="$w['icon']" :tone="$w['tone']" :title="$w['title']" />
                        @endforeach
                    </div>
                </x-ui.panel>
            @endif

            {{-- phone: My today sits between follow-ups and recent bills --}}
            @can('invoices.create')
                <div class="lg:hidden">@include('home.partials.my-today')</div>
            @endcan

            @can('invoices.view')
                @include('home.partials.recent-bills')
            @endcan
        </div>

        <aside class="min-w-0 space-y-5">
            @can('invoices.create')
                <div class="hidden lg:block">@include('home.partials.my-today')</div>
            @endcan
            @include('home.partials.side-panels')
        </aside>
    </div>
</x-layouts.app>
