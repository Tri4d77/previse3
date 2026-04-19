<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\TwoFactorService;
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
        'name' => 'Teszt',
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
    ]);
    $this->token = $login->json('data.token');
});

function twoFactorService(): TwoFactorService
{
    return app(TwoFactorService::class);
}

function authHeadersTwo(string $token): array
{
    return ['Authorization' => "Bearer {$token}"];
}

// ============ ENABLE / CONFIRM FLOW ============

test('2fa/enable: secret és QR kód SVG generálása', function () {
    $response = $this->withHeaders(authHeadersTwo($this->token))
        ->postJson('/api/v1/profile/2fa/enable');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['secret', 'otpauth_url', 'qr_code_svg']]);

    $this->user->refresh();
    expect($this->user->two_factor_secret)->not->toBeNull();
    expect($this->user->two_factor_confirmed_at)->toBeNull();

    // QR kód SVG-ként visszaadva
    expect($response->json('data.qr_code_svg'))->toContain('<svg');
});

test('2fa/enable: már aktív 2FA esetén 422', function () {
    $secret = twoFactorService()->generateSecret();
    $this->user->update([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ]);

    $this->withHeaders(authHeadersTwo($this->token))
        ->postJson('/api/v1/profile/2fa/enable')
        ->assertStatus(422);
});

test('2fa/confirm: helyes TOTP kód aktiválja + recovery kódokat generál', function () {
    // Enable először
    $this->withHeaders(authHeadersTwo($this->token))
        ->postJson('/api/v1/profile/2fa/enable')
        ->assertOk();

    $this->user->refresh();
    $code = twoFactorService()->generateCurrentCode($this->user->two_factor_secret);

    $response = $this->withHeaders(authHeadersTwo($this->token))
        ->postJson('/api/v1/profile/2fa/confirm', ['code' => $code]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['recovery_codes']])
        ->assertJsonCount(8, 'data.recovery_codes');

    $this->user->refresh();
    expect($this->user->two_factor_confirmed_at)->not->toBeNull();
    expect($this->user->two_factor_recovery_codes)->toHaveCount(8);
});

test('2fa/confirm: rossz kóddal hibát dob', function () {
    $this->withHeaders(authHeadersTwo($this->token))
        ->postJson('/api/v1/profile/2fa/enable');

    $this->withHeaders(authHeadersTwo($this->token))
        ->postJson('/api/v1/profile/2fa/confirm', ['code' => '000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');

    $this->user->refresh();
    expect($this->user->two_factor_confirmed_at)->toBeNull();
});

// ============ DISABLE ============

test('2fa/disable: helyes jelszóval kikapcsol', function () {
    $this->user->update([
        'two_factor_secret' => twoFactorService()->generateSecret(),
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => twoFactorService()->generateRecoveryCodes(),
    ]);

    $this->withHeaders(authHeadersTwo($this->token))
        ->postJson('/api/v1/profile/2fa/disable', ['password' => 'OldPass123!'])
        ->assertOk();

    $this->user->refresh();
    expect($this->user->two_factor_secret)->toBeNull();
    expect($this->user->two_factor_confirmed_at)->toBeNull();
    expect($this->user->two_factor_recovery_codes)->toBeNull();
});

test('2fa/disable: rossz jelszóval 422', function () {
    $this->user->update([
        'two_factor_secret' => twoFactorService()->generateSecret(),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->withHeaders(authHeadersTwo($this->token))
        ->postJson('/api/v1/profile/2fa/disable', ['password' => 'RosszJelszo!'])
        ->assertStatus(422);

    $this->user->refresh();
    expect($this->user->two_factor_confirmed_at)->not->toBeNull();
});

// ============ RECOVERY CODES ============

test('2fa/recovery-codes regenerate új 8 kódot ad', function () {
    $this->user->update([
        'two_factor_secret' => twoFactorService()->generateSecret(),
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => ['RÉGI1', 'RÉGI2'],
    ]);

    $response = $this->withHeaders(authHeadersTwo($this->token))
        ->postJson('/api/v1/profile/2fa/recovery-codes/regenerate');

    $response->assertOk()->assertJsonCount(8, 'data');

    $this->user->refresh();
    expect($this->user->two_factor_recovery_codes)->toHaveCount(8);
    expect($this->user->two_factor_recovery_codes)->not->toContain('RÉGI1');
});

// ============ LOGIN + 2FA CHALLENGE FLOW ============

test('2FA aktív user login után requires_two_factor választ kap', function () {
    $this->user->update([
        'two_factor_secret' => twoFactorService()->generateSecret(),
        'two_factor_confirmed_at' => now(),
    ]);

    // Régi token törölve (ne zavarjon)
    $this->user->tokens()->delete();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'teszt@xy.hu',
        'password' => 'OldPass123!',
    ]);

    $response->assertOk()
        ->assertJsonPath('requires_two_factor', true)
        ->assertJsonStructure(['challenge_token']);

    // Még nincs valódi login token
    expect($response->json('data.token'))->toBeNull();
});

test('2FA challenge: helyes TOTP kóddal sikeres belépés', function () {
    $secret = twoFactorService()->generateSecret();
    $this->user->update([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ]);
    $this->user->tokens()->delete();

    // Login → challenge token
    $challengeToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'teszt@xy.hu',
        'password' => 'OldPass123!',
    ])->json('challenge_token');

    // TOTP kód
    $code = twoFactorService()->generateCurrentCode($secret);

    $response = $this->withHeaders(authHeadersTwo($challengeToken))
        ->postJson('/api/v1/auth/2fa/challenge', ['code' => $code]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['user', 'token']]);
    expect($response->json('data.token'))->toBeString();
});

