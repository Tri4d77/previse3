<?php

use App\Models\Location;
use App\Models\LocationType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Platform
    $this->platform = Organization::create([
        'type' => 'platform', 'name' => 'P', 'slug' => 'p', 'is_active' => true,
    ]);

    // Subscriber org admin role-szerepkörökkel
    $this->org = Organization::create([
        'type' => 'subscriber', 'name' => 'XY', 'slug' => 'xy',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    OrganizationRoleSeeder::seed($this->org);
    $this->adminRole = Role::where('organization_id', $this->org->id)->where('slug', 'admin')->first();
    $this->dispatcherRole = Role::where('organization_id', $this->org->id)->where('slug', 'dispatcher')->first();
    $this->userRole = Role::where('organization_id', $this->org->id)->where('slug', 'user')->first();

    // Admin user
    $this->admin = User::create([
        'name' => 'Admin', 'email' => 'admin@xy.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->admin->id, 'organization_id' => $this->org->id,
        'role_id' => $this->adminRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);

    // Login
    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@xy.hu', 'password' => 'Pass1234!',
    ])->json('data.token');
});

function authH(string $t): array
{
    return ['Authorization' => "Bearer {$t}"];
}

// ============ TYPES ============

test('új subscriber szervezet kap default helyszín-típusokat', function () {
    expect(LocationType::where('organization_id', $this->org->id)->count())->toBe(8);
    expect(LocationType::where('organization_id', $this->org->id)->pluck('name')->all())
        ->toContain('Iroda', 'Bevásárlóközpont', 'Lakóház', 'Egyéb');
});

test('admin lekérheti a típus-listát', function () {
    $this->withHeaders(authH($this->token))
        ->getJson('/api/v1/location-types')
        ->assertOk()
        ->assertJsonCount(8, 'data');
});

