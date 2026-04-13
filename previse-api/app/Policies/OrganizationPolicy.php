<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Szervezet beállításainak megtekintése.
     */
    public function view(User $user, Organization $organization): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->organization_id === $organization->id;
    }

    /**
     * Szervezet beállításainak módosítása.
     */
    public function update(User $user, Organization $organization): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('settings.manage_organization')
            && $user->organization_id === $organization->id;
    }

    /**
     * Ügyfél-szervezet létrehozása (csak előfizetők).
     */
    public function createClient(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('settings.manage_organization')
            && $user->organization->isSubscriber();
    }

    /**
     * Előfizető szervezet létrehozása (csak platform admin).
     */
    public function createSubscriber(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
