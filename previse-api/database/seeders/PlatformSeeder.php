<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    /**
     * Platform szervezet, szuper-admin szerepkör és felhasználó létrehozása.
     */
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

        // Összes engedély hozzárendelése az admin szerepkörhöz
        $allPermissions = Permission::pluck('id');
        $adminRole->permissions()->sync($allPermissions);

        // 3. Szuper-admin felhasználó
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@previse.hu'],
            [
                'organization_id' => $platform->id,
                'name' => 'Super Admin',
                'password' => 'Admin123!',
                'role_id' => $adminRole->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Felhasználó beállítások
        $superAdmin->getOrCreateSettings();
    }
}
