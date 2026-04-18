<?php

use App\Http\Controllers\Api\OrganizationController;
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

    // --- Szervezet-váltás ---
    Route::post('/auth/switch-organization', [AuthController::class, 'switchOrganization'])->name('auth.switch-organization');

    // --- Szuper-admin: impersonation ---
    Route::post('/auth/enter-organization/{orgId}', [AuthController::class, 'enterOrganization'])
        ->whereNumber('orgId')
        ->name('auth.enter-organization');
    Route::post('/auth/exit-organization', [AuthController::class, 'exitOrganization'])->name('auth.exit-organization');

    // --- Szervezetek ---
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/admin/organizations-tree', [OrganizationController::class, 'tree'])->name('organizations.tree');
});
