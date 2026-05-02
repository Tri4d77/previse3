<?php

use App\Models\Location;
use App\Models\LocationContact;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationRoleSeeder;
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
    OrganizationRoleSeeder::seed($this->org);
    $adminRole = Role::where('organization_id', $this->org->id)->where('slug', 'admin')->first();
    $userRole = Role::where('organization_id', $this->org->id)->where('slug', 'user')->first();

    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@xy.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    $this->adminMembership = Membership::create([
        'user_id' => $this->admin->id, 'organization_id' => $this->org->id,
        'role_id' => $adminRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);

    $this->user = User::create([
        'name' => 'User', 'email' => 'user@xy.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    $this->userMembership = Membership::create([
        'user_id' => $this->user->id, 'organization_id' => $this->org->id,
        'role_id' => $userRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);

    $this->location = Location::create([
        'organization_id' => $this->org->id, 'code' => 'LOC-001', 'name' => 'Test Building',
    ]);

    $this->adminToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@xy.hu', 'password' => 'Pass1234!',
    ])->json('data.token');
    $this->userToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@xy.hu', 'password' => 'Pass1234!',
    ])->json('data.token');
});

function crHdr(string $t): array
{
    return ['Authorization' => "Bearer {$t}"];
}

// ============ CONTACTS ============

