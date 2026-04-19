<?php

use App\Models\AuthEvent;
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
        'name' => 'Teszt', 'email' => 'teszt@xy.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->user->id, 'organization_id' => $this->org->id,
        'role_id' => $this->role->id, 'is_active' => true, 'joined_at' => now(),
    ]);
});

function authLog(string $email, string $password): string
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email, 'password' => $password,
    ])->json('data.token');
}

function authHeader(string $t): array
{
    return ['Authorization' => "Bearer {$t}"];
}

// ============ LOGIN EVENTS ============

test('sikeres bejelentkezés login_success eseményt naplóz', function () {
    authLog('teszt@xy.hu', 'Pass1234!');

    expect(AuthEvent::where('event', AuthEvent::LOGIN_SUCCESS)->count())->toBe(1);
    $event = AuthEvent::where('event', AuthEvent::LOGIN_SUCCESS)->first();
    expect($event->user_id)->toBe($this->user->id);
    expect($event->metadata['organization_id'])->toBe($this->org->id);
});

test('sikertelen bejelentkezés login_failed eseményt naplóz', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'teszt@xy.hu', 'password' => 'RosszJelszo!',
    ])->assertStatus(422);

    $event = AuthEvent::where('event', AuthEvent::LOGIN_FAILED)->first();
    expect($event)->not->toBeNull();
    expect($event->user_id)->toBe($this->user->id);
    expect($event->email)->toBe('teszt@xy.hu');
});

test('nem létező email login_failed eseményt naplóz user_id nélkül', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'nincsilyen@xy.hu', 'password' => 'AkarMi1234!',
    ])->assertStatus(422);

    $event = AuthEvent::where('event', AuthEvent::LOGIN_FAILED)->first();
    expect($event)->not->toBeNull();
    expect($event->user_id)->toBeNull();
    expect($event->email)->toBe('nincsilyen@xy.hu');
});

test('logout esemény naplózva', function () {
    $token = authLog('teszt@xy.hu', 'Pass1234!');

    $this->withHeaders(authHeader($token))->postJson('/api/v1/auth/logout')->assertOk();

    expect(AuthEvent::where('event', AuthEvent::LOGOUT)->count())->toBe(1);
});

// ============ PASSWORD / EMAIL / 2FA ============

test('jelszó módosítás password_changed eseményt naplóz', function () {
    $token = authLog('teszt@xy.hu', 'Pass1234!');

    $this->withHeaders(authHeader($token))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'Pass1234!',
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ])->assertOk();

    expect(AuthEvent::where('event', AuthEvent::PASSWORD_CHANGED)->count())->toBe(1);
});

test('email change request + confirm + cancel események', function () {
    $token = authLog('teszt@xy.hu', 'Pass1234!');

    // Request
    $this->withHeaders(authHeader($token))
        ->postJson('/api/v1/profile/email/change', [
            'password' => 'Pass1234!', 'new_email' => 'uj@xy.hu',
        ])->assertOk();
    expect(AuthEvent::where('event', AuthEvent::EMAIL_CHANGE_REQUESTED)->exists())->toBeTrue();

    // Cancel
    $this->withHeaders(authHeader($token))
        ->deleteJson('/api/v1/profile/email/pending')->assertOk();
    expect(AuthEvent::where('event', AuthEvent::EMAIL_CHANGE_CANCELLED)->exists())->toBeTrue();

    // Új request + confirm
    $this->withHeaders(authHeader($token))
        ->postJson('/api/v1/profile/email/change', [
            'password' => 'Pass1234!', 'new_email' => 'uj@xy.hu',
        ])->assertOk();
    $this->user->refresh();

    $this->postJson('/api/v1/auth/email/confirm', ['token' => $this->user->email_change_token])->assertOk();
    expect(AuthEvent::where('event', AuthEvent::EMAIL_CHANGE_CONFIRMED)->exists())->toBeTrue();
});

test('2FA események naplózva: enable + disable + regenerate + challenge_failed', function () {
    $token = authLog('teszt@xy.hu', 'Pass1234!');

    // Enable
    $this->withHeaders(authHeader($token))->postJson('/api/v1/profile/2fa/enable')->assertOk();
    $this->user->refresh();
    $code = app(\App\Services\TwoFactorService::class)->generateCurrentCode($this->user->two_factor_secret);

    // Confirm
    $this->withHeaders(authHeader($token))
        ->postJson('/api/v1/profile/2fa/confirm', ['code' => $code])->assertOk();
    expect(AuthEvent::where('event', AuthEvent::TWO_FACTOR_ENABLED)->exists())->toBeTrue();

    // Regenerate recovery codes
    $this->withHeaders(authHeader($token))
        ->postJson('/api/v1/profile/2fa/recovery-codes/regenerate')->assertOk();
    expect(AuthEvent::where('event', AuthEvent::TWO_FACTOR_RECOVERY_REGENERATED)->exists())->toBeTrue();

    // Ellenőrizzük, hogy a 2FA valóban be lett kapcsolva
    $this->user->refresh();
    expect($this->user->hasTwoFactorEnabled())->toBeTrue();

    // 2FA challenge sikertelen
    $this->user->tokens()->delete();
    \Illuminate\Support\Facades\Auth::forgetGuards(); // cache reset két request között
    $loginResp = $this->postJson('/api/v1/auth/login', [
        'email' => 'teszt@xy.hu', 'password' => 'Pass1234!',
    ]);
    $loginResp->assertJsonPath('requires_two_factor', true);
    $challenge = $loginResp->json('challenge_token');
    expect($challenge)->toBeString();

    \Illuminate\Support\Facades\Auth::forgetGuards();
    $this->withHeaders(authHeader($challenge))
        ->postJson('/api/v1/auth/2fa/challenge', ['code' => '000000'])->assertStatus(422);
    expect(AuthEvent::where('event', AuthEvent::TWO_FACTOR_CHALLENGE_FAILED)->exists())->toBeTrue();

    // Disable (Sanctum::actingAs, mert a challenge token nem elég)
    $this->user->refresh();
    \Laravel\Sanctum\Sanctum::actingAs($this->user);
    $this->postJson('/api/v1/profile/2fa/disable', ['password' => 'Pass1234!'])->assertOk();
    expect(AuthEvent::where('event', AuthEvent::TWO_FACTOR_DISABLED)->exists())->toBeTrue();
});

