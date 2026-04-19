<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Platform + szuper-admin
    $this->platform = Organization::create([
        'type' => 'platform', 'name' => 'Platform', 'slug' => 'platform', 'is_active' => true,
    ]);

    $this->platformRole = Role::create([
        'organization_id' => $this->platform->id,
        'name' => 'Admin', 'slug' => 'admin', 'is_system' => true,
    ]);
    $this->platformRole->permissions()->sync(\App\Models\Permission::pluck('id'));

    $this->superAdmin = User::create([
        'name' => 'Szuper Admin',
        'email' => 'admin@previse.hu',
        'password' => 'Admin123!',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    Membership::create([
        'user_id' => $this->superAdmin->id,
        'organization_id' => $this->platform->id,
        'role_id' => $this->platformRole->id,
        'is_active' => true,
        'joined_at' => now(),
    ]);
});

function loginAsSuperAdminOrg(): string
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => 'admin@previse.hu',
        'password' => 'Admin123!',
        'device_name' => 'Test',
    ]);
    return $response->json('data.token');
}

test('szuper-admin tud új subscriber szervezetet létrehozni', function () {
    $token = loginAsSuperAdminOrg();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/organizations', [
            'name' => 'Új Kft.',
            'type' => 'subscriber',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'subscriber')
        ->assertJsonPath('data.name', 'Új Kft.')
        ->assertJsonPath('data.parent_id', $this->platform->id);

    $org = Organization::where('name', 'Új Kft.')->first();
    expect($org)->not->toBeNull();

    // Alap szerepkörök automatikusan létrejöttek (5 db)
    expect($org->roles()->count())->toBe(5);
    expect($org->roles()->pluck('slug')->toArray())->toContain('admin', 'dispatcher', 'user', 'recorder', 'maintainer');
});

test('szuper-admin tud új client szervezetet létrehozni subscriber alá', function () {
    // Előbb létrehozunk egy subscribert
    $subscriber = Organization::create([
        'type' => 'subscriber', 'name' => 'XY Kft.', 'slug' => 'xy-kft',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);

    $token = loginAsSuperAdminOrg();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/organizations', [
            'name' => 'ABC Plaza',
            'type' => 'client',
            'parent_id' => $subscriber->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.type', 'client')
        ->assertJsonPath('data.parent_id', $subscriber->id);
});

test('client szervezet nem hozható létre subscriber nélkül', function () {
    $token = loginAsSuperAdminOrg();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/organizations', [
            'name' => 'Téves',
            'type' => 'client',
            // parent_id hiányzik
        ]);

    $response->assertUnprocessable();
    expect($response->json('errors.parent_id'))->not->toBeNull();
});

test('client szervezet szülője csak subscriber lehet', function () {
    $token = loginAsSuperAdminOrg();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/organizations', [
            'name' => 'Téves',
            'type' => 'client',
            'parent_id' => $this->platform->id, // Platform nem lehet
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('nem szuper-admin nem hozhat létre szervezetet', function () {
    // Előbb hozzunk létre egy subscribert + admin usert
    $subscriber = Organization::create([
        'type' => 'subscriber', 'name' => 'XY', 'slug' => 'xy', 'is_active' => true, 'parent_id' => $this->platform->id,
    ]);
    $role = Role::create([
        'organization_id' => $subscriber->id, 'name' => 'Admin', 'slug' => 'admin',
    ]);
    $user = User::create([
        'name' => 'Org Admin', 'email' => 'orgadmin@xy.hu', 'password' => 'Pass123!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $user->id, 'organization_id' => $subscriber->id, 'role_id' => $role->id,
        'is_active' => true, 'joined_at' => now(),
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'orgadmin@xy.hu', 'password' => 'Pass123!', 'device_name' => 'Test',
    ]);
    $token = $login->json('data.token');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/organizations', [
            'name' => 'Próba',
            'type' => 'subscriber',
        ]);

    $response->assertForbidden();
});

test('szervezet adatai módosíthatók (szuper-adminnak)', function () {
    $org = Organization::create([
        'type' => 'subscriber', 'name' => 'Régi', 'slug' => 'regi',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);

    $token = loginAsSuperAdminOrg();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/organizations/{$org->id}", [
            'name' => 'Új név',
            'email' => 'info@uj.hu',
        ]);

    $response->assertOk();
    expect($org->fresh()->name)->toBe('Új név');
    expect($org->fresh()->email)->toBe('info@uj.hu');
});

