<?php

namespace Database\Seeders;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Platform szervezet + szuper-admin felhasználó létrehozása.
 *
 * Ez a seeder a rendszer minimális alapját hozza létre:
 * - 1 Platform szervezet
 * - 1 Admin szerepkör (minden engedéllyel)
 * - 1 Szuper-admin user
 * - 1 Membership a felhasználónak
 */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Platform szervezet
        $platform = Organization::firstOrCreate(
            ['type' => 'platform'],
            [
                'name' => 'Previse Platform',
                'slug' => 'previse-platform',
                'is_active' => true,
            ]
        );

        // 2. Admin szerepkör a platform szervezethez (összes engedéllyel)
        $adminRole = Role::firstOrCreate(
            ['organization_id' => $platform->id, 'slug' => 'admin'],
            [
                'name' => 'Adminisztrátor',
                'description' => 'Teljes hozzáférés a platform minden funkciójához.',
                'is_system' => true,
            ]
        );

        // Összes engedély hozzárendelése
        $allPermissions = Permission::pluck('id');
        $adminRole->permissions()->sync($allPermissions);

        // 3. Szuper-admin felhasználó
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@previse.hu'],
            [
                'name' => 'Szuper Admin',
                'password' => 'Admin123!',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Felhasználó beállítások létrehozása
        $superAdmin->getOrCreateSettings();

        // 4. Membership a szuper-admin → Platform kapcsolathoz
        Membership::firstOrCreate(
            [
                'user_id' => $superAdmin->id,
                'organization_id' => $platform->id,
            ],
            [
                'role_id' => $adminRole->id,
                'is_active' => true,
                'joined_at' => now(),
            ]
        );

        $this->command->info('Platform + szuper-admin létrehozva:');
        $this->command->info('  Email: admin@previse.hu');
        $this->command->info('  Jelszó: Admin123!');
    }
}
