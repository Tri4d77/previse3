<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->platform = Organization::create([
        'type' => 'platform', 'name' => 'P', 'slug' => 'p', 'is_active' => true,
    ]);
    $this->org = Organization::create([
        'type' => 'subscriber', 'name' => 'XY', 'slug' => 'xy',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    $this->role = Role::create([
        'organization_id' => $this->org->id,
        'name' => 'Admin', 'slug' => 'admin', 'is_system' => true,
    ]);
    $this->role->permissions()->sync(Permission::pluck('id'));

    $this->user = User::create([
        'name' => 'Teszt User',
        'email' => 'teszt@xy.hu',
        'password' => 'OldPass123!',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'role_id' => $this->role->id,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'teszt@xy.hu',
        'password' => 'OldPass123!',
        'device_name' => 'Desktop Chrome',
    ]);
    $this->token = $login->json('data.token');
});

function authHeaders(string $token): array
{
    return ['Authorization' => "Bearer {$token}"];
}

// ============ PASSWORD CHANGE TESZTEK ============

test('jelszó módosítás sikeres helyes jelenlegi jelszóval', function () {
    $response = $this->withHeaders(authHeaders($this->token))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'OldPass123!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ]);

    $response->assertOk();
    $this->user->refresh();

    expect(\Illuminate\Support\Facades\Hash::check('NewPass456!', $this->user->password))->toBeTrue();
});

test('jelszó módosítás hibás jelenlegi jelszóval -> 422', function () {
    $this->withHeaders(authHeaders($this->token))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'RosszJelszo!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('current_password');
});

test('jelszó módosítás: új jelszó nem lehet azonos a régivel', function () {
    $this->withHeaders(authHeaders($this->token))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'OldPass123!',
            'password' => 'OldPass123!',
            'password_confirmation' => 'OldPass123!',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('jelszó módosítás: gyenge jelszó (8 char) elutasítva', function () {
    $this->withHeaders(authHeaders($this->token))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'OldPass123!',
            'password' => 'Gyenge1!',
            'password_confirmation' => 'Gyenge1!',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('jelszó módosítás: megerősítés nélkül elutasítva', function () {
    $this->withHeaders(authHeaders($this->token))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'OldPass123!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'Masik789!',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

test('jelszó módosítás logout_other_devices=true esetén más tokenek revokálva', function () {
    // Hozzunk létre egy második tokent ugyanennek a usernek
    $secondToken = $this->user->createToken('Mobile App');
    $secondTokenId = $secondToken->accessToken->id;

    $this->withHeaders(authHeaders($this->token))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'OldPass123!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
            'logout_other_devices' => true,
        ])
        ->assertOk();

    // Az aktuális token marad, a másik törlődik
    expect($this->user->tokens()->where('id', $secondTokenId)->exists())->toBeFalse();
    expect($this->user->tokens()->count())->toBe(1);
});

// ============ SESSIONS LISTA ============

test('sessions endpoint listázza a user tokenjeit', function () {
    $this->user->createToken('Mobile iPhone');
    $this->user->createToken('Tablet iPad');

    $response = $this->withHeaders(authHeaders($this->token))
        ->getJson('/api/v1/profile/sessions');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'ip_address', 'user_agent', 'last_used_at', 'created_at', 'is_current'],
            ],
        ]);

    // Az aktuális token jelölve
    $current = collect($response->json('data'))->firstWhere('is_current', true);
    expect($current)->not->toBeNull();
    expect($current['name'])->toBe('Desktop Chrome');
});

test('sessions: IP és user agent mentve a login token-en', function () {
    $response = $this->withHeaders(authHeaders($this->token))
        ->getJson('/api/v1/profile/sessions');

    $current = collect($response->json('data'))->firstWhere('is_current', true);
    expect($current['ip_address'])->not->toBeNull();
});

// ============ SESSION REVOKE ============

test('adott session revokálása törli a tokent', function () {
    $second = $this->user->createToken('Mobile')->accessToken;

    $this->withHeaders(authHeaders($this->token))
        ->deleteJson("/api/v1/profile/sessions/{$second->id}")
        ->assertOk();

    expect($this->user->tokens()->where('id', $second->id)->exists())->toBeFalse();
});

test('az aktuális saját session nem revokálható a sessions endpointon', function () {
    $currentId = \App\Models\PersonalAccessToken::where('name', 'Desktop Chrome')->first()->id;

    $this->withHeaders(authHeaders($this->token))
        ->deleteJson("/api/v1/profile/sessions/{$currentId}")
        ->assertStatus(422);

    // Token még él
    expect($this->user->tokens()->where('id', $currentId)->exists())->toBeTrue();
});

test('idegen user tokenje nem revokálható', function () {
    $otherUser = User::create([
        'name' => 'Más', 'email' => 'mas@xy.hu', 'password' => 'X',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    $otherToken = $otherUser->createToken('other')->accessToken;

    $this->withHeaders(authHeaders($this->token))
        ->deleteJson("/api/v1/profile/sessions/{$otherToken->id}")
        ->assertStatus(404);

    // Idegen token érintetlen
    expect($otherUser->tokens()->count())->toBe(1);
});

test('minden más eszköz kijelentkeztetése (destroyOthers)', function () {
    $this->user->createToken('Mobile');
    $this->user->createToken('Tablet');
    expect($this->user->tokens()->count())->toBe(3);

    $response = $this->withHeaders(authHeaders($this->token))
        ->deleteJson('/api/v1/profile/sessions/others');

    $response->assertOk()
        ->assertJsonPath('revoked_count', 2);

    expect($this->user->tokens()->count())->toBe(1);
});
