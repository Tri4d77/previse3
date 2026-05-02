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

    $this->superAdminMembership = Membership::create([
        'user_id' => $this->superAdmin->id,
        'organization_id' => $this->platform->id,
        'role_id' => $this->platformRole->id,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    // Előfizető szervezet + admin szerepkör
    $this->org = Organization::create([
        'type' => 'subscriber', 'name' => 'XY Kft.', 'slug' => 'xy-kft',
        'is_active' => true, 'parent_id' => $this->platform->id,
    ]);

    $this->adminRole = Role::create([
        'organization_id' => $this->org->id,
        'name' => 'Admin', 'slug' => 'admin', 'is_system' => true,
    ]);
    $this->adminRole->permissions()->sync(\App\Models\Permission::pluck('id'));

    $this->userRole = Role::create([
        'organization_id' => $this->org->id,
        'name' => 'Felhasználó', 'slug' => 'user', 'is_system' => true,
    ]);

    $this->admin = User::create([
        'name' => 'Szervezeti Admin',
        'email' => 'szervadmin@xy.hu',
        'password' => 'Admin123!',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $this->adminMembership = Membership::create([
        'user_id' => $this->admin->id,
        'organization_id' => $this->org->id,
        'role_id' => $this->adminRole->id,
        'is_active' => true,
        'joined_at' => now(),
    ]);
});

// Segéd: login az adminnal és visszaadja a tokent
function loginAsAdmin(): string
{
    $response = test()->postJson('/api/v1/auth/login', [
        'email' => 'szervadmin@xy.hu',
        'password' => 'Admin123!',
        'device_name' => 'Test',
    ]);
    return $response->json('data.token');
}

test('új user meghívás: új user és új membership jön létre', function () {
    $token = loginAsAdmin();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships', [
            'name' => 'Új Felhasználó',
            'email' => 'uj@xy.hu',
            'role_id' => $this->userRole->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('is_existing_user', false)
        ->assertJsonPath('data.user.email', 'uj@xy.hu')
        ->assertJsonPath('data.status', 'pending');

    // User létrejött, password = null
    $newUser = User::where('email', 'uj@xy.hu')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->password)->toBeNull();
    expect($newUser->is_active)->toBeFalse();
    expect($newUser->email_verified_at)->toBeNull();

    // Membership létrejött pending állapotban
    $membership = Membership::where('user_id', $newUser->id)->first();
    expect($membership->invitation_token)->not->toBeNull();
    expect($membership->joined_at)->toBeNull();
    expect($membership->is_active)->toBeFalse();

    // Invitation URL érkezik
    expect($response->json('invitation_url'))->toContain('/invitation/');
});

test('létező user meghívás: csak új membership jön létre', function () {
    // Már létező user (nem tagja még a szervezetnek)
    $existing = User::create([
        'name' => 'Már Létező',
        'email' => 'letezo@valahol.hu',
        'password' => 'Pass123!',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $token = loginAsAdmin();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships', [
            'email' => 'letezo@valahol.hu',
            'role_id' => $this->userRole->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('is_existing_user', true)
        ->assertJsonPath('data.user.email', 'letezo@valahol.hu')
        ->assertJsonPath('data.user.name', 'Már Létező');

    // Csak 1 user maradt ezzel az email-lel
    expect(User::where('email', 'letezo@valahol.hu')->count())->toBe(1);

    // A user adatai változatlanok
    expect($existing->fresh()->password)->not->toBeNull();
    expect($existing->fresh()->is_active)->toBeTrue();

    // Új membership létrejött
    $membership = Membership::where('user_id', $existing->id)->first();
    expect($membership)->not->toBeNull();
    expect($membership->invitation_token)->not->toBeNull();
    expect($membership->joined_at)->toBeNull();
});

test('check-email: új email -> user_exists false', function () {
    $token = loginAsAdmin();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships/check-email', [
            'email' => 'ujemail@test.hu',
        ]);

    $response->assertOk()
        ->assertJsonPath('user_exists', false);
});

test('check-email: létező user -> user_exists true', function () {
    $existing = User::create([
        'name' => 'Létező',
        'email' => 'letezo@valahol.hu',
        'password' => 'Pass123!',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $token = loginAsAdmin();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships/check-email', [
            'email' => 'letezo@valahol.hu',
        ]);

    $response->assertOk()
        ->assertJsonPath('user_exists', true)
        ->assertJsonPath('user.name', 'Létező')
        ->assertJsonPath('already_member', false);
});

test('check-email: már tag -> already_member true', function () {
    $token = loginAsAdmin();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships/check-email', [
            'email' => 'szervadmin@xy.hu',
        ]);

    $response->assertOk()
        ->assertJsonPath('user_exists', true)
        ->assertJsonPath('already_member', true);
});