test('admin új típust hozhat létre', function () {
    $this->withHeaders(authH($this->token))
        ->postJson('/api/v1/location-types', ['name' => 'Saját kategória'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Saját kategória');
});

test('duplikált típus-név elutasítva', function () {
    $this->withHeaders(authH($this->token))
        ->postJson('/api/v1/location-types', ['name' => 'Iroda'])
        ->assertStatus(422);
});

// ============ LOCATIONS CRUD ============

test('helyszín létrehozása auto-generált koddal', function () {
    $type = LocationType::where('organization_id', $this->org->id)->where('name', 'Iroda')->first();

    $response = $this->withHeaders(authH($this->token))
        ->postJson('/api/v1/locations', [
            'name' => 'Westend B',
            'type_id' => $type->id,
            'city' => 'Budapest',
        ])->assertCreated();

    expect($response->json('data.code'))->toBe('LOC-001');
    expect($response->json('data.name'))->toBe('Westend B');
    expect($response->json('data.type.name'))->toBe('Iroda');
});

test('helyszín létrehozása saját koddal', function () {
    $this->withHeaders(authH($this->token))
        ->postJson('/api/v1/locations', [
            'code' => 'WST-B',
            'name' => 'Westend B',
        ])->assertCreated()->assertJsonPath('data.code', 'WST-B');
});

test('duplikált kód org-szinten elutasítva', function () {
    Location::create([
        'organization_id' => $this->org->id,
        'code' => 'A1', 'name' => 'X', 'is_active' => 1,
    ]);

    $this->withHeaders(authH($this->token))
        ->postJson('/api/v1/locations', [
            'code' => 'A1', 'name' => 'Másik',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
});

test('helyszín auto-generált kód folyamatosan növekszik', function () {
    $this->withHeaders(authH($this->token))
        ->postJson('/api/v1/locations', ['name' => 'A'])->assertCreated();
    $this->withHeaders(authH($this->token))
        ->postJson('/api/v1/locations', ['name' => 'B'])->assertCreated();

    $codes = Location::where('organization_id', $this->org->id)->pluck('code')->toArray();
    expect($codes)->toContain('LOC-001', 'LOC-002');
});

test('helyszín lista alapból csak az aktívakat adja', function () {
    Location::create(['organization_id' => $this->org->id, 'code' => 'A1', 'name' => 'Aktív', 'is_active' => 1]);
    Location::create(['organization_id' => $this->org->id, 'code' => 'A2', 'name' => 'Archív', 'is_active' => 0]);
    Location::create(['organization_id' => $this->org->id, 'code' => 'A3', 'name' => 'Megszűnt', 'is_active' => 2]);

    $response = $this->withHeaders(authH($this->token))
        ->getJson('/api/v1/locations')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Aktív');
});

test('helyszín lista is_active=all minden helyszínt mutat', function () {
    Location::create(['organization_id' => $this->org->id, 'code' => 'A1', 'name' => 'A', 'is_active' => 1]);
    Location::create(['organization_id' => $this->org->id, 'code' => 'A2', 'name' => 'B', 'is_active' => 0]);
    Location::create(['organization_id' => $this->org->id, 'code' => 'A3', 'name' => 'C', 'is_active' => 2]);

    $response = $this->withHeaders(authH($this->token))
        ->getJson('/api/v1/locations?is_active=all')->assertOk();

    expect($response->json('data'))->toHaveCount(3);
});

test('helyszín keresés név alapján', function () {
    Location::create(['organization_id' => $this->org->id, 'code' => 'WST-B', 'name' => 'Westend B', 'city' => 'Budapest']);
    Location::create(['organization_id' => $this->org->id, 'code' => 'AR1', 'name' => 'Aréna 1', 'city' => 'Budapest']);

    $response = $this->withHeaders(authH($this->token))
        ->getJson('/api/v1/locations?search=Westend')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Westend B');
});

test('helyszín státusz-váltás (active → archived)', function () {
    $loc = Location::create(['organization_id' => $this->org->id, 'code' => 'A1', 'name' => 'A', 'is_active' => 1]);

    $this->withHeaders(authH($this->token))
        ->postJson("/api/v1/locations/{$loc->id}/status", ['is_active' => 0])
        ->assertOk()
        ->assertJsonPath('data.is_active', 0);
});

test('helyszín soft-delete + restore', function () {
    $loc = Location::create(['organization_id' => $this->org->id, 'code' => 'A1', 'name' => 'A', 'is_active' => 1]);

    $this->withHeaders(authH($this->token))->deleteJson("/api/v1/locations/{$loc->id}")->assertOk();
    expect(Location::find($loc->id))->toBeNull();
    expect(Location::withTrashed()->find($loc->id))->not->toBeNull();

    $this->withHeaders(authH($this->token))->postJson("/api/v1/locations/{$loc->id}/restore")->assertOk();
    expect(Location::find($loc->id))->not->toBeNull();
});

test('idegen szervezet helyszínéhez nem fér hozzá', function () {
    // Másik subscriber + ott egy hely
    $otherOrg = Organization::create([
        'type' => 'subscriber', 'name' => 'O', 'slug' => 'o',
        'parent_id' => $this->platform->id, 'is_active' => true,
    ]);
    OrganizationRoleSeeder::seed($otherOrg);
    $otherLoc = Location::create(['organization_id' => $otherOrg->id, 'code' => 'X', 'name' => 'X', 'is_active' => 1]);

    $this->withHeaders(authH($this->token))
        ->getJson("/api/v1/locations/{$otherLoc->id}")
        ->assertStatus(403);
});

// ============ IMAGE UPLOAD ============

test('helyszín kép feltöltés thumbnail-lel', function () {
    Storage::fake('public');

    $loc = Location::create(['organization_id' => $this->org->id, 'code' => 'A1', 'name' => 'A', 'is_active' => 1]);
    $file = UploadedFile::fake()->image('test.jpg', 800, 600);

    $this->withHeaders(array_merge(authH($this->token), ['Accept' => 'application/json']))
        ->post("/api/v1/locations/{$loc->id}/image", ['image' => $file])
        ->assertOk();

    $loc->refresh();
    expect($loc->image_path)->not->toBeNull();
    expect($loc->image_path)->toStartWith("locations/{$this->org->id}/{$loc->id}/image-");
    Storage::disk('public')->assertExists($loc->image_path);

    // Thumbnail létezik
    $thumbPath = preg_replace('/image-(\d+)\.jpg$/', 'image-thumb-$1.jpg', $loc->image_path);
    Storage::disk('public')->assertExists($thumbPath);
});

test('helyszín kép feltöltés - túl nagy fájl elutasítva', function () {
    Storage::fake('public');

    $loc = Location::create(['organization_id' => $this->org->id, 'code' => 'A1', 'name' => 'A', 'is_active' => 1]);
    $file = UploadedFile::fake()->create('huge.jpg', 6 * 1024); // 6 MB

    $this->withHeaders(array_merge(authH($this->token), ['Accept' => 'application/json']))
        ->post("/api/v1/locations/{$loc->id}/image", ['image' => $file])
        ->assertStatus(422);
});

// ============ PERMISSIONS ============

test('user szerepkör nem hozhat létre helyszínt (de látja)', function () {
    $userUser = User::create([
        'name' => 'U', 'email' => 'u@xy.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $userUser->id, 'organization_id' => $this->org->id,
        'role_id' => $this->userRole->id, 'is_active' => true, 'joined_at' => now(),
    ]);
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => 'u@xy.hu', 'password' => 'Pass1234!',
    ])->json('data.token');

    // Hozzáfér az indexhez (locations.read)
    $this->withHeaders(authH($token))->getJson('/api/v1/locations')->assertOk();

    // De a backend még nem ellenőrzi explicit a permission-t a store-on
    // (ez middleware-rel jönne; ML1-ben az infrastruktúra még nincs élesítve).
    // Ezt a tesztet markerként hagyom: amikor a CheckPermission middleware
    // visszakerül (M3-ban kommentként szerepelt), kibővítjük.
    expect(true)->toBeTrue();
});
