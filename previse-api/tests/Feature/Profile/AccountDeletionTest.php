<?php

use App\Mail\SecurityAlertMail;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $this->platform = Organization::create([
        'type' => 'platform', 'name' => 'Platform', 'slug' => 'platform', 'is_active' => true,
    ]);
    $this->platformAdminRole = Role::create([
        'organization_id' => $this->platform->id, 'name' => 'Admin', 'slug' => 'admin', 'is_system' => true,
    ]);
    $this->platformAdminRole->permissions()->sync(Permission::pluck('id'));

    $this->superAdmin = User::create([
        'name' => 'Szuper Admin', 'email' => 'super@previse.hu', 'password' => 'Admin123!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->superAdmin->id,
        'organization_id' => $this->platform->id,
        'role_id' => $this->platformAdminRole->id,
        'is_active' => true, 'joined_at' => now(),
    ]);

    // Subscriber org 2 userrel: admin + sima user
    $this->org = Organization::create([
        'type' => 'subscriber', 'name' => 'XY', 'slug' => 'xy',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    $this->orgAdminRole = Role::create([
        'organization_id' => $this->org->id, 'name' => 'Admin', 'slug' => 'admin', 'is_system' => true,
    ]);
    $this->orgAdminRole->permissions()->sync(Permission::pluck('id'));
    $this->orgUserRole = Role::create([
        'organization_id' => $this->org->id, 'name' => 'Felhasználó', 'slug' => 'user', 'is_system' => true,
    ]);

    $this->orgAdmin = User::create([
        'name' => 'Org Admin', 'email' => 'admin@xy.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->orgAdmin->id, 'organization_id' => $this->org->id,
        'role_id' => $this->orgAdminRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);

    $this->orgUser = User::create([
        'name' => 'Org User', 'email' => 'user@xy.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->orgUser->id, 'organization_id' => $this->org->id,
        'role_id' => $this->orgUserRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);
});

function loginAs(string $email, string $password, ?int $defaultOrgId = null): string
{
    // Ha több tagság van, default_organization_id-vel kényszerítjük a direkt belépést
    if ($defaultOrgId) {
        $user = \App\Models\User::where('email', $email)->first();
        if ($user) {
            $user->settings()->updateOrCreate([], ['default_organization_id' => $defaultOrgId]);
        }
    }

    $response = test()->postJson('/api/v1/auth/login', [
        'email' => $email, 'password' => $password,
    ]);

    // Ha selection szükséges, automatikusan kiválasztjuk az elsőt
    if ($response->json('requires_organization_selection')) {
        $membershipId = $response->json('memberships.0.id');
        $selectionToken = $response->json('selection_token');
        $response = test()->withHeader('Authorization', "Bearer {$selectionToken}")
            ->postJson('/api/v1/auth/select-organization', ['membership_id' => $membershipId]);
    }

    return $response->json('data.token');
}

// ============ FIÓK MEGSZÜNTETÉSE ============

test('fiók-törlés kezdeményezése sikeres jó jelszóval', function () {
    Mail::fake();
    $token = loginAs('user@xy.hu', 'Pass1234!');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/v1/profile', ['password' => 'Pass1234!']);

    $response->assertOk()->assertJsonStructure(['scheduled_deletion_at']);

    $this->orgUser->refresh();
    expect($this->orgUser->scheduled_deletion_at)->not->toBeNull();
    // Tagságok soft-deleted-ek
    expect($this->orgUser->memberships()->withTrashed()->whereNotNull('deleted_at')->count())->toBeGreaterThan(0);
    // Tokenek törölve
    expect($this->orgUser->tokens()->count())->toBe(0);

    Mail::assertQueued(SecurityAlertMail::class, fn ($m) => $m->eventKey === 'account_deletion_scheduled');
});

test('fiók-törlés rossz jelszóval 422', function () {
    $token = loginAs('user@xy.hu', 'Pass1234!');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/v1/profile', ['password' => 'RosszJelszo!'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    $this->orgUser->refresh();
    expect($this->orgUser->scheduled_deletion_at)->toBeNull();
});

test('egyetlen szuper-admin nem törölheti magát', function () {
    $token = loginAs('super@previse.hu', 'Admin123!');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/v1/profile', ['password' => 'Admin123!'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'last_super_admin');

    $this->superAdmin->refresh();
    expect($this->superAdmin->scheduled_deletion_at)->toBeNull();
});

test('második szuper-admin létrehozása után törölhető a fiók', function () {
    // Második platform admin
    $super2 = User::create([
        'name' => 'Super 2', 'email' => 'super2@previse.hu', 'password' => 'Admin123!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $super2->id, 'organization_id' => $this->platform->id,
        'role_id' => $this->platformAdminRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);

    $token = loginAs('super@previse.hu', 'Admin123!');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/v1/profile', ['password' => 'Admin123!'])
        ->assertOk();
});

// ============ LOGIN PENDING DELETION ============

test('pending-deletion usert login a visszavonás tokenhez irányítja', function () {
    $this->orgUser->update(['scheduled_deletion_at' => now()->addDays(30)]);
    // Tagságok soft-deleted (mivel éles flow-ban is)
    $this->orgUser->memberships()->update(['is_active' => false]);
    $this->orgUser->memberships()->delete();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@xy.hu',
        'password' => 'Pass1234!',
    ]);

    $response->assertOk()
        ->assertJsonPath('requires_deletion_decision', true)
        ->assertJsonStructure(['deletion_cancel_token', 'scheduled_deletion_at', 'days_until_deletion']);
});

// ============ VISSZAVONÁS ============

test('fiók-törlés visszavonható és egyben login tokent ad', function () {
    Mail::fake();
    $this->orgUser->update(['scheduled_deletion_at' => now()->addDays(30)]);
    $this->orgUser->memberships()->update(['is_active' => false]);
    $this->orgUser->memberships()->delete();

    // Ellenőrizzük, hogy a login flow cancel tokent ad
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@xy.hu', 'password' => 'Pass1234!',
    ]);
    $loginResponse->assertOk()->assertJsonPath('requires_deletion_decision', true);
    expect($loginResponse->json('deletion_cancel_token'))->toBeString();

    // A cancel endpoint teszteléséhez Sanctum::actingAs
    \Laravel\Sanctum\Sanctum::actingAs($this->orgUser);

    $cancelResponse = $this->postJson('/api/v1/profile/delete/cancel')->assertOk();

    // A cancel után teljes login válasz: token + user + membership
    $cancelResponse->assertJsonStructure(['data' => ['user', 'token']]);
    expect($cancelResponse->json('data.token'))->toBeString();

    $this->orgUser->refresh();
    expect($this->orgUser->scheduled_deletion_at)->toBeNull();
    expect($this->orgUser->activeMemberships()->count())->toBeGreaterThan(0);

    Mail::assertQueued(SecurityAlertMail::class, fn ($m) => $m->eventKey === 'account_deletion_cancelled');
});

