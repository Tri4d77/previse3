<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Auth\AuthController;
use App\Models\AuthEvent;
use App\Models\Membership;
use App\Services\AuthEventLogger;
use App\Services\SecurityNotificationService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Kétfaktoros hitelesítés (2FA / TOTP) endpointjai.
 *
 * Állapot:
 *  - Ha two_factor_secret NULL → 2FA nincs beállítva
 *  - Ha secret kitöltve, two_factor_confirmed_at NULL → setup alatt (még nem aktív)
 *  - Ha secret kitöltve, two_factor_confirmed_at kitöltve → 2FA aktív
 *
 * Lépések:
 *  1. POST /profile/2fa/enable      → secret + QR (setup állapot)
 *  2. POST /profile/2fa/confirm     → első TOTP kód megerősítés → aktiválás + recovery kódok
 *  3. POST /profile/2fa/disable     → kikapcsolás jelszó megerősítéssel (töröl minden 2FA mezőt)
 *  4. GET  /profile/2fa/recovery-codes                    → létező kódok (csak aktív 2FA)
 *  5. POST /profile/2fa/recovery-codes/regenerate         → új 8 kód
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
        private SecurityNotificationService $securityNotify,
        private AuthEventLogger $authEvents,
    ) {}

    /**
     * POST /api/v1/profile/2fa/status
     *
     * Aktuális 2FA állapot lekérdezése.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
            'setup_in_progress' => ! is_null($user->two_factor_secret) && is_null($user->two_factor_confirmed_at),
            'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/profile/2fa/enable
     *
     * Új secret generálás (setup kezdete). A secret-et adatbázisba mentjük,
     * de two_factor_confirmed_at még NULL → a következő confirm lépés aktiválja.
     *
     * Ha a user már aktív 2FA-val rendelkezik, előbb kell disable-elnie.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => __('auth.2fa_already_enabled'),
            ], 422);
        }

        $secret = $this->twoFactor->generateSecret();
        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        $otpauthUrl = $this->twoFactor->otpauthUrl($user, $secret);
        $qrSvg = $this->twoFactor->qrCodeSvg($otpauthUrl);

        return response()->json([
            'data' => [
                'secret' => $secret,
                'otpauth_url' => $otpauthUrl,
                'qr_code_svg' => $qrSvg,
            ],
        ]);
    }

    /**
     * POST /api/v1/profile/2fa/confirm
     *
     * Az első TOTP kód megerősítésével aktiváljuk a 2FA-t.
     * Ha sikeres, generáljuk a 8 recovery kódot.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (is_null($user->two_factor_secret)) {
            return response()->json([
                'message' => __('auth.2fa_setup_not_started'),
            ], 422);
        }

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => __('auth.2fa_already_enabled'),
            ], 422);
        }

        if (! $this->twoFactor->verifyCode($user->two_factor_secret, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => [__('auth.2fa_invalid_code')],
            ]);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->update([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        // Biztonsági értesítés
        $this->securityNotify->twoFactorEnabled($user, $request);
        $this->authEvents->log(AuthEvent::TWO_FACTOR_ENABLED, user: $user);

        return response()->json([
            'data' => [
                'recovery_codes' => $recoveryCodes,
            ],
            'message' => __('auth.2fa_enabled'),
        ]);
    }

    /**
     * POST /api/v1/profile/2fa/disable
     *
     * Kikapcsolás jelszó megerősítéssel.
     */
    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('auth.password')],
            ]);
        }

        if (! $user->hasTwoFactorEnabled() && is_null($user->two_factor_secret)) {
            return response()->json([
                'message' => __('auth.2fa_not_enabled'),
            ], 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->securityNotify->twoFactorDisabled($user, $request);
        $this->authEvents->log(AuthEvent::TWO_FACTOR_DISABLED, user: $user);

        return response()->json([
            'message' => __('auth.2fa_disabled'),
        ]);
    }

    /**
     * GET /api/v1/profile/2fa/recovery-codes
     *
     * Aktív recovery kódok listája. Csak aktív 2FA-val rendelkező usernek.
     */
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json(['message' => __('auth.2fa_not_enabled')], 422);
        }

        return response()->json([
            'data' => $user->two_factor_recovery_codes ?? [],
        ]);
    }

    /**
     * POST /api/v1/profile/2fa/recovery-codes/regenerate
     *
     * Új recovery kódok generálása. Csak aktív 2FA-val.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json(['message' => __('auth.2fa_not_enabled')], 422);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();
        $user->update(['two_factor_recovery_codes' => $codes]);

        $this->authEvents->log(AuthEvent::TWO_FACTOR_RECOVERY_REGENERATED, user: $user);

        return response()->json([
            'data' => $codes,
            'message' => __('auth.2fa_recovery_codes_regenerated'),
        ]);
    }

    /**
     * POST /api/v1/auth/2fa/challenge
     *
     * Login után (ha a user 2FA-val rendelkezik) TOTP kód vagy recovery kód ellenőrzése.
     *
     * Header: Authorization: Bearer <challenge_token>
     * Body:   { "code": "123456" } vagy { "recovery_code": "ABCDE-FGHIJ" }
     *
     * Sikeres esetén a challenge tokent revokáljuk, és visszahívjuk az AuthController
     * login utáni logikáját (szervezet-választó / direkt belépés).
     */
    public function challenge(Request $request, AuthController $authController): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'size:6'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        if (empty($validated['code']) && empty($validated['recovery_code'])) {
            throw ValidationException::withMessages([
                'code' => [__('auth.2fa_code_required')],
            ]);
        }

        $user = $request->user();
        $token = $user->currentAccessToken();

        // Csak a dedikált 2fa-challenge tokennel hívható (a login állít elő egy
        // rövid életű tokent '2fa:verify' ability-vel; normál login tokenek '*' ability-vel
        // rendelkeznek, ezeket itt kifejezetten tiltjuk).
        $abilities = (array) ($token?->abilities ?? []);
        $hasAll = in_array('*', $abilities, true);
        if (! $token || $hasAll || ! in_array('2fa:verify', $abilities, true)) {
            return response()->json(['message' => __('auth.forbidden')], 403);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return response()->json(['message' => __('auth.2fa_not_enabled')], 422);
        }

        // Kód ellenőrzés (TOTP vagy recovery)
        $valid = false;
        $usedRecovery = false;
        if (! empty($validated['code'])) {
            $valid = $this->twoFactor->verifyCode($user->two_factor_secret, $validated['code']);
        } else {
            $newCodes = $this->twoFactor->consumeRecoveryCode(
                $user->two_factor_recovery_codes ?? [],
                $validated['recovery_code'],
            );
            if ($newCodes !== null) {
                $user->update(['two_factor_recovery_codes' => $newCodes]);
                $valid = true;
                $usedRecovery = true;
            }
        }

        if (! $valid) {
            $this->authEvents->log(AuthEvent::TWO_FACTOR_CHALLENGE_FAILED, user: $user);
            throw ValidationException::withMessages([
                'code' => [__('auth.2fa_invalid_code')],
            ]);
        }

        if ($usedRecovery) {
            $this->authEvents->log(AuthEvent::TWO_FACTOR_RECOVERY_USED, user: $user, metadata: [
                'remaining_codes' => count($user->two_factor_recovery_codes ?? []),
            ]);
        }

        // Challenge token törlés
        $token->delete();

        // Login utáni flow folytatása (szervezet-választó vagy direkt login)
        return $authController->issueLoginSuccessResponse($user, $request);
    }
}
