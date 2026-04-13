<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        // Csoportokat mindenki láthatja (szűrőkben használjuk)
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage_roles');
    }

    public function update(User $user, Group $group): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('users.manage_roles')
            && $user->organization_id === $group->organization_id;
    }

    public function delete(User $user, Group $group): bool
    {
        // Nem törölhető, ha vannak tagjai
        if ($group->users()->exists()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('users.manage_roles')
            && $user->organization_id === $group->organization_id;
    }
}
