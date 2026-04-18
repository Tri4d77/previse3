<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     *
     * Bejelentkezés email + jelszó.
     * SPA mód: session cookie-t kap.
     * Mobil mód: bearer tokent kap (device_name megadásakor).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Rate limiting: max 5 kísérlet / perc / email
        $throttleKey = Str::lower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => [__('auth.throttle', ['seconds' => $seconds])],
            ]);
        }

        // Felhasználó keresése
        $user = User::with(['organization', 'role.permissions'])
            ->where('email', $request->email)
            ->first();

        // Hibás email vagy jelszó
        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Inaktív felhasználó
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('auth.inactive')],
            ]);
        }

        // Inaktív szervezet
        if (! $user->organization->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('auth.organization_inactive')],
            ]);
        }

        // Email nem megerősítve (meghívó nem elfogadva)
        if (is_null($user->email_verified_at)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.unverified')],
            ]);
        }

        // Rate limiter reset sikeres bejelentkezéskor
        RateLimiter::clear($throttleKey);

        // Utolsó bejelentkezés frissítése
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Token mód (mobil) vagy session mód (SPA)
        $token = null;
        if ($request->has('device_name')) {
            // Mobil: bearer token létrehozása
            $token = $user->createToken($request->device_name)->plainTextToken;
        } elseif ($request->hasSession()) {
            // SPA: session regenerálás
            Auth::login($user);
            $request->session()->regenerate();
        }

        $response = [
            'data' => [
                'user' => new UserResource($user),
            ],
        ];

        if ($token) {
            $response['data']['token'] = $token;
        }

        return response()->json($response);
    }

    /**
     * POST /api/v1/auth/verify-password
     *
     * Jelenlegi felhasználó jelszavának ellenőrzése (lockscreen feloldáshoz).
     * A felhasználó bejelentkezve marad, nem generál új tokent.
     */
    public function verifyPassword(Request $request): JsonResponse
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

        return response()->json([
            'message' => 'OK',
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     *
     * Kijelentkezés az aktuális eszközről.
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->user()->currentAccessToken()) {
            // Token mód: aktuális token törlése
            $request->user()->currentAccessToken()->delete();
        } else {
            // SPA mód: session törlés
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => __('auth.logged_out')]);
    }

    /**
     * POST /api/v1/auth/logout-all
     *
     * Kijelentkezés minden eszközről (összes token törlése).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Összes token törlése
        $request->user()->tokens()->delete();

        // Session törlés
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => __('auth.logged_out_all')]);
    }

    /**
     * GET /api/v1/auth/user
     *
     * Bejelentkezett felhasználó adatai.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load(['organization', 'role.permissions', 'groups']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * POST /api/v1/auth/forgot-password
     *
     * Jelszó-visszaállítás kérése email-ben.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Mindig sikert jelzünk (biztonsági okokból nem áruljuk el, hogy létezik-e az email)
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'message' => __('auth.reset_link_sent'),
        ]);
    }

    /**
     * POST /api/v1/auth/reset-password
     *
     * Új jelszó beállítása token alapján.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                ])->setRememberToken(Str::random(60));

                $user->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => __('auth.password_reset_success'),
        ]);
    }

    /**
     * POST /api/v1/auth/accept-invitation
     *
     * Meghívó elfogadása és jelszó beállítása.
     */
    public function acceptInvitation(AcceptInvitationRequest $request): JsonResponse
    {
        $user = User::where('invitation_token', $request->token)
            ->whereNull('email_verified_at')
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => [__('auth.invitation_invalid')],
            ]);
        }

        // Meghívó lejárt-e (7 nap)
        if ($user->invitation_sent_at && $user->invitation_sent_at->diffInDays(now()) > 7) {
            throw ValidationException::withMessages([
                'token' => [__('auth.invitation_expired')],
            ]);
        }

        // Jelszó beállítása és fiók aktiválása
        $user->update([
            'password' => $request->password,
            'email_verified_at' => now(),
            'invitation_token' => null,
            'invitation_sent_at' => null,
            'is_active' => true,
        ]);

        // Beállítások létrehozása
        $user->getOrCreateSettings();

        return response()->json([
            'message' => __('auth.invitation_accepted'),
        ]);
    }

    /**
     * GET /api/v1/auth/invitation/{token}
     *
     * Meghívó adatainak lekérése (a frontend ennek alapján jeleníti meg a felhasználó nevét, szervezetét).
     */
    public function invitationInfo(string $token): JsonResponse
    {
        $user = User::with(['organization', 'role'])
            ->where('invitation_token', $token)
            ->whereNull('email_verified_at')
            ->first();

        if (! $user) {
            return response()->json([
                'message' => __('auth.invitation_invalid'),
            ], 404);
        }

        // Lejárat ellenőrzés
        $expired = $user->invitation_sent_at && $user->invitation_sent_at->diffInDays(now()) > 7;

        return response()->json([
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'organization' => $user->organization->name,
                'role' => $user->role->name,
                'expired' => $expired,
            ],
        ]);
    }
}
