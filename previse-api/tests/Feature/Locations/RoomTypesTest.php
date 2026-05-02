<?php

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoomType;
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
    Membership::create([
        'user_id' => $this->admin->id, 'organization_id' => $this->org->id,
        'role_id' => $adminRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);
    $this->user = User::create([
        'name' => 'User', 'email' => 'user@xy.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->user->id, 'organization_id' => $this->org->id,
        'role_id' => $userRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);

    $this->adminToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@xy.hu', 'password' => 'Pass1234!',
    ])->json('data.token');
    $this->userToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@xy.hu', 'password' => 'Pass1234!',
    ])->json('data.token');
});

function rtHdr(string $t): array
{
    return ['Authorization' => "Bearer {$t}"];
}

test('új subscriber szervezet kap default helyiség-típusokat', function () {
    expect(RoomType::where('organization_id', $this->org->id)->count())->toBe(8);
    $names = RoomType::where('organization_id', $this->org->id)
        ->orderBy('sort_order')
        ->pluck('name')
        ->all();
    expect($names)->toBe(['Iroda', 'Tárgyaló', 'Raktár', 'Folyosó', 'Mosdó', 'Konyha', 'Műszaki', 'Egyéb']);
});

test('admin lekérheti a helyiség-típus listát', function () {
    $response = $this->withHeaders(rtHdr($this->adminToken))
        ->getJson('/api/v1/room-types');

    $response->assertOk()->assertJsonCount(8, 'data');
});

test('admin új helyiség-típust hozhat létre', function () {
    $this->withHeaders(rtHdr($this->adminToken))
        ->postJson('/api/v1/room-types', ['name' => 'Szerver szoba'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Szerver szoba');
});

test('duplikált helyiség-típus név elutasítva', function () {
    $this->withHeaders(rtHdr($this->adminToken))
        ->postJson('/api/v1/room-types', ['name' => 'Iroda'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('user szerepkör nem hozhat létre helyiség-típust', function () {
    $this->withHeaders(rtHdr($this->userToken))
        ->postJson('/api/v1/room-types', ['name' => 'Új típus'])
        ->assertStatus(403);
});

test('user szerepkör listázhatja a típusokat', function () {
    $this->withHeaders(rtHdr($this->userToken))
        ->getJson('/api/v1/room-types')
        ->assertOk();
});

test('helyiség-típus szerkeszthető', function () {
    $type = RoomType::where('organization_id', $this->org->id)->first();

    $this->withHeaders(rtHdr($this->adminToken))
        ->putJson("/api/v1/room-types/{$type->id}", ['name' => 'Átnevezett'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Átnevezett');
});

test('helyiség-típus törölhető (a meglévő helyiségek érintetlenek maradnak)', function () {
    $type = RoomType::where('organization_id', $this->org->id)->first();

    $this->withHeaders(rtHdr($this->adminToken))
        ->deleteJson("/api/v1/room-types/{$type->id}")
        ->assertOk();

    expect(RoomType::find($type->id))->toBeNull();
});

test('idegen szervezet típusához nem fér hozzá', function () {
    $otherOrg = Organization::create([
        'type' => 'subscriber', 'name' => 'AB', 'slug' => 'ab',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    OrganizationRoleSeeder::seed($otherOrg);
    $otherType = RoomType::where('organization_id', $otherOrg->id)->first();

    $this->withHeaders(rtHdr($this->adminToken))
        ->putJson("/api/v1/room-types/{$otherType->id}", ['name' => 'Nem-én'])
        ->assertStatus(403);
});

test('user_settings locations_floors_sort és locations_rooms_sort mentehető', function () {
    $this->withHeaders(rtHdr($this->adminToken))
        ->putJson('/api/v1/settings', [
            'locations_floors_sort' => 'name',
            'locations_rooms_sort' => 'number',
        ])
        ->assertOk()
        ->assertJsonPath('data.locations_floors_sort', 'name')
        ->assertJsonPath('data.locations_rooms_sort', 'number');
});

test('user_settings érvénytelen sort érték elutasítva', function () {
    $this->withHeaders(rtHdr($this->adminToken))
        ->putJson('/api/v1/settings', ['locations_floors_sort' => 'invalid'])
        ->assertStatus(422);
});