test('meghívás: már tag -> hiba', function () {
    $token = loginAsAdmin();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships', [
            'email' => 'szervadmin@xy.hu',
            'role_id' => $this->userRole->id,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('meghívás: más szervezet szerepkörével -> hiba', function () {
    // A platform admin szerepkör nem tartozik az XY Kft.-hez
    $token = loginAsAdmin();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships', [
            'name' => 'Új',
            'email' => 'uj@test.hu',
            'role_id' => $this->platformRole->id, // másik szervezet szerepköre
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['role_id']);
});

test('új user nevet kötelezően kell megadni', function () {
    $token = loginAsAdmin();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships', [
            'email' => 'ujuj@test.hu',
            // name hiányzik
            'role_id' => $this->userRole->id,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('meghívó elfogadás - új user esetén jelszó beállítás', function () {
    // Új meghívás létrehozása
    $token = loginAsAdmin();
    $invite = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships', [
            'name' => 'Új Felhasználó',
            'email' => 'uj@xy.hu',
            'role_id' => $this->userRole->id,
        ]);

    $newUser = User::where('email', 'uj@xy.hu')->first();
    $membership = $newUser->memberships()->first();
    $invitationToken = $membership->invitation_token;

    // Elfogadás
    $response = $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => $invitationToken,
        'password' => 'NewPass123!',
        'password_confirmation' => 'NewPass123!',
    ]);

    $response->assertOk();

    $newUser->refresh();
    $membership->refresh();

    expect($newUser->password)->not->toBeNull();
    expect($newUser->email_verified_at)->not->toBeNull();
    expect($newUser->is_active)->toBeTrue();

    expect($membership->joined_at)->not->toBeNull();
    expect($membership->is_active)->toBeTrue();
    expect($membership->invitation_token)->toBeNull();

    // Be tud jelentkezni
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'uj@xy.hu',
        'password' => 'NewPass123!',
        'device_name' => 'Test',
    ]);
    $login->assertOk();
});

test('meghívó elfogadás - létező user esetén a jelenlegi jelszóval', function () {
    // Létező user
    $existing = User::create([
        'name' => 'Létező',
        'email' => 'letezo@valahol.hu',
        'password' => 'ExistingPass123!',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $token = loginAsAdmin();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships', [
            'email' => 'letezo@valahol.hu',
            'role_id' => $this->userRole->id,
        ]);

    $membership = Membership::where('user_id', $existing->id)
        ->where('organization_id', $this->org->id)
        ->first();

    $response = $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => $membership->invitation_token,
        'password' => 'ExistingPass123!',
        'password_confirmation' => 'ExistingPass123!',
    ]);

    $response->assertOk();

    $membership->refresh();
    expect($membership->joined_at)->not->toBeNull();
    expect($membership->is_active)->toBeTrue();
});

test('meghívó elfogadás - létező user rossz jelszóval -> hiba', function () {
    $existing = User::create([
        'name' => 'Létező',
        'email' => 'letezo@valahol.hu',
        'password' => 'ExistingPass123!',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $token = loginAsAdmin();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships', [
            'email' => 'letezo@valahol.hu',
            'role_id' => $this->userRole->id,
        ]);

    $membership = Membership::where('user_id', $existing->id)->first();

    $response = $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => $membership->invitation_token,
        'password' => 'RosszJelszo!',
        'password_confirmation' => 'RosszJelszo!',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

test('meghívó elfogadás - inaktív létező user reaktiválódik', function () {
    // Korábban deaktiválódott user (pl. minden tagsága törlésekor)
    $existing = User::create([
        'name' => 'Régi',
        'email' => 'regi@valahol.hu',
        'password' => 'OldPass123!',
        'is_active' => false,
        'email_verified_at' => now(),
    ]);

    // Admin újra meghívja egy szervezetbe
    $token = loginAsAdmin();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/memberships', [
            'email' => 'regi@valahol.hu',
            'role_id' => $this->userRole->id,
        ]);

    $membership = Membership::where('user_id', $existing->id)
        ->where('organization_id', $this->org->id)
        ->first();

    // Elfogadja a régi jelszavával
    $this->postJson('/api/v1/auth/accept-invitation', [
        'token' => $membership->invitation_token,
        'password' => 'OldPass123!',
        'password_confirmation' => 'OldPass123!',
    ])->assertOk();

    // user.is_active újra true → be tud lépni
    $existing->refresh();
    expect($existing->is_active)->toBeTrue();

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'regi@valahol.hu',
        'password' => 'OldPass123!',
        'device_name' => 'Test',
    ]);
    $login->assertOk();
});
