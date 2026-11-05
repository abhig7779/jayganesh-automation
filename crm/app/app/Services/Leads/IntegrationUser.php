<?php

namespace App\Services\Leads;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The login that owns the website / ads API keys. It has no role and is switched off, so it can
 * never sign in to the CRM, and a leaked key can't read customers or bills: every other API route
 * checks permissions this user doesn't have. Leads it creates show "Website & ads" as the creator.
 */
final class IntegrationUser
{
    public const EMAIL = 'integrations@crm.invalid';

    public static function get(): User
    {
        return User::query()->where('email', self::EMAIL)->first()
            ?? User::query()->forceCreate([
                'name' => 'Website & ads (API)',
                'email' => self::EMAIL,
                'password' => Str::random(48),
                'branch_id' => Branch::query()->value('id'),
                'is_active' => false,
            ]);
    }

    public static function is(?User $user): bool
    {
        return $user !== null && $user->email === self::EMAIL;
    }
}