test('admin kontaktot hozhat létre helyszínhez', function () {
    $response = $this->withHeaders(crHdr($this->adminToken))
        ->postJson("/api/v1/locations/{$this->location->id}/contacts", [
            'name' => 'Kovács János',
            'role_label' => 'Gondnok',
            'phone' => '+36 30 123 4567',
            'email' => 'kovacs@example.com',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Kovács János')
        ->assertJsonPath('data.role_label', 'Gondnok');
});

test('admin kontakt listát kérdezhet le', function () {
    LocationContact::create([
        'location_id' => $this->location->id, 'name' => 'A',
    ]);
    LocationContact::create([
        'location_id' => $this->location->id, 'name' => 'B',
    ]);

    $this->withHeaders(crHdr($this->adminToken))
        ->getJson("/api/v1/locations/{$this->location->id}/contacts")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('user szerepkör nem hozhat létre kontaktot', function () {
    $this->withHeaders(crHdr($this->userToken))
        ->postJson("/api/v1/locations/{$this->location->id}/contacts", ['name' => 'X'])
        ->assertStatus(403);
});

test('user szerepkör listázhatja a kontaktokat', function () {
    LocationContact::create(['location_id' => $this->location->id, 'name' => 'Z']);

    $this->withHeaders(crHdr($this->userToken))
        ->getJson("/api/v1/locations/{$this->location->id}/contacts")
        ->assertOk();
});

test('kontakt szerkeszthető', function () {
    $c = LocationContact::create([
        'location_id' => $this->location->id, 'name' => 'Régi',
    ]);

    $this->withHeaders(crHdr($this->adminToken))
        ->putJson("/api/v1/location-contacts/{$c->id}", ['name' => 'Új'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Új');
});

test('kontakt törölhető', function () {
    $c = LocationContact::create([
        'location_id' => $this->location->id, 'name' => 'Töröl',
    ]);

    $this->withHeaders(crHdr($this->adminToken))
        ->deleteJson("/api/v1/location-contacts/{$c->id}")
        ->assertOk();

    expect(LocationContact::find($c->id))->toBeNull();
});

test('kontakt érvénytelen email elutasítva', function () {
    $this->withHeaders(crHdr($this->adminToken))
        ->postJson("/api/v1/locations/{$this->location->id}/contacts", [
            'name' => 'X', 'email' => 'invalid',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('role autocomplete egyedi értékeket ad vissza', function () {
    LocationContact::create(['location_id' => $this->location->id, 'name' => 'A', 'role_label' => 'Gondnok']);
    LocationContact::create(['location_id' => $this->location->id, 'name' => 'B', 'role_label' => 'Gondnok']);
    LocationContact::create(['location_id' => $this->location->id, 'name' => 'C', 'role_label' => 'Portás']);

    $resp = $this->withHeaders(crHdr($this->adminToken))
        ->getJson("/api/v1/locations/{$this->location->id}/contact-roles")
        ->assertOk();

    expect($resp->json('data'))->toBe(['Gondnok', 'Portás']);
});

test('idegen szervezet kontaktjához nem fér hozzá', function () {
    $otherOrg = Organization::create([
        'type' => 'subscriber', 'name' => 'AB', 'slug' => 'ab',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    OrganizationRoleSeeder::seed($otherOrg);
    $otherLoc = Location::create([
        'organization_id' => $otherOrg->id, 'code' => 'X', 'name' => 'X',
    ]);

    $this->withHeaders(crHdr($this->adminToken))
        ->getJson("/api/v1/locations/{$otherLoc->id}/contacts")
        ->assertStatus(403);
});

// ============ RESPONSIBLES ============

test('admin felelőst rendelhet a helyszínhez', function () {
    $this->withHeaders(crHdr($this->adminToken))
        ->postJson("/api/v1/locations/{$this->location->id}/responsibles", [
            'membership_ids' => [$this->userMembership->id],
        ])
        ->assertStatus(201)
        ->assertJsonCount(1, 'data');

    expect($this->location->responsibles()->count())->toBe(1);
});

test('felelős listázható', function () {
    $this->location->responsibles()->attach($this->userMembership->id, ['assigned_at' => now()]);

    $this->withHeaders(crHdr($this->adminToken))
        ->getJson("/api/v1/locations/{$this->location->id}/responsibles")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user.name', 'User');
});

test('felelős eltávolítható', function () {
    $this->location->responsibles()->attach($this->userMembership->id, ['assigned_at' => now()]);

    $this->withHeaders(crHdr($this->adminToken))
        ->deleteJson("/api/v1/locations/{$this->location->id}/responsibles/{$this->userMembership->id}")
        ->assertOk();

    expect($this->location->responsibles()->count())->toBe(0);
});

test('idegen szervezet membership-je nem rendelhető felelősnek', function () {
    $otherOrg = Organization::create([
        'type' => 'subscriber', 'name' => 'AB', 'slug' => 'ab',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    OrganizationRoleSeeder::seed($otherOrg);
    $otherRole = Role::where('organization_id', $otherOrg->id)->where('slug', 'admin')->first();
    $otherUser = User::create([
        'name' => 'O', 'email' => 'o@ab.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    $otherM = Membership::create([
        'user_id' => $otherUser->id, 'organization_id' => $otherOrg->id,
        'role_id' => $otherRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);

    $this->withHeaders(crHdr($this->adminToken))
        ->postJson("/api/v1/locations/{$this->location->id}/responsibles", [
            'membership_ids' => [$otherM->id],
        ])
        ->assertStatus(422);

    expect($this->location->responsibles()->count())->toBe(0);
});

test('user szerepkör nem rendelhet felelőst', function () {
    $this->withHeaders(crHdr($this->userToken))
        ->postJson("/api/v1/locations/{$this->location->id}/responsibles", [
            'membership_ids' => [$this->userMembership->id],
        ])
        ->assertStatus(403);
});

test('available endpoint csak még nem felelős membership-eket ad vissza', function () {
    $this->location->responsibles()->attach($this->userMembership->id, ['assigned_at' => now()]);

    $resp = $this->withHeaders(crHdr($this->adminToken))
        ->getJson("/api/v1/locations/{$this->location->id}/responsibles/available")
        ->assertOk();

    $ids = collect($resp->json('data'))->pluck('id')->all();
    expect($ids)->toContain($this->adminMembership->id);
    expect($ids)->not->toContain($this->userMembership->id);
});

test('duplikált felelős kijelölés idempotens', function () {
    $this->withHeaders(crHdr($this->adminToken))
        ->postJson("/api/v1/locations/{$this->location->id}/responsibles", [
            'membership_ids' => [$this->userMembership->id],
        ])->assertStatus(201);

    $this->withHeaders(crHdr($this->adminToken))
        ->postJson("/api/v1/locations/{$this->location->id}/responsibles", [
            'membership_ids' => [$this->userMembership->id],
        ])->assertStatus(201);

    expect($this->location->responsibles()->count())->toBe(1);
});
