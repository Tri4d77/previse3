<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     *
     * Válasz-kezelés:
     * - Ha 1 aktív tagság VAGY default_organization_id → automatikus belépés az adott szervezetbe (token + user)
     * - Ha több aktív tagság és nincs default → "requires_organization_selection": true,
     *   a frontend-nek meg kell jelenítenie a szervezet-választót, és majd
     *   a /auth/select-organization endpointot hívja
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Rate limiting
        $throttleKey = Str::lower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => [__('auth.throttle', ['seconds' => $seconds])],
            ]);
        }

        // User keresése
        $user = User::where('email', $request->email)->first();

        // Hibás email vagy jelszó
        if (! $user || is_null($user->password) || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Inaktív user
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('auth.inactive')],
            ]);
        }

        // Email nem megerősítve (meghívó nem elfogadva)
        if (is_null($user->email_verified_at)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.unverified')],
            ]);
        }

        // Aktív tagságok
        $activeMemberships = $user->activeMemberships()
            ->with(['organization', 'role'])
            ->get();

        if ($activeMemberships->isEmpty()) {
            throw ValidationException::withMessages([
                'email' => [__('auth.no_active_membership')],
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Utolsó bejelentkezés
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Szervezet-választó logika
        $settings = $user->getOrCreateSettings();
        $defaultOrgId = $settings->default_organization_id;

        // Ha egyetlen aktív tagság → automatikus belépés
        if ($activeMemberships->count() === 1) {
            return $this->issueTokenForMembership($user, $activeMemberships->first(), $request);
        }

        // Ha default_organization_id be van állítva → oda lép be
        if ($defaultOrgId) {
            $defaultMembership = $activeMemberships->firstWhere('organization_id', $defaultOrgId);
            if ($defaultMembership) {
                return $this->issueTokenForMembership($user, $defaultMembership, $request);
            }
        }

        // Több aktív tagság, nincs default → szervezet-választó szükséges
        // Egy ideiglenes tokent adunk vissza, ami csak a select-organization-höz használható
        $selectionToken = $user->createToken(
            'organization-selection',
            ['organization:select'],
            now()->addMinutes(10)
        )->plainTextToken;

        return response()->json([
            'requires_organization_selection' => true,
            'selection_token' => $selectionToken,
            'memberships' => $activeMemberships->map(fn (Membership $m) => [
                'id' => $m->id,
                'organization' => [
                    'id' => $m->organization->id,
                    'name' => $m->organization->name,
                    'type' => $m->organization->type,
                ],
                'role' => [
                    'id' => $m->role->id,
                    'name' => $m->role->name,
                    'slug' => $m->role->slug,
                ],
                'last_active_at' => $m->last_active_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * POST /api/v1/auth/select-organization
     *
     * A bejelentkezés után szervezet-választás (ha a user több aktív tagsággal rendelkezik
     * és nincs default beállítva).
     *
     * A selection_token-nel hívható, membership_id megadásával.
     */
    public function selectOrganization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'membership_id' => ['required', 'integer', 'exists:memberships,id'],
        ]);

        $user = $request->user();

        // A selection tokennek legalább `organization:select` ability kell legyen
        $token = $user->currentAccessToken();
        if (! $token || ! $token->can('organization:select')) {
            return response()->json(['message' => __('auth.forbidden')], 403);
        }

        $membership = Membership::with(['organization', 'role'])
            ->where('id', $validated['membership_id'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('joined_at')
            ->first();

        if (! $membership) {
            throw ValidationException::withMessages([
                'membership_id' => [__('auth.invalid_membership')],
            ]);
        }

        // A selection token törlése
        $token->delete();

        // Valódi token kiállítása az adott tagságra
        return $this->issueTokenForMembership($user, $membership, $request);
    }

    /**
     * POST /api/v1/auth/switch-organization
     *
     * Szervezet-váltás bejelentkezett állapotban. A régi token törlődik,
     * új jön létre a megadott tagsággal.
     */
    public function switchOrganization(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'membership_id' => ['required', 'integer', 'exists:memberships,id'],
        ]);

        $user = $request->user();

        $membership = Membership::with(['organization', 'role'])
            ->where('id', $validated['membership_id'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNotNull('joined_at')
            ->first();

        if (! $membership) {
            throw ValidationException::withMessages([
                'membership_id' => [__('auth.invalid_membership')],
            ]);
        }

        // Régi token törlése (biztonsági okokból)
        $user->currentAccessToken()->delete();

        return $this->issueTokenForMembership($user, $membership, $request);
    }

    /**
     * POST /api/v1/auth/enter-organization/{org_id}
     *
     * Szuper-admin belép egy másik szervezet kontextusába (impersonation).
     */
    public function enterOrganization(Request $request, int $orgId): JsonResponse
    {
        $user = $request->user();

        // Csak szuper-admin használhatja
        if (! $user->isSuperAdmin()) {
            return response()->json(['message' => __('auth.forbidden')], 403);
        }

        $organization = Organization::find($orgId);
        if (! $organization || ! $organization->is_active) {
            return response()->json(['message' => 'A szervezet nem található vagy inaktív.'], 404);
        }

        // Régi token törlése
        $user->currentAccessToken()->delete();

        // Új token context_organization_id-vel
        return $this->issueImpersonationToken($user, $organization, $request);
    }

    /**
     * POST /api/v1/auth/exit-organization
     *
     * Szuper-admin visszalép a Platform membership-re.
     */
    public function exitOrganization(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            return response()->json(['message' => __('auth.forbidden')], 403);
        }

        $platformMembership = $user->platformMembership();

        if (! $platformMembership) {
            return response()->json(['message' => 'Platform tagság nem található.'], 404);
        }

        // Régi token törlése
        $user->currentAccessToken()->delete();

        return $this->issueTokenForMembership($user, $platformMembership, $request);
    }

    /**
     * Token létrehozása egy adott tagsághoz.
     */
    private function issueTokenForMembership(User $user, Membership $membership, Request $request): JsonResponse
    {
        // Membership utolsó aktivitás frissítése
        $membership->update(['last_active_at' => now()]);

        $deviceName = $request->input('device_name', 'Web Browser');

        $newToken = $user->createToken($deviceName);
        $newToken->accessToken->update([
            'current_membership_id' => $membership->id,
            'context_organization_id' => null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        return response()->json([
            'data' => [
                'user' => new UserResource($user->fresh()),
                'current_membership' => $this->membershipToArray($membership->fresh(['organization', 'role.permissions'])),
                'token' => $newToken->plainTextToken,
            ],
        ]);
    }

    /**
     * Impersonation token létrehozása szuper-adminnak.
     */
    private function issueImpersonationToken(User $user, Organization $organization, Request $request): JsonResponse
    {
        $deviceName = $request->input('device_name', 'Web Browser') . ' (szuper-admin impersonation)';

        $newToken = $user->createToken($deviceName);
        $newToken->accessToken->update([
            'current_membership_id' => null,
            'context_organization_id' => $organization->id,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        return response()->json([
            'data' => [
                'user' => new UserResource($user->fresh()),
                'current_membership' => null,
                'context_organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'type' => $organization->type,
                    'slug' => $organization->slug,
                ],
                'is_super_admin_impersonation' => true,
                'token' => $newToken->plainTextToken,
            ],
        ]);
    }

    /**
     * Membership tömb reprezentáció.
     */
    private function membershipToArray(Membership $membership): array
    {
        return [
            'id' => $membership->id,
            'is_active' => $membership->is_active,
            'joined_at' => $membership->joined_at?->toIso8601String(),
            'last_active_at' => $membership->last_active_at?->toIso8601String(),
            'organization' => [
                'id' => $membership->organization->id,
                'name' => $membership->organization->name,
                'type' => $membership->organization->type,
                'slug' => $membership->organization->slug,
            ],
            'role' => [
                'id' => $membership->role->id,
                'name' => $membership->role->name,
                'slug' => $membership->role->slug,
            ],
            'permissions' => $membership->role->permissions
                ->map(fn ($p) => $p->module . '.' . $p->action)
                ->values(),
        ];
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => __('auth.logged_out')]);
    }

    /**
     * POST /api/v1/auth/logout-all
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => __('auth.logged_out_all')]);
    }

    /**
     * GET /api/v1/auth/user
     *
     * A bejelentkezett user adatai az aktuális membership / impersonation kontextussal.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        $currentMembership = null;
        $contextOrganization = null;
        $isImpersonation = false;
        $permissions = [];

        if ($token->context_organization_id) {
            // Szuper-admin impersonation
            $org = Organization::find($token->context_organization_id);
            if ($org) {
                $contextOrganization = [
                    'id' => $org->id,
                    'name' => $org->name,
                    'type' => $org->type,
                    'slug' => $org->slug,
                ];
                $isImpersonation = true;
                // Impersonation módban minden engedély
                $permissions = \App\Models\Permission::all()
                    ->map(fn ($p) => $p->module . '.' . $p->action)
                    ->values()
                    ->toArray();
            }
        } elseif ($token->current_membership_id) {
            $membership = Membership::with(['organization', 'role.permissions'])
                ->find($token->current_membership_id);
            if ($membership) {
                $currentMembership = $this->membershipToArray($membership);
                $permissions = $currentMembership['permissions']->toArray();
            }
        }

        // Az összes aktív tagság listája
        $memberships = $user->activeMemberships()
            ->with(['organization', 'role'])
            ->get()
            ->map(fn (Membership $m) => [
                'id' => $m->id,
                'organization' => [
                    'id' => $m->organization->id,
                    'name' => $m->organization->name,
                    'type' => $m->organization->type,
                ],
                'role' => [
                    'id' => $m->role->id,
                    'name' => $m->role->name,
                    'slug' => $m->role->slug,
                ],
                'last_active_at' => $m->last_active_at?->toIso8601String(),
            ])->values();

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'current_membership' => $currentMembership,
                'context_organization' => $contextOrganization,
                'is_super_admin_impersonation' => $isImpersonation,
                'is_super_admin' => $user->isSuperAdmin(),
                'memberships' => $memberships,
                'permissions' => $permissions,
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/verify-password
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

        return response()->json(['message' => 'OK']);
    }

    /**
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => __('auth.reset_link_sent')]);
    }

    /**
     * POST /api/v1/auth/reset-password
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

        return response()->json(['message' => __('auth.password_reset_success')]);
    }

    /**
     * POST /api/v1/auth/accept-invitation
     *
     * Meghívó elfogadása (membership token alapján).
     *
     * Új user esetén: jelszó + password_confirmation beállítása
     * Létező user esetén: csak a jelszó ellenőrzés (hogy ő az)
     */
    public function acceptInvitation(AcceptInvitationRequest $request): JsonResponse
    {
        $membership = Membership::with('user')
            ->where('invitation_token', $request->token)
            ->whereNull('joined_at')
            ->first();

        if (! $membership) {
            throw ValidationException::withMessages([
                'token' => [__('auth.invitation_invalid')],
            ]);
        }

        if ($membership->isInvitationExpired()) {
            throw ValidationException::withMessages([
                'token' => [__('auth.invitation_expired')],
            ]);
        }

        $user = $membership->user;

        // Ha az user még nincs aktiválva (NULL password) → most állítjuk be
        if (is_null($user->password) || is_null($user->email_verified_at)) {
            $user->update([
                'password' => $request->password,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            $user->getOrCreateSettings();
        } else {
            // Létező user: ellenőrizzük a jelszót
            if (! Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'password' => [__('auth.password')],
                ]);
            }
        }

        // Membership aktiválása
        $membership->update([
            'is_active' => true,
            'joined_at' => now(),
            'invitation_token' => null,
            'invitation_sent_at' => null,
        ]);

        return response()->json([
            'message' => __('auth.invitation_accepted'),
        ]);
    }

    /**
     * GET /api/v1/auth/invitation/{token}
     *
     * Meghívó infó lekérése (frontend megjelenítéshez).
     */
    public function invitationInfo(string $token): JsonResponse
    {
        $membership = Membership::with(['user', 'organization', 'role'])
            ->where('invitation_token', $token)
            ->whereNull('joined_at')
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => __('auth.invitation_invalid'),
            ], 404);
        }

        return response()->json([
            'data' => [
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'organization' => $membership->organization->name,
                'role' => $membership->role->name,
                'is_new_user' => is_null($membership->user->password),
                'expired' => $membership->isInvitationExpired(),
            ],
        ]);
    }
}
