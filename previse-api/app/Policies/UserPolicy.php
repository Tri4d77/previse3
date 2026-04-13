<?php

namespace App\Policies;

use App\Models\User;

/**
 * Felhasználó-kezelés jogosultságai.
 */
class UserPolicy
{
    /**
     * Felhasználók listájának megtekintése.
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasPermission('users.read');
    }

    /**
     * Egy felhasználó adatainak megtekintése.
     */
    public function view(User $authUser, User $target): bool
    {
        // Saját profilt mindenki megnézheti
        if ($authUser->id === $target->id) {
            return true;
        }

        // Szuper-admin mindent lát
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        // Csak a saját szervezet felhasználóit
        return $authUser->hasPermission('users.read')
            && $authUser->organization_id === $target->organization_id;
    }

    /**
     * Felhasználó meghívása (létrehozás).
     */
    public function create(User $authUser): bool
    {
        return $authUser->hasPermission('users.create');
    }

    /**
     * Felhasználó adatainak módosítása.
     */
    public function update(User $authUser, User $target): bool
    {
        // Saját profilt mindenki szerkesztheti
        if ($authUser->id === $target->id) {
            return true;
        }

        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->hasPermission('users.edit')
            && $authUser->organization_id === $target->organization_id;
    }

    /**
     * Felhasználó aktiválása / deaktiválása.
     */
    public function toggleActive(User $authUser, User $target): bool
    {
        // Saját magát nem deaktiválhatja
        if ($authUser->id === $target->id) {
            return false;
        }

        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->hasPermission('users.deactivate')
            && $authUser->organization_id === $target->organization_id;
    }

    /**
     * Felhasználó törlése (soft delete).
     */
    public function delete(User $authUser, User $target): bool
    {
        // Saját magát nem törölheti
        if ($authUser->id === $target->id) {
            return false;
        }

        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->hasPermission('users.edit')
            && $authUser->organization_id === $target->organization_id;
    }

    /**
     * Szerepkörök kezelése.
     */
    public function manageRoles(User $authUser): bool
    {
        return $authUser->hasPermission('users.manage_roles');
    }
}
