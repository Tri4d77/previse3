<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSubscriberSeeder extends Seeder
{
    /**
     * Demo előfizető szervezet alap szerepkörökkel, csoportokkal és felhasználókkal.
     * Fejlesztéshez és teszteléshez.
     */
    public function run(): void
    {
        // 1. Előfizető szervezet
        $subscriber = Organization::firstOrCreate(
            ['slug' => 'xy-karbantarto-kft'],
            [
                'type' => 'subscriber',
                'name' => 'XY Karbantartó Kft.',
                'address' => '1234 Budapest, Példa utca 1.',
                'city' => 'Budapest',
                'zip_code' => '1234',
                'phone' => '+36 1 234 5678',
                'email' => 'info@xykarbantarto.hu',
                'tax_number' => '12345678-2-42',
                'is_active' => true,
            ]
        );

        // 2. Alap szerepkörök létrehozása
        $roles = $this->createDefaultRoles($subscriber);

        // 3. Csoportok
        $groups = $this->createDefaultGroups($subscriber);

        // 4. Demo felhasználók
        $this->createDemoUsers($subscriber, $roles, $groups);

        // 5. Ügyfél-szervezetek
        $this->createClientOrganizations($subscriber, $roles);
    }

    private function createDefaultRoles(Organization $org): array
    {
        $allPermissions = Permission::all();

        // Admin - minden engedély
        $admin = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'admin'],
            ['name' => 'Adminisztrátor', 'description' => 'Teljes hozzáférés a szervezeten belül.', 'is_system' => true]
        );
        $admin->permissions()->sync($allPermissions->pluck('id'));

        // Diszpécser
        $dispatcher = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'dispatcher'],
            ['name' => 'Diszpécser', 'description' => 'Bejelentések kezelése, feladatok kiosztása, riportok.', 'is_system' => true]
        );
        $dispatcherPerms = $allPermissions->filter(function ($p) {
            $excluded = [
                'users.create', 'users.edit', 'users.deactivate', 'users.manage_roles',
                'settings.manage_organization', 'settings.manage_sla',
                'tickets.delete', 'tasks.delete', 'projects.delete', 'issues.delete',
                'contracts.delete', 'contracts.manage_contractors',
            ];

            return ! in_array($p->module . '.' . $p->action, $excluded);
        });
        $dispatcher->permissions()->sync($dispatcherPerms->pluck('id'));

        // Felhasználó
        $user = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'user'],
            ['name' => 'Felhasználó', 'description' => 'Saját bejelentések és feladatok kezelése.', 'is_system' => true]
        );
        $userPerms = $allPermissions->filter(function ($p) {
            $allowed = [
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

            return in_array($p->module . '.' . $p->action, $allowed);
        });
        $user->permissions()->sync($userPerms->pluck('id'));

        // Rögzítő
        $recorder = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'recorder'],
            ['name' => 'Rögzítő', 'description' => 'Adatrögzítés, bejelentés létrehozása.', 'is_system' => true]
        );
        $recorderPerms = $allPermissions->filter(function ($p) {
            $allowed = [
                'tickets.read', 'tickets.create',
                'issues.read', 'issues.create',
                'suggestions.read', 'suggestions.create', 'suggestions.vote',
                'messages.send',
            ];

            return in_array($p->module . '.' . $p->action, $allowed);
        });
        $recorder->permissions()->sync($recorderPerms->pluck('id'));

        // Karbantartó
        $maintainer = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'maintainer'],
            ['name' => 'Karbantartó', 'description' => 'Karbantartási feladatok, eszközkezelés.', 'is_system' => true]
        );
        $maintainerPerms = $allPermissions->filter(function ($p) {
            $allowed = [
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

            return in_array($p->module . '.' . $p->action, $allowed);
        });
        $maintainer->permissions()->sync($maintainerPerms->pluck('id'));

        return compact('admin', 'dispatcher', 'user', 'recorder', 'maintainer');
    }

    private function createDefaultGroups(Organization $org): array
    {
        $technical = Group::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Műszaki csapat'],
            ['description' => 'Műszaki karbantartó és javító csapat.']
        );

        $management = Group::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Vezetőség'],
            ['description' => 'Szervezet vezetői.']
        );

        $support = Group::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Ügyfélszolgálat'],
            ['description' => 'Ügyfélszolgálati munkatársak, diszpécserek.']
        );

        return compact('technical', 'management', 'support');
    }

    private function createDemoUsers(Organization $org, array $roles, array $groups): void
    {
        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@xykarbantarto.hu'],
            [
                'organization_id' => $org->id,
                'name' => 'Kovács János',
                'password' => 'Admin123!',
                'role_id' => $roles['admin']->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->getOrCreateSettings();
        $admin->groups()->syncWithoutDetaching([$groups['management']->id]);

        // Diszpécser
        $dispatcher = User::firstOrCreate(
            ['email' => 'disz@xykarbantarto.hu'],
            [
                'organization_id' => $org->id,
                'name' => 'Nagy Anna',
                'password' => 'Admin123!',
                'role_id' => $roles['dispatcher']->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $dispatcher->getOrCreateSettings();
        $dispatcher->groups()->syncWithoutDetaching([$groups['support']->id]);

        // Karbantartó
        $maintainer = User::firstOrCreate(
            ['email' => 'karbantarto@xykarbantarto.hu'],
            [
                'organization_id' => $org->id,
                'name' => 'Tóth Péter',
                'password' => 'Admin123!',
                'role_id' => $roles['maintainer']->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $maintainer->getOrCreateSettings();
        $maintainer->groups()->syncWithoutDetaching([$groups['technical']->id]);
    }

    private function createClientOrganizations(Organization $subscriber, array $roles): void
    {
        // Ügyfél 1
        $client1 = Organization::firstOrCreate(
            ['slug' => 'abc-bevasarlokozpont'],
            [
                'parent_id' => $subscriber->id,
                'type' => 'client',
                'name' => 'ABC Bevásárlóközpont',
                'address' => '1056 Budapest, Vásár utca 10.',
                'city' => 'Budapest',
                'phone' => '+36 1 345 6789',
                'email' => 'info@abc-plaza.hu',
                'is_active' => true,
            ]
        );

        // Ügyfél szerepkör (korlátozott)
        $clientRole = Role::firstOrCreate(
            ['organization_id' => $client1->id, 'slug' => 'client_user'],
            ['name' => 'Ügyfél képviselő', 'description' => 'Bejelentések létrehozása és saját bejelentések követése.', 'is_system' => true]
        );
        $clientPerms = Permission::whereIn('module', ['tickets', 'issues', 'messages'])
            ->whereIn('action', ['read', 'create'])
            ->pluck('id');
        $clientRole->permissions()->sync($clientPerms);

        // Ügyfél felhasználó
        $clientUser = User::firstOrCreate(
            ['email' => 'nagy.peter@abc-plaza.hu'],
            [
                'organization_id' => $client1->id,
                'name' => 'Nagy Péter',
                'password' => 'Admin123!',
                'role_id' => $clientRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $clientUser->getOrCreateSettings();
    }
}
