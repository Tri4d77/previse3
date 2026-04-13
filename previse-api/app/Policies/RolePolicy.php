<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.manage_roles');
    }

    public function view(User $user, Role $role): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('users.manage_roles')
            && $user->organization_id === $role->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage_roles');
    }

    public function update(User $user, Role $role): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('users.manage_roles')
            && $user->organization_id === $role->organization_id;
    }

    public function delete(User $user, Role $role): bool
    {
        // Rendszer szerepkör nem törölhető
        if ($role->is_system) {
            return false;
        }

        // Nem törölhető, ha van hozzárendelt felhasználó
        if ($role->users()->exists()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasPermission('users.manage_roles')
            && $user->organization_id === $role->organization_id;
    }
}
