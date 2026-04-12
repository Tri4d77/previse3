<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Previse v2
|--------------------------------------------------------------------------
|
| Prefix: /api/v1/
| Minden route automatikusan a v1 prefix alá kerül.
|
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

// Auth routes (publikus) - Fázis 1-ben implementáljuk
Route::prefix('v1/auth')->group(function () {
    // POST /api/v1/auth/login
    // POST /api/v1/auth/logout
    // POST /api/v1/auth/forgot-password
    // POST /api/v1/auth/reset-password
    // POST /api/v1/auth/accept-invitation
});

// Védett route-ok (auth:sanctum) - Fázisonként bővítjük
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Bejelentkezett felhasználó
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