test('szervezet státusza módosítható (active → inactive)', function () {
    $org = Organization::create([
        'type' => 'subscriber', 'name' => 'Teszt', 'slug' => 'teszt',
        'parent_id' => $this->platform->id, 'status' => 'active', 'is_active' => true,
    ]);

    $token = loginAsSuperAdminOrg();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/organizations/{$org->id}/status", ['status' => 'inactive']);

    $response->assertOk();
    $fresh = $org->fresh();
    expect($fresh->status)->toBe('inactive');
    expect($fresh->is_active)->toBeFalse();
});

test('szervezet megszüntethető (terminated) + terminated_at kitöltődik', function () {
    $org = Organization::create([
        'type' => 'subscriber', 'name' => 'Teszt', 'slug' => 'teszt',
        'parent_id' => $this->platform->id, 'status' => 'active', 'is_active' => true,
    ]);

    $token = loginAsSuperAdminOrg();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/organizations/{$org->id}/status", ['status' => 'terminated']);

    $response->assertOk();
    $fresh = $org->fresh();
    expect($fresh->status)->toBe('terminated');
    expect($fresh->is_active)->toBeFalse();
    expect($fresh->terminated_at)->not->toBeNull();
});

test('szervezet visszaaktiválható terminated-ből', function () {
    $org = Organization::create([
        'type' => 'subscriber', 'name' => 'Teszt', 'slug' => 'teszt',
        'parent_id' => $this->platform->id, 'status' => 'terminated',
        'is_active' => false, 'terminated_at' => now(),
    ]);

    $token = loginAsSuperAdminOrg();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/organizations/{$org->id}/status", ['status' => 'active']);

    $response->assertOk();
    $fresh = $org->fresh();
    expect($fresh->status)->toBe('active');
    expect($fresh->is_active)->toBeTrue();
    expect($fresh->terminated_at)->toBeNull();
});

test('Platform szervezet státusza nem módosítható', function () {
    $token = loginAsSuperAdminOrg();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/organizations/{$this->platform->id}/status", ['status' => 'inactive']);

    $response->assertUnprocessable();
});

test('subscriber admin tud client szervezetet létrehozni saját szervezete alá', function () {
    // Subscriber + admin
    $subscriber = Organization::create([
        'type' => 'subscriber', 'name' => 'Sub', 'slug' => 'sub',
        'parent_id' => $this->platform->id, 'status' => 'active', 'is_active' => true,
    ]);
    $role = \App\Models\Role::create([
        'organization_id' => $subscriber->id, 'name' => 'Admin', 'slug' => 'admin',
    ]);
    $role->permissions()->sync(\App\Models\Permission::pluck('id'));
    $subAdmin = User::create([
        'name' => 'Sub Admin', 'email' => 'subadmin@sub.hu', 'password' => 'Pass123!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $subAdmin->id, 'organization_id' => $subscriber->id, 'role_id' => $role->id,
        'is_active' => true, 'joined_at' => now(),
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'subadmin@sub.hu', 'password' => 'Pass123!', 'device_name' => 'T',
    ]);
    $token = $login->json('data.token');

    // Client létrehozás
    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/organizations', [
            'name' => 'Ügyfél',
            'type' => 'client',
            'parent_id' => $subscriber->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.parent_id', $subscriber->id)
        ->assertJsonPath('data.type', 'client');
});

test('organizations-tree fa-struktúrát ad vissza', function () {
    $sub = Organization::create([
        'type' => 'subscriber', 'name' => 'Sub', 'slug' => 'sub',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    Organization::create([
        'type' => 'client', 'name' => 'Client', 'slug' => 'client',
        'parent_id' => $sub->id, 'is_active' => true,
    ]);

    $token = loginAsSuperAdminOrg();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/organizations-tree');

    $response->assertOk();

    $tree = $response->json('data');
    expect($tree)->toHaveCount(1); // csak a Platform a gyökér
    expect($tree[0]['type'])->toBe('platform');
    expect($tree[0]['children'])->toHaveCount(1); // Sub
    expect($tree[0]['children'][0]['children'])->toHaveCount(1); // Client
});
