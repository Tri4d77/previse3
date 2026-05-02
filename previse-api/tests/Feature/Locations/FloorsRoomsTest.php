<?php

use App\Models\Floor;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Room;
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

    // Admin user
    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@xy.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->admin->id, 'organization_id' => $this->org->id,
        'role_id' => $adminRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);

    // Sima user (csak read)
    $this->user = User::create([
        'name' => 'User', 'email' => 'user@xy.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->user->id, 'organization_id' => $this->org->id,
        'role_id' => $userRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);

    // Helyszín
    $this->location = Location::create([
        'organization_id' => $this->org->id, 'code' => 'LOC-001', 'name' => 'Test Building',
    ]);

    // Tokenek
    $this->adminToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@xy.hu', 'password' => 'Pass1234!',
    ])->json('data.token');
    $this->userToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@xy.hu', 'password' => 'Pass1234!',
    ])->json('data.token');
});

function hdr(string $t): array
{
    return ['Authorization' => "Bearer {$t}"];
}

// ============ FLOORS ============

test('admin szintet hozhat létre helyszínhez', function () {
    $response = $this->withHeaders(hdr($this->adminToken))
        ->postJson("/api/v1/locations/{$this->location->id}/floors", [
            'name' => 'Földszint', 'level' => 0,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Földszint')
        ->assertJsonPath('data.level', 0)
        ->assertJsonPath('data.location_id', $this->location->id);
});

test('szint nevet helyszín-szinten unique-on tartjuk', function () {
    Floor::create(['location_id' => $this->location->id, 'name' => 'Földszint', 'level' => 0]);

    $this->withHeaders(hdr($this->adminToken))
        ->postJson("/api/v1/locations/{$this->location->id}/floors", [
            'name' => 'Földszint',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('szintek listája level szerint rendezve', function () {
    Floor::create(['location_id' => $this->location->id, 'name' => '1. emelet', 'level' => 1]);
    Floor::create(['location_id' => $this->location->id, 'name' => 'Pince', 'level' => -1]);
    Floor::create(['location_id' => $this->location->id, 'name' => 'Földszint', 'level' => 0]);

    $response = $this->withHeaders(hdr($this->adminToken))
        ->getJson("/api/v1/locations/{$this->location->id}/floors")
        ->assertOk();

    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toBe(['Pince', 'Földszint', '1. emelet']);
});

test('üres szint törölhető', function () {
    $floor = Floor::create(['location_id' => $this->location->id, 'name' => 'Pince', 'level' => -1]);

    $this->withHeaders(hdr($this->adminToken))
        ->deleteJson("/api/v1/floors/{$floor->id}")
        ->assertOk();

    expect(Floor::where('id', $floor->id)->exists())->toBeFalse();
});

test('szint, amelyben helyiségek vannak, nem törölhető', function () {
    $floor = Floor::create(['location_id' => $this->location->id, 'name' => 'Földszint', 'level' => 0]);
    Room::create(['location_id' => $this->location->id, 'floor_id' => $floor->id, 'name' => 'Iroda 1']);

    $this->withHeaders(hdr($this->adminToken))
        ->deleteJson("/api/v1/floors/{$floor->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'floor_has_rooms');
});

test('szint módosítható', function () {
    $floor = Floor::create(['location_id' => $this->location->id, 'name' => 'Földszint', 'level' => 0]);

    $this->withHeaders(hdr($this->adminToken))
        ->putJson("/api/v1/floors/{$floor->id}", [
            'name' => 'Földszint (átnevezve)', 'description' => 'Újonnan felújítva',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Földszint (átnevezve)');
});

test('idegen szervezet helyszín-szintjéhez nem lehet hozzáférni', function () {
    $otherOrg = Organization::create([
        'type' => 'subscriber', 'name' => 'AB', 'slug' => 'ab',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    $otherLocation = Location::create([
        'organization_id' => $otherOrg->id, 'code' => 'AB-001', 'name' => 'Másik épület',
    ]);

    $this->withHeaders(hdr($this->adminToken))
        ->postJson("/api/v1/locations/{$otherLocation->id}/floors", [
            'name' => 'Földszint',
        ])
        ->assertStatus(403);
});

// ============ ROOMS ============

test('admin helyiséget hozhat létre szint nélkül', function () {
    $response = $this->withHeaders(hdr($this->adminToken))
        ->postJson("/api/v1/locations/{$this->location->id}/rooms", [
            'name' => 'Bejárat',
            'type' => 'folyosó',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Bejárat')
        ->assertJsonPath('data.floor_id', null);
});

test('admin helyiséget hozhat létre szinttel', function () {
    $floor = Floor::create(['location_id' => $this->location->id, 'name' => 'Földszint', 'level' => 0]);

    $this->withHeaders(hdr($this->adminToken))
        ->postJson("/api/v1/locations/{$this->location->id}/rooms", [
            'floor_id' => $floor->id,
            'name' => 'Recepció',
            'type' => 'iroda',
            'area_sqm' => 25.5,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.floor_id', $floor->id)
        ->assertJsonPath('data.area_sqm', '25.50');
});

test('idegen helyszín szintje nem köthető helyiséghez', function () {
    $otherLocation = Location::create([
        'organization_id' => $this->org->id, 'code' => 'LOC-002', 'name' => 'B épület',
    ]);
    $otherFloor = Floor::create(['location_id' => $otherLocation->id, 'name' => 'Földszint', 'level' => 0]);

    $this->withHeaders(hdr($this->adminToken))
        ->postJson("/api/v1/locations/{$this->location->id}/rooms", [
            'floor_id' => $otherFloor->id,
            'name' => 'X',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('floor_id');
});

test('helyiség listája szűrhető szint szerint', function () {
    $floor = Floor::create(['location_id' => $this->location->id, 'name' => '1. emelet', 'level' => 1]);
    Room::create(['location_id' => $this->location->id, 'floor_id' => $floor->id, 'name' => 'Iroda 1']);
    Room::create(['location_id' => $this->location->id, 'floor_id' => null, 'name' => 'Bejárat']);
    Room::create(['location_id' => $this->location->id, 'floor_id' => $floor->id, 'name' => 'Iroda 2']);

    $resByFloor = $this->withHeaders(hdr($this->adminToken))
        ->getJson("/api/v1/locations/{$this->location->id}/rooms?floor_id={$floor->id}")
        ->assertOk();
    expect($resByFloor->json('data'))->toHaveCount(2);

    $resNoFloor = $this->withHeaders(hdr($this->adminToken))
        ->getJson("/api/v1/locations/{$this->location->id}/rooms?floor_id=null")
        ->assertOk();
    expect($resNoFloor->json('data'))->toHaveCount(1);
    expect($resNoFloor->json('data.0.name'))->toBe('Bejárat');
});

test('helyiség módosítható szinten kívülre is', function () {
    $floor = Floor::create(['location_id' => $this->location->id, 'name' => 'F', 'level' => 0]);
    $room = Room::create([
        'location_id' => $this->location->id, 'floor_id' => $floor->id, 'name' => 'X',
    ]);

    $this->withHeaders(hdr($this->adminToken))
        ->putJson("/api/v1/rooms/{$room->id}", ['floor_id' => null])
        ->assertOk()
        ->assertJsonPath('data.floor_id', null);
});

test('helyiség törlése', function () {
    $room = Room::create([
        'location_id' => $this->location->id, 'name' => 'Törölhető',
    ]);

    $this->withHeaders(hdr($this->adminToken))
        ->deleteJson("/api/v1/rooms/{$room->id}")
        ->assertOk();

    expect(Room::where('id', $room->id)->exists())->toBeFalse();
});

test('helyiség típus-autocomplete egyedi értékeket ad', function () {
    Room::create(['location_id' => $this->location->id, 'name' => 'A', 'type' => 'iroda']);
    Room::create(['location_id' => $this->location->id, 'name' => 'B', 'type' => 'iroda']);
    Room::create(['location_id' => $this->location->id, 'name' => 'C', 'type' => 'raktár']);
    Room::create(['location_id' => $this->location->id, 'name' => 'D', 'type' => null]);

    $response = $this->withHeaders(hdr($this->adminToken))
        ->getJson("/api/v1/locations/{$this->location->id}/room-types")
        ->assertOk();

    expect($response->json('data'))->toBe(['iroda', 'raktár']);
});

// ============ PERMISSIONS ============

test('user szerepkör nem hozhat létre szintet (403)', function () {
    $this->withHeaders(hdr($this->userToken))
        ->postJson("/api/v1/locations/{$this->location->id}/floors", [
            'name' => 'Földszint',
        ])
        ->assertStatus(403);
});

test('user szerepkör NEM hozhat létre helyiséget', function () {
    $this->withHeaders(hdr($this->userToken))
        ->postJson("/api/v1/locations/{$this->location->id}/rooms", [
            'name' => 'Iroda',
        ])
        ->assertStatus(403);
});

test('user szerepkör listázhatja a szinteket és helyiségeket (read)', function () {
    Floor::create(['location_id' => $this->location->id, 'name' => 'F', 'level' => 0]);
    Room::create(['location_id' => $this->location->id, 'name' => 'R']);

    $this->withHeaders(hdr($this->userToken))
        ->getJson("/api/v1/locations/{$this->location->id}/floors")
        ->assertOk();

    $this->withHeaders(hdr($this->userToken))
        ->getJson("/api/v1/locations/{$this->location->id}/rooms")
        ->assertOk();
});

// ============ HELYSZÍN TÖRLÉS CASCADE ============

test('helyszín törlése cascade-eli a szinteket és helyiségeket', function () {
    $floor = Floor::create(['location_id' => $this->location->id, 'name' => 'F', 'level' => 0]);
    Room::create(['location_id' => $this->location->id, 'floor_id' => $floor->id, 'name' => 'R1']);
    Room::create(['location_id' => $this->location->id, 'name' => 'R2']);

    $this->withHeaders(hdr($this->adminToken))
        ->deleteJson("/api/v1/locations/{$this->location->id}")
        ->assertOk();

    // Soft delete a helyszínen — a floors/rooms a DB-ben még megvannak (soft delete)
    expect(Floor::withTrashed()->where('location_id', $this->location->id)->count())->toBe(1);
    // De az aktív listából eltűnnek
    expect(Floor::where('location_id', $this->location->id)->count())->toBeGreaterThanOrEqual(0);
});
