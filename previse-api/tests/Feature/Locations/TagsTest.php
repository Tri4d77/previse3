<?php

use App\Models\Location;
use App\Models\LocationTag;
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

    $this->location = Location::create([
        'organization_id' => $this->org->id, 'code' => 'LOC-001', 'name' => 'Bldg',
    ]);

    $this->adminToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@xy.hu', 'password' => 'Pass1234!',
    ])->json('data.token');
    $this->userToken = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@xy.hu', 'password' => 'Pass1234!',
    ])->json('data.token');
});

function tagHdr(string $t): array
{
    return ['Authorization' => "Bearer {$t}"];
}

// ============ KATALÓGUS ============

test('admin címkét hozhat létre', function () {
    $this->withHeaders(tagHdr($this->adminToken))
        ->postJson('/api/v1/location-tags', ['name' => 'VIP', 'color' => 'amber'])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'VIP')
        ->assertJsonPath('data.color', 'amber');
});

test('érvénytelen szín elutasítva', function () {
    $this->withHeaders(tagHdr($this->adminToken))
        ->postJson('/api/v1/location-tags', ['name' => 'X', 'color' => 'turquoise'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('color');
});

test('duplikált címke név elutasítva', function () {
    LocationTag::create(['organization_id' => $this->org->id, 'name' => 'VIP', 'color' => 'red']);

    $this->withHeaders(tagHdr($this->adminToken))
        ->postJson('/api/v1/location-tags', ['name' => 'VIP', 'color' => 'green'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('user szerepkör nem hozhat létre címkét', function () {
    $this->withHeaders(tagHdr($this->userToken))
        ->postJson('/api/v1/location-tags', ['name' => 'X', 'color' => 'red'])
        ->assertStatus(403);
});

test('user szerepkör listázhatja a címkéket', function () {
    LocationTag::create(['organization_id' => $this->org->id, 'name' => 'X', 'color' => 'red']);

    $this->withHeaders(tagHdr($this->userToken))
        ->getJson('/api/v1/location-tags')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('címke szerkeszthető', function () {
    $tag = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'X', 'color' => 'red']);

    $this->withHeaders(tagHdr($this->adminToken))
        ->putJson("/api/v1/location-tags/{$tag->id}", ['name' => 'Y', 'color' => 'blue'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Y')
        ->assertJsonPath('data.color', 'blue');
});

test('címke törölhető (helyszínhez rendelt pivot is törlődik)', function () {
    $tag = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'X', 'color' => 'red']);
    $this->location->tags()->attach($tag->id);

    $this->withHeaders(tagHdr($this->adminToken))
        ->deleteJson("/api/v1/location-tags/{$tag->id}")
        ->assertOk();

    expect(LocationTag::find($tag->id))->toBeNull();
    expect($this->location->tags()->count())->toBe(0);
});

test('reorder beállítja a sort_order-t', function () {
    $a = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'A', 'color' => 'red', 'sort_order' => 5]);
    $b = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'B', 'color' => 'blue', 'sort_order' => 6]);
    $c = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'C', 'color' => 'green', 'sort_order' => 7]);

    $this->withHeaders(tagHdr($this->adminToken))
        ->postJson('/api/v1/location-tags/reorder', ['ids' => [$c->id, $a->id, $b->id]])
        ->assertOk();

    expect(LocationTag::find($c->id)->sort_order)->toBe(0);
    expect(LocationTag::find($a->id)->sort_order)->toBe(1);
    expect(LocationTag::find($b->id)->sort_order)->toBe(2);
});

test('idegen szervezet címkéjét nem lehet szerkeszteni', function () {
    $otherOrg = Organization::create([
        'type' => 'subscriber', 'name' => 'AB', 'slug' => 'ab',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    $foreignTag = LocationTag::create([
        'organization_id' => $otherOrg->id, 'name' => 'F', 'color' => 'red',
    ]);

    $this->withHeaders(tagHdr($this->adminToken))
        ->putJson("/api/v1/location-tags/{$foreignTag->id}", ['name' => 'Z'])
        ->assertStatus(403);
});

// ============ HOZZÁRENDELÉS ============

test('helyszínhez címkék rendelhetőek (sync)', function () {
    $a = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'A', 'color' => 'red']);
    $b = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'B', 'color' => 'blue']);

    $this->withHeaders(tagHdr($this->adminToken))
        ->putJson("/api/v1/locations/{$this->location->id}/tags", ['tag_ids' => [$a->id, $b->id]])
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($this->location->tags()->count())->toBe(2);
});

test('sync teljes csere — meglévő hozzárendelések felülíródnak', function () {
    $a = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'A', 'color' => 'red']);
    $b = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'B', 'color' => 'blue']);
    $c = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'C', 'color' => 'green']);
    $this->location->tags()->attach([$a->id, $b->id]);

    $this->withHeaders(tagHdr($this->adminToken))
        ->putJson("/api/v1/locations/{$this->location->id}/tags", ['tag_ids' => [$c->id]])
        ->assertOk();

    $ids = $this->location->tags()->pluck('location_tags.id')->all();
    expect($ids)->toBe([$c->id]);
});

test('üres tag_ids → minden hozzárendelés törlődik', function () {
    $a = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'A', 'color' => 'red']);
    $this->location->tags()->attach($a->id);

    $this->withHeaders(tagHdr($this->adminToken))
        ->putJson("/api/v1/locations/{$this->location->id}/tags", ['tag_ids' => []])
        ->assertOk();

    expect($this->location->tags()->count())->toBe(0);
});

test('idegen szervezet címkéje nem rendelhető a saját helyszínhez', function () {
    $otherOrg = Organization::create([
        'type' => 'subscriber', 'name' => 'AB', 'slug' => 'ab',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    $foreignTag = LocationTag::create([
        'organization_id' => $otherOrg->id, 'name' => 'F', 'color' => 'red',
    ]);

    $this->withHeaders(tagHdr($this->adminToken))
        ->putJson("/api/v1/locations/{$this->location->id}/tags", ['tag_ids' => [$foreignTag->id]])
        ->assertOk();

    // A pivot üres maradt — a saját org címkéi közül nem volt egyezés
    expect($this->location->tags()->count())->toBe(0);
});

test('user szerepkör nem rendelhet címkét helyszínhez', function () {
    $a = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'A', 'color' => 'red']);

    $this->withHeaders(tagHdr($this->userToken))
        ->putJson("/api/v1/locations/{$this->location->id}/tags", ['tag_ids' => [$a->id]])
        ->assertStatus(403);
});

test('user szerepkör listázhatja a helyszín címkéit', function () {
    $a = LocationTag::create(['organization_id' => $this->org->id, 'name' => 'A', 'color' => 'red']);
    $this->location->tags()->attach($a->id);

    $this->withHeaders(tagHdr($this->userToken))
        ->getJson("/api/v1/locations/{$this->location->id}/tags")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
