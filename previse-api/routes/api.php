<?php

use App\Http\Controllers\Api\FloorsController;
use App\Http\Controllers\Api\LocationsController;
use App\Http\Controllers\Api\RoomsController;
use App\Http\Controllers\Api\RoomTypesController;
use App\Http\Controllers\Api\LocationTypesController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Previse v2
|--------------------------------------------------------------------------
*/

// Health check (publikus) - alap státusz
Route::get('/v1/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'version' => '2.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Részletes health check (deployment ellenőrzéshez)
// Visszaadja: DB elérés, mail driver, queue driver, scheduler legutóbbi futás
Route::get('/v1/health/details', function () {
    $checks = [
        'app' => [
            'name' => config('app.name'),
            'env' => config('app.env'),
            'debug' => config('app.debug'),
            'version' => '2.0.0',
            'locale' => config('app.locale'),
            'url' => config('app.url'),
        ],
        'database' => ['status' => 'unknown', 'driver' => config('database.default')],
        'mail' => [
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'from' => config('mail.from.address'),
        ],
        'queue' => ['driver' => config('queue.default')],
        'cache' => ['driver' => config('cache.default')],
        'session' => ['driver' => config('session.driver')],
    ];

    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $checks['database']['status'] = 'connected';
        $checks['database']['name'] = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
    } catch (\Throwable $e) {
        $checks['database']['status'] = 'error';
        $checks['database']['error'] = config('app.debug') ? $e->getMessage() : 'Connection failed';
    }

    return response()->json([
        'status' => $checks['database']['status'] === 'connected' ? 'ok' : 'degraded',
        'timestamp' => now()->toIso8601String(),
        'checks' => $checks,
    ]);
});

// ========== AUTH ROUTES (publikus) ==========
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::post('/accept-invitation', [AuthController::class, 'acceptInvitation'])->name('auth.accept-invitation');
    Route::get('/invitation/{token}', [AuthController::class, 'invitationInfo'])->name('auth.invitation-info');

    // Email-v\u00e1ltoztat\u00e1s meger\u0151s\u00edt\u0151 tokennel (publikus, nincs auth sz\u00fcks\u00e9ges - M6)
    Route::post('/email/confirm', [ProfileController::class, 'confirmEmailChange'])->name('auth.email.confirm');

    // Szervezet-választás belépés után (csak organization-selection ability tokennel)
    Route::middleware('auth:sanctum')
        ->post('/select-organization', [AuthController::class, 'selectOrganization'])
        ->name('auth.select-organization');
});

