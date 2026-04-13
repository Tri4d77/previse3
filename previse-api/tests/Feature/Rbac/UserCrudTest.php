<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->org = Organization::create([
        'type' => 'subscriber', 'name' => 'Test Kft.', 'slug' => 'test-kft', 'is_active' => true,
    ]);

    $this->adminRole = Role::create([
        'organization_id' => $this->org->id, 'name' => 'Admin', 'slug' => 'admin', 'is_system' => true,
    ]);
    $this->adminRole->permissions()->sync(Permission::pluck('id'));

    $this->userRole = Role::create([
        'organization_id' => $this->org->id, 'name' => 'Felhasználó', 'slug' => 'user', 'is_system' => true,
    ]);

    $this->admin = User::create([
        'organization_id' => $this->org->id, 'name' => 'Admin User', 'email' => 'admin@test.hu',
        'password' => 'Pass123!', 'role_id' => $this->adminRole->id,
        'is_active' => true, 'email_verified_at' => now(),
    ]);
});

test('admin meg tud hívni új felhasználót', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/users', [
        'name' => 'Új Felhasználó',
        'email' => 'uj@test.hu',
        'role_id' => $this->userRole->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Új Felhasználó')
        ->assertJsonPath('data.email', 'uj@test.hu')
        ->assertJsonPath('data.is_active', false); // Meghívó elfogadásig inaktív

    // Meghívó token generálódott
    $newUser = User::where('email', 'uj@test.hu')->first();
    expect($newUser->invitation_token)->not->toBeNull();
    expect($newUser->invitation_sent_at)->not->toBeNull();
});

test('meghívás: duplikált email-lel nem lehet', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/users', [
        'name' => 'Duplikált',
        'email' => 'admin@test.hu', // Már létezik
        'role_id' => $this->userRole->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('meghívás: más szervezet szerepkörével nem lehet', function () {
    $otherOrg = Organization::create([
        'type' => 'subscriber', 'name' => 'Másik', 'slug' => 'masik', 'is_active' => true,
    ]);
    $otherRole = Role::create([
        'organization_id' => $otherOrg->id, 'name' => 'Admin', 'slug' => 'admin',
    ]);

    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/users', [
        'name' => 'Teszt',
        'email' => 'teszt@test.hu',
        'role_id' => $otherRole->id, // Más szervezet szerepköre
    ]);

    $response->assertUnprocessable();
});

test('admin módosíthat felhasználót', function () {
    $user = User::create([
        'organization_id' => $this->org->id, 'name' => 'Régi Név', 'email' => 'user@test.hu',
        'password' => 'Pass123!', 'role_id' => $this->userRole->id,
        'is_active' => true, 'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($this->admin);

    $response = $this->putJson("/api/v1/users/{$user->id}", [
        'name' => 'Új Név',
        'phone' => '+36 30 123 4567',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Új Név');
});

test('admin aktiválhat és deaktiválhat felhasználót', function () {
    $user = User::create([
        'organization_id' => $this->org->id, 'name' => 'Teszt', 'email' => 'user@test.hu',
        'password' => 'Pass123!', 'role_id' => $this->userRole->id,
        'is_active' => true, 'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($this->admin);

    // Deaktiválás
    $response = $this->patchJson("/api/v1/users/{$user->id}/toggle-active");
    $response->assertOk()->assertJsonPath('data.is_active', false);

    // Visszaaktiválás
    $response = $this->patchJson("/api/v1/users/{$user->id}/toggle-active");
    $response->assertOk()->assertJsonPath('data.is_active', true);
});

test('admin nem deaktiválhatja saját magát', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->patchJson("/api/v1/users/{$this->admin->id}/toggle-active");
    $response->assertForbidden();
});

test('admin törölhet felhasználót (soft delete)', function () {
    $user = User::create([
        'organization_id' => $this->org->id, 'name' => 'Törlendő', 'email' => 'del@test.hu',
        'password' => 'Pass123!', 'role_id' => $this->userRole->id,
        'is_active' => true, 'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/v1/users/{$user->id}");
    $response->assertNoContent();

    // Soft delete - az adatbázisban megvan, de deleted_at nem null
    expect(User::withTrashed()->find($user->id)->deleted_at)->not->toBeNull();
    expect(User::find($user->id))->toBeNull(); // Normál lekérdezésben nem jelenik meg
});

test('admin nem törölheti saját magát', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->deleteJson("/api/v1/users/{$this->admin->id}");
    $response->assertForbidden();
});

test('felhasználó lista szűrhető szerepkör szerint', function () {
    User::create([
        'organization_id' => $this->org->id, 'name' => 'Sima User', 'email' => 'user@test.hu',
        'password' => 'Pass123!', 'role_id' => $this->userRole->id,
        'is_active' => true, 'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/v1/users?role=user');
    $response->assertOk();

    $users = collect($response->json('data'));
    expect($users)->toHaveCount(1);
    expect($users->first()['email'])->toBe('user@test.hu');
});
