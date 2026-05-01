<?php

namespace App\Services;

use App\Models\LocationType;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;

/**
 * Alap szerepkörök automatikus létrehozása egy új szervezethez.
 *
 * Minden új szervezet (subscriber, client) kap egy alap szerepkör-készletet:
 * - admin, diszpécser, felhasználó, rögzítő, karbantartó
 *
 * A Platform szervezet csak admin szerepkört kap (szuper-admin).
 */
class OrganizationRoleSeeder
{
    /**
     * Alap szerepkörök létrehozása.
     */
    public static function seed(Organization $org): void
    {
        $allPermissions = Permission::all();

        if ($org->isPlatform()) {
            self::createPlatformRoles($org, $allPermissions);
            return;
        }

        self::createSubscriberRoles($org, $allPermissions);
        self::seedDefaultLocationTypes($org);
    }

    /**
     * Alap helyszín-típusok minden új subscriber/client szervezethez.
     * A felhasználó később törölheti vagy módosíthatja a Helyszín-beállítások felületen.
     */
    private static function seedDefaultLocationTypes(Organization $org): void
    {
        $defaults = [
            'Iroda',
            'Bevásárlóközpont',
            'Lakóház',
            'Ipari',
            'Oktatási',
            'Egészségügyi',
            'Raktár',
            'Egyéb',
        ];

        foreach ($defaults as $i => $name) {
            LocationType::firstOrCreate(
                ['organization_id' => $org->id, 'name' => $name],
                ['sort_order' => $i],
            );
        }
    }

    private static function createPlatformRoles(Organization $org, $allPermissions): void
    {
        $admin = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'admin'],
            [
                'name' => 'Adminisztrátor',
                'description' => 'Teljes hozzáférés a platform minden funkciójához.',
                'is_system' => true,
            ]
        );
        $admin->permissions()->sync($allPermissions->pluck('id'));
    }

    private static function createSubscriberRoles(Organization $org, $allPermissions): void
    {
        // Admin - minden engedély
        $admin = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'admin'],
            [
                'name' => 'Adminisztrátor',
                'description' => 'Teljes hozzáférés a szervezeten belül.',
                'is_system' => true,
            ]
        );
        $admin->permissions()->sync($allPermissions->pluck('id'));

        // Diszpécser
        $dispatcher = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'dispatcher'],
            [
                'name' => 'Diszpécser',
                'description' => 'Bejelentések kezelése, feladatok kiosztása.',
                'is_system' => true,
            ]
        );
        $dispatcherExcluded = [
            'users.create', 'users.edit', 'users.deactivate', 'users.manage_roles',
            'settings.manage_organization', 'settings.manage_sla',
            'tickets.delete', 'tasks.delete', 'projects.delete', 'issues.delete',
            'contracts.delete', 'contracts.manage_contractors',
            // Locations: csak az admin kezelhet katalógus-szintű dolgokat
            'locations.delete', 'locations.manage_tags', 'locations.manage_types',
            'locations.import',
        ];
        $dispatcher->permissions()->sync(
            $allPermissions->filter(
                fn ($p) => ! in_array($p->module . '.' . $p->action, $dispatcherExcluded)
            )->pluck('id')
        );

        // Felhasználó
        $user = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'user'],
            [
                'name' => 'Felhasználó',
                'description' => 'Saját bejelentések és feladatok kezelése.',
                'is_system' => true,
            ]
        );
        $userAllowed = [
            'tickets.read', 'tickets.create', 'tickets.update', 'tickets.close',
            'tasks.read', 'tasks.update', 'tasks.complete',
            'projects.read',
            'issues.read', 'issues.create', 'issues.update',
            'suggestions.read', 'suggestions.create', 'suggestions.vote',
            'documents.read', 'documents.download',
            'locations.read',
            'assets.read',
            'maintenance.read',
            'reports.view_dashboard',
            'messages.send',
        ];
        $user->permissions()->sync(
            $allPermissions->filter(
                fn ($p) => in_array($p->module . '.' . $p->action, $userAllowed)
            )->pluck('id')
        );

        // Rögzítő
        $recorder = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'recorder'],
            [
                'name' => 'Rögzítő',
                'description' => 'Adatrögzítés, bejelentés létrehozása.',
                'is_system' => true,
            ]
        );
        $recorderAllowed = [
            'tickets.read', 'tickets.create',
            'issues.read', 'issues.create',
            'suggestions.read', 'suggestions.create', 'suggestions.vote',
            'messages.send',
        ];
        $recorder->permissions()->sync(
            $allPermissions->filter(
                fn ($p) => in_array($p->module . '.' . $p->action, $recorderAllowed)
            )->pluck('id')
        );

        // Karbantartó
        $maintainer = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'maintainer'],
            [
                'name' => 'Karbantartó',
                'description' => 'Karbantartási feladatok, eszközkezelés.',
                'is_system' => true,
            ]
        );
        $maintainerAllowed = [
            'tickets.read', 'tickets.create', 'tickets.update',
            'tasks.read', 'tasks.update', 'tasks.complete',
            'issues.read', 'issues.create', 'issues.update', 'issues.resolve',
            'locations.read',
            'assets.read', 'assets.update', 'assets.change_status',
            'maintenance.read', 'maintenance.log_work',
            'suggestions.read', 'suggestions.create', 'suggestions.vote',
            'reports.view_dashboard',
            'messages.send',
        ];
        $maintainer->permissions()->sync(
            $allPermissions->filter(
                fn ($p) => in_array($p->module . '.' . $p->action, $maintainerAllowed)
            )->pluck('id')
        );
    }
}