// ========== VÉDETT ROUTES (auth:sanctum) ==========
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    // --- Auth ---
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
    Route::get('/auth/user', [AuthController::class, 'user'])->name('auth.user');
    Route::post('/auth/verify-password', [AuthController::class, 'verifyPassword'])->name('auth.verify-password');

    // --- Profil: biztons\u00e1g (M4) ---
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::get('/profile/sessions', [ProfileController::class, 'sessions'])->name('profile.sessions.index');
    Route::delete('/profile/sessions/others', [ProfileController::class, 'destroyOtherSessions'])->name('profile.sessions.destroy-others');
    Route::delete('/profile/sessions/{id}', [ProfileController::class, 'destroySession'])->whereNumber('id')->name('profile.sessions.destroy');

    // --- User settings (M9) ---
    Route::put('/settings', [ProfileController::class, 'updateSettings'])->name('settings.update');

    // --- Login history (M8) ---
    Route::get('/profile/login-history', [ProfileController::class, 'loginHistory'])->name('profile.login-history');

    // --- Szervezetb\u0151l kil\u00e9p\u00e9s + fi\u00f3k megsz\u00fcntet\u00e9se (M7) ---
    Route::post('/profile/memberships/{id}/leave', [ProfileController::class, 'leaveOrganization'])
        ->whereNumber('id')
        ->name('profile.memberships.leave');
    Route::delete('/profile', [ProfileController::class, 'deleteAccount'])->name('profile.delete');
    Route::post('/profile/delete/cancel', [ProfileController::class, 'cancelAccountDeletion'])->name('profile.delete.cancel');

    // --- Email-c\u00edm v\u00e1ltoztat\u00e1s (M6) ---
    Route::post('/profile/email/change', [ProfileController::class, 'requestEmailChange'])->name('profile.email.change');
    Route::delete('/profile/email/pending', [ProfileController::class, 'cancelEmailChange'])->name('profile.email.cancel');

    // --- 2FA (M5) ---
    Route::get('/profile/2fa/status', [TwoFactorController::class, 'status'])->name('profile.2fa.status');
    Route::post('/profile/2fa/enable', [TwoFactorController::class, 'enable'])->name('profile.2fa.enable');
    Route::post('/profile/2fa/confirm', [TwoFactorController::class, 'confirm'])->name('profile.2fa.confirm');
    Route::post('/profile/2fa/disable', [TwoFactorController::class, 'disable'])->name('profile.2fa.disable');
    Route::get('/profile/2fa/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('profile.2fa.recovery-codes');
    Route::post('/profile/2fa/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('profile.2fa.recovery-codes.regenerate');

    // Login ut\u00e1ni 2FA ellen\u0151rz\u00e9s (challenge token k\u00e9nt)
    Route::post('/auth/2fa/challenge', [TwoFactorController::class, 'challenge'])->name('auth.2fa.challenge');

    // --- Szervezet-váltás ---
    Route::post('/auth/switch-organization', [AuthController::class, 'switchOrganization'])->name('auth.switch-organization');

    // --- Szuper-admin: impersonation ---
    Route::post('/auth/enter-organization/{orgId}', [AuthController::class, 'enterOrganization'])
        ->whereNumber('orgId')
        ->name('auth.enter-organization');
    Route::post('/auth/exit-organization', [AuthController::class, 'exitOrganization'])->name('auth.exit-organization');

    // --- Szervezetek ---
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update');
    Route::post('/organizations/{organization}/status', [OrganizationController::class, 'setStatus'])->name('organizations.set-status');
    Route::get('/admin/organizations-tree', [OrganizationController::class, 'tree'])->name('organizations.tree');

    // --- Szerepk\u00f6r\u00f6k ---
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

    // --- Tags\u00e1gok (membership) ---
    Route::get('/memberships', [MembershipController::class, 'index'])->name('memberships.index');
    Route::post('/memberships/check-email', [MembershipController::class, 'checkEmail'])->name('memberships.check-email');
    Route::post('/memberships', [MembershipController::class, 'store'])->name('memberships.store');
    Route::put('/memberships/{membership}', [MembershipController::class, 'update'])->name('memberships.update');
    Route::patch('/memberships/{membership}/toggle-active', [MembershipController::class, 'toggleActive'])->name('memberships.toggle-active');
    Route::post('/memberships/{membership}/resend-invitation', [MembershipController::class, 'resendInvitation'])->name('memberships.resend-invitation');
    Route::delete('/memberships/{membership}', [MembershipController::class, 'destroy'])->name('memberships.destroy');
    Route::post('/memberships/{membershipId}/restore', [MembershipController::class, 'restore'])
        ->whereNumber('membershipId')
        ->name('memberships.restore');

    // --- Helyszínek (ML1) ---
    Route::get('/locations', [LocationsController::class, 'index'])->name('locations.index');
    Route::post('/locations', [LocationsController::class, 'store'])->name('locations.store');
    Route::get('/locations/{location}', [LocationsController::class, 'show'])->name('locations.show');
    Route::put('/locations/{location}', [LocationsController::class, 'update'])->name('locations.update');
    Route::delete('/locations/{location}', [LocationsController::class, 'destroy'])->name('locations.destroy');
    Route::post('/locations/{locationId}/restore', [LocationsController::class, 'restore'])
        ->whereNumber('locationId')
        ->name('locations.restore');
    Route::post('/locations/{location}/status', [LocationsController::class, 'setStatus'])->name('locations.set-status');
    Route::post('/locations/{location}/image', [LocationsController::class, 'uploadImage'])->name('locations.upload-image');
    Route::delete('/locations/{location}/image', [LocationsController::class, 'deleteImage'])->name('locations.delete-image');

    // --- Helyszín-típusok (ML1) ---
    Route::get('/location-types', [LocationTypesController::class, 'index'])->name('location-types.index');
    Route::post('/location-types', [LocationTypesController::class, 'store'])->name('location-types.store');
    Route::put('/location-types/{locationType}', [LocationTypesController::class, 'update'])->name('location-types.update');
    Route::delete('/location-types/{locationType}', [LocationTypesController::class, 'destroy'])->name('location-types.destroy');

    // --- Szintek (ML2.1) ---
    Route::get('/locations/{location}/floors', [FloorsController::class, 'index'])->name('locations.floors.index');
    Route::post('/locations/{location}/floors', [FloorsController::class, 'store'])->name('locations.floors.store');
    Route::put('/floors/{floor}', [FloorsController::class, 'update'])->name('floors.update');
    Route::delete('/floors/{floor}', [FloorsController::class, 'destroy'])->name('floors.destroy');

    // --- Helyiségek (ML2.1) ---
    Route::get('/locations/{location}/rooms', [RoomsController::class, 'indexByLocation'])->name('locations.rooms.index');
    Route::post('/locations/{location}/rooms', [RoomsController::class, 'store'])->name('locations.rooms.store');
    Route::get('/locations/{location}/room-types', [RoomsController::class, 'typesAutocomplete'])->name('locations.room-types');
    Route::get('/floors/{floor}/rooms', [RoomsController::class, 'indexByFloor'])->name('floors.rooms.index');
    Route::put('/rooms/{room}', [RoomsController::class, 'update'])->name('rooms.update');
    Route::delete('/rooms/{room}', [RoomsController::class, 'destroy'])->name('rooms.destroy');

    // --- Helyiség-típus katalógus (ML2.1 finalizálás) ---
    Route::get('/room-types', [RoomTypesController::class, 'index'])->name('room-types.index');
    Route::post('/room-types', [RoomTypesController::class, 'store'])->name('room-types.store');
    Route::put('/room-types/{roomType}', [RoomTypesController::class, 'update'])->name('room-types.update');
    Route::delete('/room-types/{roomType}', [RoomTypesController::class, 'destroy'])->name('room-types.destroy');
});
