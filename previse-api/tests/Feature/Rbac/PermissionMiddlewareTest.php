<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::create([
        'type' => 'subscriber', 'name' => 'Test Kft.', 'slug' => 'test-kft', 'is_active' => true,
    ]);

    // Engedélyek seedelése
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Admin szerepkör (minden engedély)
    $this->adminRole = Role::create([
        'organization_id' => $this->org->id, 'name' => 'Admin', 'slug' => 'admin', 'is_system' => true,
    ]);
    $this->adminRole->permissions()->sync(Permission::pluck('id'));

    // Korlátozott szerepkör (csak tickets.read)
    $this->limitedRole = Role::create([
        'organization_id' => $this->org->id, 'name' => 'Korlátozott', 'slug' => 'limited',
    ]);
    $this->limitedRole->permissions()->sync(
        Permission::where('module', 'tickets')->where('action', 'read')->pluck('id')
    );

    $this->adminUser = User::create([
        'organization_id' => $this->org->id, 'name' => 'Admin', 'email' => 'admin@test.hu',
        'password' => 'Pass123!', 'role_id' => $this->adminRole->id,
        'is_active' => true, 'email_verified_at' => now(),
    ]);

    $this->limitedUser = User::create([
        'organization_id' => $this->org->id, 'name' => 'Korlátozott', 'email' => 'limited@test.hu',
        'password' => 'Pass123!', 'role_id' => $this->limitedRole->id,
        'is_active' => true, 'email_verified_at' => now(),
    ]);
});

test('admin felhasználó hozzáfér a felhasználók listához', function () {
    Sanctum::actingAs($this->adminUser);

    $response = $this->getJson('/api/v1/users');
    $response->assertOk();
});

test('korlátozott felhasználó nem fér hozzá a felhasználók listához', function () {
    Sanctum::actingAs($this->limitedUser);

    $response = $this->getJson('/api/v1/users');
    $response->assertForbidden();
});

test('admin lát más szervezet felhasználóit: NEM (multi-tenant)', function () {
    $otherOrg = Organization::create([
        'type' => 'subscriber', 'name' => 'Másik Kft.', 'slug' => 'masik-kft', 'is_active' => true,
    ]);
    $otherRole = Role::create([
        'organization_id' => $otherOrg->id, 'name' => 'Admin', 'slug' => 'admin',
    ]);
    User::create([
        'organization_id' => $otherOrg->id, 'name' => 'Másik User', 'email' => 'other@masik.hu',
        'password' => 'Pass123!', 'role_id' => $otherRole->id,
        'is_active' => true, 'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($this->adminUser);

    $response = $this->getJson('/api/v1/users');
    $response->assertOk();

    // Csak a saját szervezet felhasználóit adja vissza
    $emails = collect($response->json('data'))->pluck('email')->toArray();
    expect($emails)->toContain('admin@test.hu');
    expect($emails)->toContain('limited@test.hu');
    expect($emails)->not->toContain('other@masik.hu');
});

test('szuper-admin mindent megtehet', function () {
    $platformOrg = Organization::create([
        'type' => 'platform', 'name' => 'Platform', 'slug' => 'platform', 'is_active' => true,
    ]);
    $platformRole = Role::create([
        'organization_id' => $platformOrg->id, 'name' => 'Admin', 'slug' => 'admin', 'is_system' => true,
    ]);
    $superAdmin = User::create([
        'organization_id' => $platformOrg->id, 'name' => 'Super', 'email' => 'super@previse.hu',
        'password' => 'Pass123!', 'role_id' => $platformRole->id,
        'is_active' => true, 'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($superAdmin);

    // Szuper-admin hozzáfér a roles endpoint-hoz is (permission nélkül)
    $response = $this->getJson('/api/v1/permissions');
    $response->assertOk();
});
