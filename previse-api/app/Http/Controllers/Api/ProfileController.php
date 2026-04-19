<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EmailChangeConfirmMail;
use App\Mail\EmailChangeNoticeMail;
use App\Models\User;
use App\Services\SecurityNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
    public function __construct(
        private SecurityNotificationService $securityNotify,
    ) {}

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

        // Biztonsági értesítés email
        $this->securityNotify->passwordChanged($user, $request);

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

    // ============== EMAIL CHANGE FLOW (M6) ==============

    /**
     * POST /api/v1/profile/email/change
     *
     * Email-cím változtatási kérés indítása:
     *  - jelszó megerősítés
     *  - új email egyedi (még nem foglalt)
     *  - nem azonos a jelenlegivel
     *  - pending_email + email_change_token + email_change_sent_at elmentve
     *  - megerősítő link kimegy az ÚJ címre
     *  - tájékoztató email kimegy a RÉGI címre (biztonsági értesítés)
     */
    public function requestEmailChange(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'new_email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('auth.password')],
            ]);
        }

        if (strtolower($validated['new_email']) === strtolower($user->email)) {
            throw ValidationException::withMessages([
                'new_email' => [__('auth.email_same_as_current')],
            ]);
        }

        $token = Str::random(64);
        $user->update([
            'pending_email' => $validated['new_email'],
            'email_change_token' => $token,
            'email_change_sent_at' => now(),
        ]);

        $expiresInMinutes = (int) config('auth.email_change_expires_minutes', 60);
        $locale = $user->settings?->locale ?? app()->getLocale();
        $confirmUrl = rtrim(config('app.frontend_url'), '/') . '/email/confirm/' . $token;

        // Megerősítő email az ÚJ címre
        try {
            Mail::send(new EmailChangeConfirmMail(
                userName: $user->name ?? '',
                oldEmail: $user->email,
                newEmail: $validated['new_email'],
                confirmUrl: $confirmUrl,
                expiresInMinutes: $expiresInMinutes,
                locale: $locale,
            ));
        } catch (\Throwable $e) {
            Log::error('Email change confirm mail failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        // Tájékoztató email a RÉGI címre
        try {
            Mail::send(new EmailChangeNoticeMail(
                recipientEmail: $user->email,
                userName: $user->name ?? '',
                newEmail: $validated['new_email'],
                requestedAt: now()->toDateTimeString(),
                ipAddress: (string) $request->ip(),
                locale: $locale,
            ));
        } catch (\Throwable $e) {
            Log::error('Email change notice mail failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => __('auth.email_change_requested'),
            'pending_email' => $validated['new_email'],
        ]);
    }

    /**
     * POST /api/v1/profile/email/confirm
     *
     * Megerősítő tokennel élesíti az új email-t.
     * NEM szükséges authentikáció (a link és token az egyetlen igazolás).
     */
    public function confirmEmailChange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $user = User::where('email_change_token', $validated['token'])
            ->whereNotNull('pending_email')
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => [__('auth.email_change_invalid')],
            ]);
        }

        $expiresInMinutes = (int) config('auth.email_change_expires_minutes', 60);
        if (! $user->email_change_sent_at || $user->email_change_sent_at->diffInMinutes(now()) > $expiresInMinutes) {
            throw ValidationException::withMessages([
                'token' => [__('auth.email_change_expired')],
            ]);
        }

        // Még egyszer ellenőrizzük, hogy az új email ne legyen foglalt
        $newEmail = $user->pending_email;
        if (User::where('email', $newEmail)->where('id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'new_email' => [__('auth.email_already_taken')],
            ]);
        }

        $oldEmail = $user->email;
        $user->update([
            'email' => $newEmail,
            'pending_email' => null,
            'email_change_token' => null,
            'email_change_sent_at' => null,
            // új címet megerősítettnek tekintjük
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        // Biztonsági értesítés a régi címre
        $this->securityNotify->emailChanged($user, $oldEmail, $request);

        return response()->json([
            'message' => __('auth.email_change_confirmed'),
            'email' => $newEmail,
        ]);
    }

    /**
     * DELETE /api/v1/profile/email/pending
     *
     * Folyamatban lévő email-változtatás visszavonása (auth szükséges).
     */
    public function cancelEmailChange(Request $request): JsonResponse
    {
        $user = $request->user();

        if (is_null($user->pending_email)) {
            return response()->json(['message' => __('auth.email_change_nothing_pending')], 422);
        }

        $user->update([
            'pending_email' => null,
            'email_change_token' => null,
            'email_change_sent_at' => null,
        ]);

        return response()->json(['message' => __('auth.email_change_cancelled')]);
    }
}
