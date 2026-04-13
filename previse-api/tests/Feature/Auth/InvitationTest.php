<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        'name' => 'Felhasználó',
        'slug' => 'user',
    ]);

    $this->invitedUser = User::create([
        'organization_id' => $this->org->id,
        'name' => 'Meghívott Felhasználó',
        'email' => 'meghivott@test.hu',
        'password' => 'temporary',
        'role_id' => $this->role->id,
        'is_active' => false,
        'email_verified_at' => null,
        'invitation_token' => 'valid-token-123',
        'invitation_sent_at' => now(),
    ]);
});

test('meghívó információk lekérhetők érvényes tokennel', function () {
    $response = $this->getJson('/api/v1/auth/invitation/valid-token-123');

    $response->assertOk()
        ->assertJsonPath('data.name', 'Meghívott Felhasználó')
        ->assertJsonPath('data.email', 'meghivott@test.hu')
        ->assertJsonPath('data.organization', 'Test Kft.')
        ->assertJsonPath('data.expired', false);
});

test('érvénytelen tokennel 404-et kapunk', function () {
    $response = $this->getJson('/api/v1/auth/invitation/invalid-token');

    $response->assertNotFound();
});

test('meghívó sikeresen elfogadható', function () {
    $response = $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => 'valid-token-123',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertOk();

    $this->invitedUser->refresh();
    expect($this->invitedUser->email_verified_at)->not->toBeNull();
    expect($this->invitedUser->invitation_token)->toBeNull();
    expect($this->invitedUser->is_active)->toBeTrue();
});

test('elfogadás után be tud jelentkezni', function () {
    // Elfogadás
    $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => 'valid-token-123',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    // Bejelentkezés az új jelszóval (token móddal a teszt kedvéért)
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'meghivott@test.hu',
        'password' => 'NewPassword123!',
        'device_name' => 'Test Device',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.email', 'meghivott@test.hu');
});

test('érvénytelen tokennel nem lehet elfogadni', function () {
    $response = $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => 'invalid-token',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

test('lejárt meghívó nem fogadható el (7 nap után)', function () {
    $this->invitedUser->update([
        'invitation_sent_at' => now()->subDays(8),
    ]);

    $response = $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => 'valid-token-123',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

test('jelszó megerősítés kötelező', function () {
    $response = $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => 'valid-token-123',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'MasJelszo!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('már elfogadott meghívó nem használható újra', function () {
    // Első elfogadás
    $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => 'valid-token-123',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]);

    // Második próbálkozás
    $response = $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => 'valid-token-123',
        'password' => 'AnotherPass123!',
        'password_confirmation' => 'AnotherPass123!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});
