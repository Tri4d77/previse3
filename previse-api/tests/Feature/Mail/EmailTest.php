<?php

use App\Mail\InvitationMail;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSetting;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Platform (szervezet-hierarchia gyökere)
    $this->platform = Organization::create([
        'type' => 'platform', 'name' => 'Platform', 'slug' => 'platform', 'is_active' => true,
    ]);

    // Subscriber szervezet, ahova meghívunk
    $this->org = Organization::create([
        'type' => 'subscriber', 'name' => 'XY Kft.', 'slug' => 'xy-kft',
        'is_active' => true, 'parent_id' => $this->platform->id,
    ]);
    $this->adminRole = Role::create([
        'organization_id' => $this->org->id,
        'name' => 'Admin', 'slug' => 'admin', 'is_system' => true,
    ]);
    $this->adminRole->permissions()->sync(Permission::pluck('id'));

    // Org-admin user, akivel meghívunk
    $this->admin = User::create([
        'name' => 'Org Admin',
        'email' => 'admin@xy.hu',
        'password' => 'Admin123!',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->admin->id,
        'organization_id' => $this->org->id,
        'role_id' => $this->adminRole->id,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    // Bejelentkezés → token
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@xy.hu',
        'password' => 'Admin123!',
    ]);
    $this->orgToken = $login->json('data.token');
});

// ============ INVITATION EMAIL TESZTEK ============

test('új user meghívása InvitationMail-t küld', function () {
    Mail::fake();

    $this->withHeader('Authorization', "Bearer {$this->orgToken}")
        ->postJson('/api/v1/memberships', [
            'name' => 'Új Használó',
            'email' => 'uj@test.hu',
            'role_id' => $this->adminRole->id,
        ])
        ->assertStatus(201);

    // ShouldQueue → assertQueued
    Mail::assertQueued(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo('uj@test.hu'));
});

test('létező user meghívása InvitationMail-t küld', function () {
    Mail::fake();

    User::create([
        'name' => 'Létező',
        'email' => 'letezo@test.hu',
        'password' => 'Valami123!',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $this->withHeader('Authorization', "Bearer {$this->orgToken}")
        ->postJson('/api/v1/memberships', [
            'email' => 'letezo@test.hu',
            'role_id' => $this->adminRole->id,
        ])
        ->assertStatus(201);

    Mail::assertQueued(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo('letezo@test.hu'));
});

test('meghívó újraküldése új InvitationMail-t küld', function () {
    Mail::fake();

    $inviteResponse = $this->withHeader('Authorization', "Bearer {$this->orgToken}")
        ->postJson('/api/v1/memberships', [
            'name' => 'Új',
            'email' => 'resend@test.hu',
            'role_id' => $this->adminRole->id,
        ]);

    Mail::assertQueued(InvitationMail::class, 1);

    $membershipId = $inviteResponse->json('data.id');
    $this->withHeader('Authorization', "Bearer {$this->orgToken}")
        ->postJson("/api/v1/memberships/{$membershipId}/resend-invitation")
        ->assertOk();

    Mail::assertQueued(InvitationMail::class, 2);
});

test('InvitationMail - új user esetén az új-user gombfeliratot használja (HU)', function () {
    $newUser = User::create([
        'name' => 'Új',
        'email' => 'uj@test.hu',
        'password' => null,  // új user jelzés
        'is_active' => false,
    ]);

    $membership = Membership::create([
        'user_id' => $newUser->id,
        'organization_id' => $this->org->id,
        'role_id' => $this->adminRole->id,
        'is_active' => false,
        'invitation_token' => 'test-token-abc',
        'invitation_sent_at' => now(),
    ]);

    $mail = new InvitationMail(
        membership: $membership,
        invitationUrl: 'http://localhost:5173/invitation/test-token-abc',
        inviterName: 'Org Admin',
        expiresInDays: 7,
    );

    $rendered = $mail->render();

    expect($rendered)->toContain('test-token-abc')
        ->and($rendered)->toContain('Org Admin')
        ->and($rendered)->toContain('XY Kft.')
        ->and($rendered)->toContain('Meghívó elfogadása')
        ->and($rendered)->not->toContain('Tagság megerősítése');
});

test('InvitationMail - létező user esetén a megerősítés gombfeliratot használja (HU)', function () {
    $existingUser = User::create([
        'name' => 'Létező',
        'email' => 'letezo@test.hu',
        'password' => 'Hash',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $membership = Membership::create([
        'user_id' => $existingUser->id,
        'organization_id' => $this->org->id,
        'role_id' => $this->adminRole->id,
        'is_active' => false,
        'invitation_token' => 'test-token-xyz',
        'invitation_sent_at' => now(),
    ]);

    $mail = new InvitationMail(
        membership: $membership,
        invitationUrl: 'http://localhost:5173/invitation/test-token-xyz',
        inviterName: 'Org Admin',
        expiresInDays: 7,
    );

    expect($mail->render())->toContain('Tagság megerősítése')
        ->and($mail->render())->not->toContain('Meghívó elfogadása');
});

test('InvitationMail locale a user beállítása szerint (EN)', function () {
    $user = User::create([
        'name' => 'English User',
        'email' => 'en@test.hu',
        'password' => null,
        'is_active' => false,
    ]);
    UserSetting::updateOrCreate(['user_id' => $user->id], ['locale' => 'en']);

    $membership = Membership::create([
        'user_id' => $user->id,
        'organization_id' => $this->org->id,
        'role_id' => $this->adminRole->id,
        'is_active' => false,
        'invitation_token' => 'en-token',
        'invitation_sent_at' => now(),
    ]);

    $rendered = (new InvitationMail(
        membership: $membership,
        invitationUrl: 'http://localhost:5173/invitation/en-token',
    ))->render();

    expect($rendered)->toContain('Accept invitation')
        ->and($rendered)->toContain('has invited you');
});

// ============ PASSWORD RESET TESZTEK ============

test('forgot-password ResetPasswordNotification-t küld', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'admin@xy.hu',
    ])->assertOk();

    Notification::assertSentTo($this->admin, ResetPasswordNotification::class);
});

test('ResetPasswordNotification a helyes URL-t és tokent tartalmazza (HU)', function () {
    $notification = new ResetPasswordNotification('abc123plaintoken');
    $rendered = $notification->toMail($this->admin)->render();

    expect($rendered)->toContain('abc123plaintoken')
        ->and($rendered)->toContain('/reset-password?token=')
        ->and($rendered)->toContain(urlencode('admin@xy.hu'))
        ->and($rendered)->toContain('Új jelszó beállítása');
});

test('ResetPasswordNotification EN locale a user beállítása szerint', function () {
    UserSetting::updateOrCreate(['user_id' => $this->admin->id], ['locale' => 'en']);
    $this->admin->load('settings');

    $rendered = (new ResetPasswordNotification('token-xyz'))
        ->toMail($this->admin)
        ->render();

    expect($rendered)->toContain('Set new password')
        ->and($rendered)->toContain('password reset request');
});
