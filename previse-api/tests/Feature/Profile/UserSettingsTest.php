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
        'name' => 'T', 'email' => 't@x.hu', 'password' => 'Pass1234!',
        'is_active' => true, 'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->user->id, 'organization_id' => $this->org->id,
        'role_id' => $this->role->id, 'is_active' => true, 'joined_at' => now(),
    ]);

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 't@x.hu', 'password' => 'Pass1234!',
    ])->json('data.token');
});

test('settings locale módosítható', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson('/api/v1/settings', ['locale' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.locale', 'en');

    expect($this->user->settings()->first()->locale)->toBe('en');
});

test('érvénytelen locale elutasítva', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson('/api/v1/settings', ['locale' => 'de'])
        ->assertStatus(422);
});

test('items_per_page validált tartomány', function () {
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson('/api/v1/settings', ['items_per_page' => 500])
        ->assertStatus(422);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson('/api/v1/settings', ['items_per_page' => 50])
        ->assertOk();
});

test('Accept-Language header hatással van a válasz nyelvére', function () {
    // HU user-ként rossz jelszóval próbálunk bejelentkezni, Accept-Language: en → EN válasz
    $huResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 't@x.hu', 'password' => 'Rossz!',
    ], ['Accept-Language' => 'hu']);

    $enResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'nincs@x.hu', 'password' => 'Rossz!',
    ], ['Accept-Language' => 'en']);

    expect($huResponse->json('errors.email.0'))->toContain('Hibás');
    expect($enResponse->json('errors.email.0'))->toContain('Invalid');
});
