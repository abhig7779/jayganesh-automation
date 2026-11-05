{{--
    Home: Technician. Only "My job cards", no money anywhere (UX-FLOWS §2 Technician). The cards
    are the job cards module's own "My jobs" screen (Start, Add photo, Ready for QC, tick work),
    fed by Dashboard\Workshop::forTechnician through HomeData.
--}}
@if ($cards === null)
    <x-layouts.app title="My jobs">
        <div class="card">
            <x-ui.empty-state icon="wrench" text="Your cars will show here once job cards are switched on." />
        </div>
    </x-layouts.app>
@else
    @include('jobcards.mine', ['cards' => $cards, 'user' => $user])
@endif