// ============ SESSION + FIÓK TÖRLÉS ============

test('session revoke esemény naplózva', function () {
    $token = authLog('teszt@xy.hu', 'Pass1234!');
    $other = $this->user->createToken('Mobile')->accessToken;

    $this->withHeaders(authHeader($token))
        ->deleteJson("/api/v1/profile/sessions/{$other->id}")->assertOk();

    expect(AuthEvent::where('event', AuthEvent::SESSION_REVOKED)->exists())->toBeTrue();
});

test('account_deletion_scheduled + cancelled események naplózva', function () {
    $token = authLog('teszt@xy.hu', 'Pass1234!');

    // Schedule
    $this->withHeaders(authHeader($token))
        ->deleteJson('/api/v1/profile', ['password' => 'Pass1234!'])->assertOk();
    expect(AuthEvent::where('event', AuthEvent::ACCOUNT_DELETION_SCHEDULED)->exists())->toBeTrue();

    // Cancel
    $this->user->refresh();
    \Laravel\Sanctum\Sanctum::actingAs($this->user);
    $this->postJson('/api/v1/profile/delete/cancel')->assertOk();
    expect(AuthEvent::where('event', AuthEvent::ACCOUNT_DELETION_CANCELLED)->exists())->toBeTrue();
});

// ============ LOGIN HISTORY ENDPOINT ============

test('login-history endpoint visszaadja a saját eseményeimet', function () {
    $token = authLog('teszt@xy.hu', 'Pass1234!');

    // Néhány extra esemény
    $this->withHeaders(authHeader($token))->postJson('/api/v1/auth/logout');
    $token2 = authLog('teszt@xy.hu', 'Pass1234!');
    $this->withHeaders(authHeader($token2))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'Pass1234!',
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ]);

    $response = $this->withHeaders(authHeader($token2))
        ->getJson('/api/v1/profile/login-history')
        ->assertOk();

    $events = $response->json('data');
    expect(count($events))->toBeGreaterThanOrEqual(3);
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(3);
});

test('login-history event szűrővel', function () {
    $token = authLog('teszt@xy.hu', 'Pass1234!');
    $this->withHeaders(authHeader($token))->postJson('/api/v1/auth/logout');
    $token2 = authLog('teszt@xy.hu', 'Pass1234!');

    $response = $this->withHeaders(authHeader($token2))
        ->getJson('/api/v1/profile/login-history?event[]=login_success')
        ->assertOk();

    foreach ($response->json('data') as $event) {
        expect($event['event'])->toBe(AuthEvent::LOGIN_SUCCESS);
    }
});

test('login-history nem mutatja más user eseményeit', function () {
    // Idegen user, idegen esemény
    $other = User::create([
        'name' => 'Másik', 'email' => 'masik@xy.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    AuthEvent::create([
        'user_id' => $other->id, 'email' => 'masik@xy.hu',
        'event' => AuthEvent::LOGIN_SUCCESS, 'created_at' => now(),
    ]);

    $token = authLog('teszt@xy.hu', 'Pass1234!');

    $response = $this->withHeaders(authHeader($token))
        ->getJson('/api/v1/profile/login-history');

    foreach ($response->json('data') as $event) {
        expect($event['user_id'])->toBe($this->user->id);
    }
});

// ============ PRUNING PARANCS ============

test('auth:prune-events törli a régi eseményeket', function () {
    AuthEvent::create([
        'user_id' => $this->user->id, 'event' => 'login_success',
        'created_at' => now()->subDays(120),
    ]);
    AuthEvent::create([
        'user_id' => $this->user->id, 'event' => 'login_success',
        'created_at' => now()->subDays(10),
    ]);

    expect(AuthEvent::count())->toBe(2);

    $this->artisan('auth:prune-events', ['--days' => 90])->assertExitCode(0);

    expect(AuthEvent::count())->toBe(1);
    expect(AuthEvent::first()->created_at->diffInDays(now()))->toBeLessThan(90);
});

test('auth:prune-events --dry-run nem töröl', function () {
    AuthEvent::create([
        'user_id' => $this->user->id, 'event' => 'login_success',
        'created_at' => now()->subDays(120),
    ]);

    $this->artisan('auth:prune-events', ['--days' => 90, '--dry-run' => true])->assertExitCode(0);

    expect(AuthEvent::count())->toBe(1);
});
