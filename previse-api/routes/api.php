<?php

use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Previse v2
|--------------------------------------------------------------------------
*/

// Health check (publikus)
Route::get('/v1/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
        'version' => '2.0.0',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// ========== AUTH ROUTES (publikus) ==========
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
    Route::post('/accept-invitation', [AuthController::class, 'acceptInvitation'])->name('auth.accept-invitation');
    Route::get('/invitation/{token}', [AuthController::class, 'invitationInfo'])->name('auth.invitation-info');

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
});
