<?php

use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
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
});

// ========== VÉDETT ROUTES (auth:sanctum) ==========
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    // --- Auth ---
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
    Route::get('/auth/user', [AuthController::class, 'user'])->name('auth.user');

    // --- Felhasználók ---
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);

    // --- Szerepkörök ---
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions']);
    Route::delete('/roles/{role}', [RoleController::class, 'destroy']);

    // --- Engedélyek (a mátrix felépítéséhez) ---
    Route::get('/permissions', [RoleController::class, 'permissions']);

});