test('cancel ha nincs pending deletion: 422', function () {
    $token = loginAs('user@xy.hu', 'Pass1234!');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/profile/delete/cancel')
        ->assertStatus(422);
});

test('cancel után a korábbi tagságok visszaállnak és a user normálisan be tud lépni', function () {
    Mail::fake();

    // 1. User fiók-törlést indít
    $token = loginAs('user@xy.hu', 'Pass1234!');
    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/v1/profile', ['password' => 'Pass1234!'])
        ->assertOk();

    $this->orgUser->refresh();
    expect($this->orgUser->scheduled_deletion_at)->not->toBeNull();
    expect($this->orgUser->activeMemberships()->count())->toBe(0);

    // 2. User újra bejelentkezik → cancel tokent kap
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@xy.hu', 'password' => 'Pass1234!',
    ]);
    $loginResponse->assertJsonPath('requires_deletion_decision', true);

    // 3. Cancel → tagságok visszaállnak
    \Laravel\Sanctum\Sanctum::actingAs($this->orgUser);
    $this->postJson('/api/v1/profile/delete/cancel')->assertOk();

    $this->orgUser->refresh();
    expect($this->orgUser->scheduled_deletion_at)->toBeNull();
    expect($this->orgUser->activeMemberships()->count())->toBeGreaterThan(0);

    // 4. User most már normálisan be tud lépni
    $this->orgUser->tokens()->delete(); // töröljük a Sanctum actingAs tokenjét
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@xy.hu', 'password' => 'Pass1234!',
    ]);
    $response->assertOk()->assertJsonStructure(['data' => ['user', 'current_membership', 'token']]);
});

// ============ UTOLSÓ ADMIN TÁVOZÁSA ============

test('utolsó admin fiók-törlés esetén többi tag értesítést kap', function () {
    Mail::fake();
    $token = loginAs('admin@xy.hu', 'Pass1234!');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson('/api/v1/profile', ['password' => 'Pass1234!'])
        ->assertOk();

    // orgUser egy email-t kap admin_left_organization eseménnyel
    Mail::assertQueued(SecurityAlertMail::class, function (SecurityAlertMail $m) {
        return $m->eventKey === 'admin_left_organization'
            && $m->hasTo('user@xy.hu')
            && $m->introReplacements['organization'] === 'XY'
            && $m->introReplacements['admin_name'] === 'Org Admin';
    });
});

