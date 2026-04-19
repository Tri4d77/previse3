<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Profil- és biztonsági beállítások endpointjai.
 *
 * M4-ben ide kerül:
 *  - Jelszó módosítás (régi + új + megerősítés)
 *  - Aktív sessionök (tokenek) listázása
 *  - Adott session revokálása
 *  - Minden más eszköz kijelentkeztetése
 *
 * M5 (2FA), M6 (email-change), M7 (fiók-törlés) további endpointokat fog hozzáadni.
 */
class ProfileController extends Controller
{
    /**
     * PUT /api/v1/profile/password
     *
     * Jelszó módosítás a bejelentkezett user-hez.
     *
     * Validáció:
     *  - current_password: szükséges, a jelenlegi jelszónak kell lennie
     *  - password: új jelszó, min. 10 karakter, kis+nagybetű, szám (Password::defaults())
     *  - password_confirmation: egyezzen a password-del
     *  - új ne egyezzen a régivel
     *
     * Opcionális: `logout_other_devices = true` esetén minden más tokent revokál.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'logout_other_devices' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('auth.password_same_as_old')],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        // Opcionálisan: minden más eszköz kijelentkeztetése
        if ($request->boolean('logout_other_devices')) {
            $currentTokenId = $user->currentAccessToken()?->id;
            $user->tokens()
                ->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))
                ->delete();
        }

        return response()->json([
            'message' => __('auth.password_changed'),
        ]);
    }

    /**
     * GET /api/v1/profile/sessions
     *
     * A bejelentkezett user aktív tokenjei (sessionjei), rendezve az utolsó használat szerint.
     * Az aktuális token jelölve (`is_current: true`).
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $tokens = $user->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        $sessions = $tokens->map(function ($token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'ip_address' => $token->ip_address,
                'user_agent' => $token->user_agent,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
                'is_current' => $token->id === $currentTokenId,
                'is_impersonation' => ! is_null($token->context_organization_id),
            ];
        })->values();

        return response()->json(['data' => $sessions]);
    }

    /**
     * DELETE /api/v1/profile/sessions/{id}
     *
     * Adott session (token) revokálása. Az aktuális saját sessiont NEM lehet így revokálni
     * (arra a /auth/logout való).
     */
    public function destroySession(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        if ($id === $currentTokenId) {
            return response()->json([
                'message' => __('auth.cannot_revoke_current_session'),
            ], 422);
        }

        $deleted = $user->tokens()->where('id', $id)->delete();

        if (! $deleted) {
            return response()->json([
                'message' => __('auth.session_not_found'),
            ], 404);
        }

        return response()->json([
            'message' => __('auth.session_revoked'),
        ]);
    }

    /**
     * DELETE /api/v1/profile/sessions/others
     *
     * Minden más eszközről kijelentkeztetés (az aktuális tokent meghagyja).
     */
    public function destroyOtherSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $count = $user->tokens()
            ->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->delete();

        return response()->json([
            'message' => __('auth.other_sessions_revoked'),
            'revoked_count' => $count,
        ]);
    }
}
