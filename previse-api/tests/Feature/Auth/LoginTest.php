<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // A seederek lefutnak a RefreshDatabase miatt nem, de a DatabaseSeeder hívást nem automatikusan
    // Manuálisan hozzuk létre az alapokat:
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Platform szervezet + szuper-admin létrehozása
    $this->platform = Organization::create([
        'type' => 'platform',
        'name' => 'Previse Platform',
        'slug' => 'previse-platform',
        'is_active' => true,
    ]);

    $this->adminRole = Role::create([
        'organization_id' => $this->platform->id,
        'name' => 'Adminisztrátor',
        'slug' => 'admin',
        'is_system' => true,
    ]);
    $this->adminRole->permissions()->sync(\App\Models\Permission::pluck('id'));

    $this->superAdmin = User::create([
        'name' => 'Szuper Admin',
        'email' => 'admin@previse.hu',
        'password' => 'Admin123!',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    $this->superAdmin->getOrCreateSettings();

    Membership::create([
        'user_id' => $this->superAdmin->id,
        'organization_id' => $this->platform->id,
        'role_id' => $this->adminRole->id,
        'is_active' => true,
        'joined_at' => now(),
    ]);
});

test('szuper-admin be tud jelentkezni egyetlen tagsággal', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@previse.hu',
        'password' => 'Admin123!',
        'device_name' => 'Test Device',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
                'current_membership' => [
                    'id',
                    'organization' => ['id', 'name', 'type'],
                    'role' => ['id', 'name', 'slug'],
                    'permissions',
                ],
                'token',
            ],
        ])
        ->assertJsonPath('data.user.email', 'admin@previse.hu')
        ->assertJsonPath('data.current_membership.organization.type', 'platform')
        ->assertJsonPath('data.current_membership.role.slug', 'admin');
});

test('rossz jelszóval nem lehet bejelentkezni', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@previse.hu',
        'password' => 'rosszjelszo',
        'device_name' => 'Test',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('nem létező email-lel nem lehet bejelentkezni', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nincs@ilyen.hu',
        'password' => 'Admin123!',
        'device_name' => 'Test',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('inaktív user nem tud bejelentkezni', function () {
    $this->superAdmin->update(['is_active' => false]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@previse.hu',
        'password' => 'Admin123!',
        'device_name' => 'Test',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('aktív tagság nélkül nem lehet bejelentkezni', function () {
    // Deaktiváljuk a tagságot
    $this->superAdmin->memberships()->update(['is_active' => false]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@previse.hu',
        'password' => 'Admin123!',
        'device_name' => 'Test',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('több aktív tagság esetén szervezet-választó szükséges', function () {
    // Hozzunk létre egy második szervezetet + tagságot
    $otherOrg = Organization::create([
        'type' => 'subscriber',
        'name' => 'XY Kft.',
        'slug' => 'xy-kft',
        'is_active' => true,
        'parent_id' => $this->platform->id,
    ]);
    $otherRole = Role::create([
        'organization_id' => $otherOrg->id,
        'name' => 'Admin',
        'slug' => 'admin',
    ]);
    Membership::create([
        'user_id' => $this->superAdmin->id,
        'organization_id' => $otherOrg->id,
        'role_id' => $otherRole->id,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@previse.hu',
        'password' => 'Admin123!',
        'device_name' => 'Test',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'requires_organization_selection',
            'selection_token',
            'memberships',
        ])
        ->assertJsonPath('requires_organization_selection', true);

    expect($response->json('memberships'))->toHaveCount(2);
});

test('default_organization_id esetén oda lép be automatikusan', function () {
    // Hozzunk létre másodikat
    $otherOrg = Organization::create([
        'type' => 'subscriber',
        'name' => 'XY Kft.',
        'slug' => 'xy-kft',
        'is_active' => true,
        'parent_id' => $this->platform->id,
    ]);
    $otherRole = Role::create([
        'organization_id' => $otherOrg->id,
        'name' => 'Admin',
        'slug' => 'admin',
    ]);
    Membership::create([
        'user_id' => $this->superAdmin->id,
        'organization_id' => $otherOrg->id,
        'role_id' => $otherRole->id,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    // Beállítjuk a default szervezetet
    $this->superAdmin->getOrCreateSettings()->update(['default_organization_id' => $otherOrg->id]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@previse.hu',
        'password' => 'Admin123!',
        'device_name' => 'Test',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.current_membership.organization.id', $otherOrg->id);
});
