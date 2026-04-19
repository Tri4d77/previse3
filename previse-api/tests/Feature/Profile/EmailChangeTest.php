<?php

use App\Mail\EmailChangeConfirmMail;
use App\Mail\EmailChangeNoticeMail;
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
        'name' => 'Teszt',
        'email' => 'teszt@xy.hu',
        'password' => 'MyPass123!',
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    Membership::create([
        'user_id' => $this->user->id,
        'organization_id' => $this->org->id,
        'role_id' => $this->role->id,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'teszt@xy.hu',
        'password' => 'MyPass123!',
    ]);
    $this->token = $login->json('data.token');
});

function hdrs(string $t): array
{
    return ['Authorization' => "Bearer {$t}"];
}

// ============ EMAIL CHANGE REQUEST ============

test('email-change request pending_email-t beállít és 2 email-t kiküld', function () {
    Mail::fake();

    $this->withHeaders(hdrs($this->token))
        ->postJson('/api/v1/profile/email/change', [
            'password' => 'MyPass123!',
            'new_email' => 'uj@xy.hu',
        ])
        ->assertOk()
        ->assertJsonPath('pending_email', 'uj@xy.hu');

    $this->user->refresh();
    expect($this->user->pending_email)->toBe('uj@xy.hu');
    expect($this->user->email_change_token)->not->toBeNull();
    expect($this->user->email)->toBe('teszt@xy.hu'); // még nem vált

    // Megerősítő az ÚJ címre
    Mail::assertQueued(EmailChangeConfirmMail::class, fn (EmailChangeConfirmMail $m) => $m->hasTo('uj@xy.hu'));
    // Tájékoztató a RÉGI címre
    Mail::assertQueued(EmailChangeNoticeMail::class, fn (EmailChangeNoticeMail $m) => $m->hasTo('teszt@xy.hu'));
});

