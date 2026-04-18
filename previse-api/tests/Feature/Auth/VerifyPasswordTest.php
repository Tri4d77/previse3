<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::create([
        'type' => 'subscriber', 'name' => 'Test', 'slug' => 'test', 'is_active' => true,
    ]);
    $this->role = Role::create([
        'organization_id' => $this->org->id, 'name' => 'Admin', 'slug' => 'admin',
    ]);
    $this->user = User::create([
        'organization_id' => $this->org->id, 'name' => 'Teszt', 'email' => 'test@test.hu',
        'password' => 'Password123!', 'role_id' => $this->role->id,
        'is_active' => true, 'email_verified_at' => now(),
    ]);
});

test('bejelentkezett felhasználó jelszó helyesen megerősíthető', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/auth/verify-password', [
        'password' => 'Password123!',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'OK');
});

test('helytelen jelszó 422-t ad vissza', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/auth/verify-password', [
        'password' => 'rosszjelszo',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('nem bejelentkezett felhasználó 401-et kap', function () {
    $response = $this->postJson('/api/v1/auth/verify-password', [
        'password' => 'Password123!',
    ]);

    $response->assertUnauthorized();
});

test('üres jelszó validációs hibát ad', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/auth/verify-password', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});