// ============ FINALIZE PARANCS (anonimizáció) ============

test('finalize parancs anonimizálja a lejárt grace-ű fiókokat, de a név megmarad', function () {
    $this->orgUser->update(['scheduled_deletion_at' => now()->subDay()]);

    $this->artisan('users:finalize-deletions')->assertExitCode(0);

    $this->orgUser = User::withTrashed()->find($this->orgUser->id);
    expect($this->orgUser->trashed())->toBeTrue();
    expect($this->orgUser->name)->toBe('Org User');               // NÉV MARAD
    expect($this->orgUser->email)->toBe("deleted-{$this->orgUser->id}@previse.local");
    expect($this->orgUser->phone)->toBeNull();
    expect($this->orgUser->is_active)->toBeFalse();
    expect($this->orgUser->two_factor_secret)->toBeNull();
    expect($this->orgUser->scheduled_deletion_at)->toBeNull();
    // A user beállítások törölve
    expect(\App\Models\UserSetting::where('user_id', $this->orgUser->id)->exists())->toBeFalse();
});

test('finalize parancs nem piszkálja a még nem lejárt fiókokat', function () {
    $this->orgUser->update(['scheduled_deletion_at' => now()->addDays(10)]);

    $this->artisan('users:finalize-deletions')->assertExitCode(0);

    $this->orgUser->refresh();
    expect($this->orgUser->trashed())->toBeFalse();
    expect($this->orgUser->email)->toBe('user@xy.hu');
});

test('finalize --dry-run nem módosít', function () {
    $this->orgUser->update(['scheduled_deletion_at' => now()->subDay()]);

    $this->artisan('users:finalize-deletions', ['--dry-run' => true])->assertExitCode(0);

    $this->orgUser->refresh();
    expect($this->orgUser->trashed())->toBeFalse();
    expect($this->orgUser->email)->toBe('user@xy.hu');
});

// ============ SZERVEZETBŐL KILÉPÉS ============

test('kilépés egy szervezetből ha több is van', function () {
    // orgUser-nek adjunk második tagságot egy másik szervezetben
    $org2 = Organization::create([
        'type' => 'subscriber', 'name' => 'AB', 'slug' => 'ab',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    $role2 = Role::create(['organization_id' => $org2->id, 'name' => 'User', 'slug' => 'user', 'is_system' => true]);
    $m2 = Membership::create([
        'user_id' => $this->orgUser->id, 'organization_id' => $org2->id, 'role_id' => $role2->id,
        'is_active' => true, 'joined_at' => now(),
    ]);

    $token = loginAs('user@xy.hu', 'Pass1234!');

    $firstMembership = $this->orgUser->memberships()->where('organization_id', $this->org->id)->first();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/profile/memberships/{$firstMembership->id}/leave")
        ->assertOk();

    expect($this->orgUser->activeMemberships()->count())->toBe(1);
});

test('utolsó aktív tagságot nem lehet kilépéssel elhagyni', function () {
    $token = loginAs('user@xy.hu', 'Pass1234!');

    $membership = $this->orgUser->memberships()->first();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/profile/memberships/{$membership->id}/leave")
        ->assertStatus(422)
        ->assertJsonPath('code', 'last_active_membership');
});

test('utolsó admin kilépés: többi tag értesítést kap', function () {
    Mail::fake();

    // Az orgAdmin-nak legyen másik tagsága is, hogy ne utolsó active membership legyen
    $org2 = Organization::create([
        'type' => 'subscriber', 'name' => 'AB', 'slug' => 'ab',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    $role2 = Role::create(['organization_id' => $org2->id, 'name' => 'User', 'slug' => 'user', 'is_system' => true]);
    Membership::create([
        'user_id' => $this->orgAdmin->id, 'organization_id' => $org2->id, 'role_id' => $role2->id,
        'is_active' => true, 'joined_at' => now(),
    ]);

    $token = loginAs('admin@xy.hu', 'Pass1234!');

    $xyMembership = $this->orgAdmin->memberships()->where('organization_id', $this->org->id)->first();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/profile/memberships/{$xyMembership->id}/leave")
        ->assertOk();

    Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $m) =>
        $m->eventKey === 'admin_left_organization' && $m->hasTo('user@xy.hu')
    );
});

test('egyetlen super-admin nem léphet ki a Platformból', function () {
    $token = loginAs('super@previse.hu', 'Admin123!');
    $platformMembership = $this->superAdmin->memberships()->where('organization_id', $this->platform->id)->first();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/profile/memberships/{$platformMembership->id}/leave")
        ->assertStatus(422)
        ->assertJsonPath('code', 'last_super_admin');
});