test('2FA challenge: rossz TOTP kóddal hiba', function () {
    $this->user->update([
        'two_factor_secret' => twoFactorService()->generateSecret(),
        'two_factor_confirmed_at' => now(),
    ]);
    $this->user->tokens()->delete();

    $challengeToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'teszt@xy.hu',
        'password' => 'OldPass123!',
    ])->json('challenge_token');

    $this->withHeaders(authHeadersTwo($challengeToken))
        ->postJson('/api/v1/auth/2fa/challenge', ['code' => '000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

test('2FA challenge: recovery kóddal belép, majd a kód nem használható mégegyszer', function () {
    $secret = twoFactorService()->generateSecret();
    $recoveryCodes = twoFactorService()->generateRecoveryCodes();
    $this->user->update([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => $recoveryCodes,
    ]);
    $this->user->tokens()->delete();

    // Login → challenge token
    $challengeToken1 = $this->postJson('/api/v1/auth/login', [
        'email' => 'teszt@xy.hu',
        'password' => 'OldPass123!',
    ])->json('challenge_token');

    $usedCode = $recoveryCodes[0];

    // Első használat: sikeres
    $this->withHeaders(authHeadersTwo($challengeToken1))
        ->postJson('/api/v1/auth/2fa/challenge', ['recovery_code' => $usedCode])
        ->assertOk();

    $this->user->refresh();
    expect($this->user->two_factor_recovery_codes)->toHaveCount(7);
    expect($this->user->two_factor_recovery_codes)->not->toContain($usedCode);

    // Új challenge, ugyanazzal a kóddal → elutasítva
    $this->user->tokens()->delete();
    $challengeToken2 = $this->postJson('/api/v1/auth/login', [
        'email' => 'teszt@xy.hu',
        'password' => 'OldPass123!',
    ])->json('challenge_token');

    $this->withHeaders(authHeadersTwo($challengeToken2))
        ->postJson('/api/v1/auth/2fa/challenge', ['recovery_code' => $usedCode])
        ->assertStatus(422);
});

test('2FA challenge endpoint normál tokennel nem érhető el (forbidden)', function () {
    // 2FA bekapcsolva, hogy a 403 (ability) ág triggereljen, ne a 422 (no 2fa)
    $this->user->update([
        'two_factor_secret' => twoFactorService()->generateSecret(),
        'two_factor_confirmed_at' => now(),
    ]);

    // A $this->token egy normál login token, nem challenge token
    $this->withHeaders(authHeadersTwo($this->token))
        ->postJson('/api/v1/auth/2fa/challenge', ['code' => '123456'])
        ->assertStatus(403);
});

test('nem-2FA user login egyenesen tokent kap', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'teszt@xy.hu',
        'password' => 'OldPass123!',
    ]);

    $response->assertOk();
    expect($response->json('requires_two_factor'))->toBeNull();
    expect($response->json('data.token'))->toBeString();
});
