<?php

namespace App\Policies;

use App\Models\JobCard;
use App\Models\User;

/**
 * Job card access (UX-FLOWS page 9):
 * - jobcards.create (Owner, Admin, Sales): open "Car in" cards, see the whole board, see prices,
 *   assign technicians and make every status move.
 * - jobcards.manage without create (Technician): only the cards assigned to them; can tick work,
 *   add photos, Start and send to QC. Never sees money.
 * - jobcards.view alone: read-only.
 */
class JobCardPolicy
{
    /** Moves a technician may make on their own card. Everything else needs a supervisor. */
    public const TECHNICIAN_MOVES = [
        JobCard::WAITING => [JobCard::WORKING],
        JobCard::WORKING => [JobCard::QC, JobCard::WAITING],
    ];

    public function before(User $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    /** Sees every card and the board, not just their own. */
    public function seeAll(User $user): bool
    {
        return $user->can('jobcards.view') && $user->can('jobcards.create');
    }

    /** Prices and totals are hidden from technicians (UX-FLOWS §2: no money anywhere). */
    public function seePrices(User $user): bool
    {
        return $this->seeAll($user);
    }

    public function viewAny(User $user): bool
    {
        return $user->can('jobcards.view');
    }

    public function view(User $user, JobCard $card): bool
    {
        return $user->can('jobcards.view')
            && ($this->seeAll($user) || $card->technician_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->can('jobcards.create');
    }

    /** Tick items, add photos. */
    public function work(User $user, JobCard $card): bool
    {
        return $this->view($user, $card)
            && ($user->can('jobcards.manage') || $user->can('jobcards.create'))
            && ! in_array($card->status, [JobCard::DELIVERED, JobCard::CANCELLED], true);
    }

    /** Add items, change technician, promised time, bay. */
    public function update(User $user, JobCard $card): bool
    {
        return $this->seeAll($user) && $card->isOpen();
    }

    public function move(User $user, JobCard $card, string $to): bool
    {
        if (! $this->view($user, $card) || ! ($user->can('jobcards.manage') || $user->can('jobcards.create'))) {
            return false;
        }
        if ($this->seeAll($user)) {
            return true;
        }

        return in_array($to, self::TECHNICIAN_MOVES[$card->status] ?? [], true);
    }

    public function makeBill(User $user, JobCard $card): bool
    {
        return $this->seeAll($user) && $user->can('invoices.create');
    }
}
