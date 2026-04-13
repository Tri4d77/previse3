<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Platform szervezet + admin szerepkör + felhasználó
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
        'is_system' => true,
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

test('sikeres bejelentkezés érvényes adatokkal (token mód)', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@test.hu',
        'password' => 'Password123!',
        'device_name' => 'Test Device',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'user' => [
                    'id', 'name', 'email', 'initials', 'is_active',
                    'role' => ['id', 'name', 'slug'],
                    'organization' => ['id', 'name', 'type'],
                    'permissions',
                    'settings',
                ],
                'token',
            ],
        ])
        ->assertJsonPath('data.user.email', 'test@test.hu')
        ->assertJsonPath('data.user.name', 'Teszt Felhasználó')
        ->assertJsonPath('data.user.role.slug', 'admin')
        ->assertJsonPath('data.user.organization.name', 'Test Kft.');
});

test('mobil bejelentkezés tokent ad vissza', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@test.hu',
        'password' => 'Password123!',
        'device_name' => 'iPhone 15',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['user', 'token'],
        ]);

    expect($response->json('data.token'))->not->toBeNull();
});

test('hibás jelszóval nem lehet bejelentkezni', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@test.hu',
        'password' => 'rosszjelszo',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('nem létező email-lel nem lehet bejelentkezni', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nincs@ilyen.hu',
        'password' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('inaktív felhasználó nem tud bejelentkezni', function () {
    $this->user->update(['is_active' => false]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@test.hu',
        'password' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('inaktív szervezet felhasználója nem tud bejelentkezni', function () {
    $this->org->update(['is_active' => false]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@test.hu',
        'password' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('nem aktivált fiókkal nem lehet bejelentkezni', function () {
    $this->user->update([
        'email_verified_at' => null,
        'invitation_token' => 'abc123',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@test.hu',
        'password' => 'Password123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('bejelentkezés frissíti az utolsó belépés időpontját', function () {
    expect($this->user->last_login_at)->toBeNull();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'test@test.hu',
        'password' => 'Password123!',
    ]);

    $this->user->refresh();
    expect($this->user->last_login_at)->not->toBeNull();
    expect($this->user->last_login_ip)->not->toBeNull();
});

test('rate limiting: 5 hibás próbálkozás után blokkolva', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@test.hu',
            'password' => 'rosszjelszo',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@test.hu',
        'password' => 'Password123!', // Most jó jelszó, de blokkolva van
    ]);

    $response->assertUnprocessable();
});

test('validáció: üres email és jelszó', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});
