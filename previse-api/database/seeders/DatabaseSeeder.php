<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,       // 1. Engedélyek (globális)
            PlatformSeeder::class,         // 2. Platform szervezet + szuper-admin
            DemoSubscriberSeeder::class,   // 3. Demo előfizető (fejlesztéshez)
        ]);
    }
}
