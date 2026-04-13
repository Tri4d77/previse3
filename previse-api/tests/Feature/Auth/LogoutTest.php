<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::create([
        'type' => 'subscriber',
        'name' => 'Test Kft.',
        'slug' => 'test-kft',
        'is_active' => true,
    ]);

    $this->role = Role::create([
        'organization_id' => $this->org->id,
        'name' => 'Admin',
        'slug' => 'admin',
    ]);

    $this->user = User::create([
        'organization_id' => $this->org->id,
        'name' => 'Teszt Felhasználó',
        'email' => 'test@test.hu',
        'password' => 'Password123!',
        'role_id' => $this->role->id,
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
});

test('bejelentkezett felhasználó ki tud jelentkezni', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJsonPath('message', 'Sikeres kijelentkezés.');
});

test('bejelentkezett felhasználó minden eszközről ki tud jelentkezni', function () {
    // Több token létrehozása
    $this->user->createToken('Device 1');
    $this->user->createToken('Device 2');

    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/auth/logout-all');

    $response->assertOk();

    // Minden token törölve
    expect($this->user->tokens()->count())->toBe(0);
});

test('nem bejelentkezett felhasználó 401-et kap', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertUnauthorized();
});

test('bejelentkezett felhasználó lekérheti a saját adatait', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/v1/auth/user');

    $response->assertOk()
        ->assertJsonPath('data.email', 'test@test.hu')
        ->assertJsonPath('data.name', 'Teszt Felhasználó')
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'email', 'role', 'organization', 'permissions', 'settings',
            ],
        ]);
});
