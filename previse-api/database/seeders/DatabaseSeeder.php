<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,  // 1. Engedélyek (globális)
            PlatformSeeder::class,    // 2. Platform + szuper-admin
        ]);
    }
}
