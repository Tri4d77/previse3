<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Demo előfizető szervezet alap szerepkörökkel.
 *
 * Ezt a seedert IDEIGLENESEN használjuk, amíg az M2.5 fázisban elkészül
 * a szuper-admin szervezet-kezelő felület. Addig legalább egy demo
 * előfizető szervezet legyen, amivel a multi-membership flow tesztelhető.
 *
 * FONTOS: a szervezethez nem rendelünk felhasználókat, csak üresen áll.
 * Az első adminisztrátort a szuper-admin meghívhatja a Users admin felületen
 * (miután belépett XY Karbantartó Kft. kontextusba az impersonation-nel).
 */
class DemoSubscriberSeeder extends Seeder
{
    public function run(): void
    {
        // Platform keresése (már létezik a PlatformSeeder által)
        $platform = Organization::where('type', 'platform')->first();

        if (! $platform) {
            $this->command->error('Platform szervezet nem található! Először futtasd a PlatformSeeder-t.');
            return;
        }

        // Demo előfizető szervezet
        $subscriber = Organization::firstOrCreate(
            ['slug' => 'xy-karbantarto-kft'],
            [
                'parent_id' => $platform->id,
                'type' => 'subscriber',
                'name' => 'XY Karbantartó Kft.',
                'address' => '1234 Budapest, Példa utca 1.',
                'city' => 'Budapest',
                'zip_code' => '1234',
                'phone' => '+36 1 234 5678',
                'email' => 'info@xykarbantarto.hu',
                'is_active' => true,
            ]
        );

        // Alap szerepkörök létrehozása
        $this->createDefaultRoles($subscriber);

        $this->command->info('Demo előfizető szervezet létrehozva:');
        $this->command->info('  Név: XY Karbantartó Kft.');
        $this->command->info('  Szerepkörök: Admin, Diszpécser, Felhasználó, Rögzítő, Karbantartó');
        $this->command->info('');
        $this->command->info('Teszteléshez:');
        $this->command->info('  1. Jelentkezz be szuper-adminként (admin@previse.hu)');
        $this->command->info('  2. A szervezet-váltóval lépj be a XY Karbantartó Kft.-be');
        $this->command->info('  3. Hívj meg új felhasználókat a Users oldalon');
    }

    private function createDefaultRoles(Organization $org): void
    {
        $allPermissions = Permission::all();

        // Admin - minden engedély
        $admin = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'admin'],
            ['name' => 'Adminisztrátor', 'description' => 'Teljes hozzáférés a szervezeten belül.', 'is_system' => true]
        );
        $admin->permissions()->sync($allPermissions->pluck('id'));

        // Diszpécser - legtöbb funkció, de nem tud felhasználókat kezelni és szervezeti beállításokat módosítani
        $dispatcher = Role::firstOrCreate(
            ['organization_id' => $org->id, 'slug' => 'dispatcher'],
            ['name' => 'Diszpécser', 'description' => 'Bejelentések kezelése, feladatok kiosztása.', 'is_system' => true]
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

        // Felhasználó - saját bejelentések, feladatok megtekintése
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

        // Rögzítő - bejelentés és hibajegy létrehozása
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

        // Karbantartó - karbantartási munkák
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
    }
}