test('email-change rossz jelszóval 422', function () {
    Mail::fake();

    $this->withHeaders(hdrs($this->token))
        ->postJson('/api/v1/profile/email/change', [
            'password' => 'RosszJelszo!',
            'new_email' => 'uj@xy.hu',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    $this->user->refresh();
    expect($this->user->pending_email)->toBeNull();
    Mail::assertNothingQueued();
});

test('email-change ugyanaz email esetén 422', function () {
    $this->withHeaders(hdrs($this->token))
        ->postJson('/api/v1/profile/email/change', [
            'password' => 'MyPass123!',
            'new_email' => 'teszt@xy.hu',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('new_email');
});

test('email-change foglalt email esetén 422', function () {
    User::create([
        'name' => 'Más', 'email' => 'mas@xy.hu', 'password' => 'X',
        'is_active' => true, 'email_verified_at' => now(),
    ]);

    $this->withHeaders(hdrs($this->token))
        ->postJson('/api/v1/profile/email/change', [
            'password' => 'MyPass123!',
            'new_email' => 'mas@xy.hu',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('new_email');
});

// ============ EMAIL CHANGE CONFIRM ============

test('email-change confirm helyes tokennel érvényesíti az új email-t', function () {
    $this->withHeaders(hdrs($this->token))
        ->postJson('/api/v1/profile/email/change', [
            'password' => 'MyPass123!',
            'new_email' => 'uj@xy.hu',
        ])->assertOk();

    $this->user->refresh();
    $token = $this->user->email_change_token;

    Mail::fake();
    $this->postJson('/api/v1/auth/email/confirm', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('email', 'uj@xy.hu');

    $this->user->refresh();
    expect($this->user->email)->toBe('uj@xy.hu');
    expect($this->user->pending_email)->toBeNull();
    expect($this->user->email_change_token)->toBeNull();

    // Biztonsági értesítés a régi címre
    Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $m) => $m->hasTo('teszt@xy.hu'));
});

test('email-change confirm érvénytelen tokennel 422', function () {
    $this->postJson('/api/v1/auth/email/confirm', ['token' => 'ervenytelen'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('token');
});

test('email-change confirm lejárt tokennel 422', function () {
    $this->user->update([
        'pending_email' => 'uj@xy.hu',
        'email_change_token' => 'lejart-token',
        'email_change_sent_at' => now()->subHours(3), // default 60 perc
    ]);

    $this->postJson('/api/v1/auth/email/confirm', ['token' => 'lejart-token'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('token');

    $this->user->refresh();
    expect($this->user->email)->toBe('teszt@xy.hu'); // nem vált
});

test('email-change confirm: ha közben foglalt lett az email, 422', function () {
    $this->withHeaders(hdrs($this->token))
        ->postJson('/api/v1/profile/email/change', [
            'password' => 'MyPass123!',
            'new_email' => 'uj@xy.hu',
        ])->assertOk();

    // Valaki más közben regisztrálja ugyanezt az email-t
    User::create([
        'name' => 'Verseny', 'email' => 'uj@xy.hu', 'password' => 'X',
        'is_active' => true, 'email_verified_at' => now(),
    ]);

    $this->user->refresh();
    $token = $this->user->email_change_token;

    $this->postJson('/api/v1/auth/email/confirm', ['token' => $token])
        ->assertStatus(422);
});

// ============ EMAIL CHANGE CANCEL ============

test('pending email változtatás visszavonható', function () {
    $this->withHeaders(hdrs($this->token))
        ->postJson('/api/v1/profile/email/change', [
            'password' => 'MyPass123!',
            'new_email' => 'uj@xy.hu',
        ])->assertOk();

    $this->withHeaders(hdrs($this->token))
        ->deleteJson('/api/v1/profile/email/pending')
        ->assertOk();

    $this->user->refresh();
    expect($this->user->pending_email)->toBeNull();
    expect($this->user->email_change_token)->toBeNull();
});

test('cancel ha nincs pending: 422', function () {
    $this->withHeaders(hdrs($this->token))
        ->deleteJson('/api/v1/profile/email/pending')
        ->assertStatus(422);
});

// ============ SECURITY NOTIFICATIONS ============

test('jelszó módosítás után biztonsági email kiküldve', function () {
    Mail::fake();

    $this->withHeaders(hdrs($this->token))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'MyPass123!',
            'password' => 'NewPass456!',
            'password_confirmation' => 'NewPass456!',
        ])
        ->assertOk();

    Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $m) =>
        $m->hasTo('teszt@xy.hu') && $m->eventKey === 'password_changed'
    );
});

test('2FA bekapcsolás után biztonsági email kiküldve', function () {
    // Enable
    $this->withHeaders(hdrs($this->token))
        ->postJson('/api/v1/profile/2fa/enable')
        ->assertOk();

    $this->user->refresh();
    $code = app(\App\Services\TwoFactorService::class)->generateCurrentCode($this->user->two_factor_secret);

    Mail::fake();
    $this->withHeaders(hdrs($this->token))
        ->postJson('/api/v1/profile/2fa/confirm', ['code' => $code])
        ->assertOk();

    Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $m) => $m->eventKey === 'two_factor_enabled');
});

test('2FA kikapcsolás után biztonsági email kiküldve', function () {
    $svc = app(\App\Services\TwoFactorService::class);
    $this->user->update([
        'two_factor_secret' => $svc->generateSecret(),
        'two_factor_confirmed_at' => now(),
    ]);

    Mail::fake();
    $this->withHeaders(hdrs($this->token))
        ->postJson('/api/v1/profile/2fa/disable', ['password' => 'MyPass123!'])
        ->assertOk();

    Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $m) => $m->eventKey === 'two_factor_disabled');
});

test('új eszközről bejelentkezéskor biztonsági email', function () {
    // Első login (setup-beli) már IP+UA rögzített. Most szimuláljunk egy új IP-ről login-t.
    Mail::fake();
    $this->user->tokens()->delete(); // töröljük az összes korábbi tokent
    // Új IP-ről login
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99', 'HTTP_USER_AGENT' => 'Mozilla/5.0 Firefox/120.0'])
        ->postJson('/api/v1/auth/login', [
            'email' => 'teszt@xy.hu',
            'password' => 'MyPass123!',
        ])->assertOk();

    Mail::assertQueued(SecurityAlertMail::class, fn (SecurityAlertMail $m) => $m->eventKey === 'new_device_login');
});

test('ismert eszközről bejelentkezéskor NINCS new device email', function () {
    // Az első login már létrehozott egy tokent az alapértelmezett IP+UA-val.
    // Ha újra bejelentkezünk ugyanarról → nem küld email-t.
    Mail::fake();
    $this->postJson('/api/v1/auth/login', [
        'email' => 'teszt@xy.hu',
        'password' => 'MyPass123!',
    ])->assertOk();

    Mail::assertNotQueued(SecurityAlertMail::class, fn (SecurityAlertMail $m) => $m->eventKey === 'new_device_login');
});
