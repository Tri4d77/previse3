<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Controller;
use App\Mail\EmailChangeConfirmMail;
use App\Mail\EmailChangeNoticeMail;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\SecurityNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    // ============== SZERVEZETBŐL KILÉPÉS (M7) ==============

    /**
     * POST /api/v1/profile/memberships/{id}/leave
     *
     * Kilépés egy adott szervezetből (saját tagságból).
     *
     * - Ha ez az utolsó aktív tagság → 422 'last_active_membership' kódú hibaüzenet,
     *   a frontend-nek modal-ban meg kell erősítenie: vagy ide megnyomja a fiók-törlést,
     *   vagy megszakítja.
     * - Ha a user Platform super-admin és ez az utolsó platform tagság → tiltjuk.
     * - Ha a user a szervezet utolsó adminja → többi tag értesítést kap emailben.
     */
    public function leaveOrganization(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        /** @var Membership|null $membership */
        $membership = $user->memberships()->with(['organization', 'role'])->find($id);

        if (! $membership) {
            return response()->json(['message' => __('auth.membership_not_found')], 404);
        }

        // Platform super-admin: ha ez a platform tagsága, és ő az egyetlen platform admin → blokk
        if ($membership->organization->type === Organization::TYPE_PLATFORM) {
            $otherPlatformAdmins = Membership::where('organization_id', $membership->organization_id)
                ->where('id', '!=', $membership->id)
                ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
                ->where('is_active', true)
                ->whereNotNull('joined_at')
                ->count();

            if ($otherPlatformAdmins === 0) {
                return response()->json([
                    'message' => __('auth.cannot_leave_last_super_admin'),
                    'code' => 'last_super_admin',
                ], 422);
            }
        }

        // Utolsó aktív tagság?
        $activeCount = $user->activeMemberships()->count();
        if ($activeCount <= 1) {
            return response()->json([
                'message' => __('auth.cannot_leave_last_membership'),
                'code' => 'last_active_membership',
            ], 422);
        }

        // Ha utolsó admin a szervezetben → többi tagot értesítjük
        $wasLastAdmin = $this->isLastAdminOfOrganization($membership);

        DB::transaction(function () use ($membership) {
            $membership->update(['is_active' => false]);
            $membership->delete();
        });

        if ($wasLastAdmin) {
            $this->notifyOtherMembersAdminLeft($membership, $user);
        }

        return response()->json(['message' => __('auth.left_organization')]);
    }

    // ============== FIÓK MEGSZÜNTETÉSE (M7) ==============

    /**
     * DELETE /api/v1/profile
     *
     * Fiók-törlés kezdeményezése (30 napos grace period).
     * Jelszó megerősítés kötelező. Az összes tagság deaktiválódik, tokenek törlődnek,
     * a user kijelentkezik minden eszközről és NEM tud újra bejelentkezni.
     * A grace alatt a user visszajelentkezhet a "cancel deletion" flow-val.
     *
     * Egyetlen Platform super-admin: tiltjuk (előbb új szuper-admint kell kinevezni).
     */
    public function deleteAccount(Request $request): JsonResponse
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

        if ($user->isScheduledForDeletion()) {
            return response()->json(['message' => __('auth.account_already_scheduled_for_deletion')], 422);
        }

        // Egyetlen Platform super-admin védelem
        if ($user->isSuperAdmin()) {
            $platformAdminCount = Membership::query()
                ->whereHas('organization', fn ($q) => $q->where('type', Organization::TYPE_PLATFORM))
                ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
                ->where('is_active', true)
                ->whereNotNull('joined_at')
                ->count();

            if ($platformAdminCount <= 1) {
                return response()->json([
                    'message' => __('auth.cannot_delete_last_super_admin'),
                    'code' => 'last_super_admin',
                ], 422);
            }
        }

        // Utolsó admin-e bármelyik szervezetében? Ezeket a szervezeteket el kell értesíteni
        $lastAdminMemberships = $user->activeMemberships()
            ->with(['organization', 'role'])
            ->get()
            ->filter(fn (Membership $m) => $this->isLastAdminOfOrganization($m));

        $graceDays = (int) config('auth.account_deletion_grace_days', 30);

        DB::transaction(function () use ($user, $graceDays) {
            $user->update(['scheduled_deletion_at' => now()->addDays($graceDays)]);
            // Tagságok deaktiválása + soft delete
            $user->memberships()->update(['is_active' => false]);
            $user->memberships()->delete();
            // Minden token revokálva → azonnali kijelentkezés
            $user->tokens()->delete();
        });

        // Minden szervezetben, ahol a user volt az utolsó admin → értesítjük a többieket
        foreach ($lastAdminMemberships as $m) {
            $this->notifyOtherMembersAdminLeft($m, $user);
        }

        // Értesítés magának a user-nek
        $this->securityNotify->accountDeletionScheduled($user, $request);

        return response()->json([
            'message' => __('auth.account_deletion_scheduled'),
            'scheduled_deletion_at' => $user->refresh()->scheduled_deletion_at?->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/profile/delete/cancel
     *
     * Fiók-törlés visszavonása a grace alatt. A tagságok, amiket a delete-kor
     * soft-deleted-ünk ÉS deaktiváltunk, visszaállnak aktív állapotba. Így a user
     * a cancel után normálisan be tud lépni mindenhova, ahol a törlés előtt tag volt.
     *
     * Kivételek (ahol NEM állítjuk vissza):
     *  - olyan szervezet, amit időközben megszüntettek (deleted_at, status=terminated)
     *  - tagságok, amiket a törlés előtt is inaktívvá tett az org admin
     *    (ezek `deleted_at` előtt már is_active=false voltak, csak soft-delete-re kerültek
     *     az account deletion során; ezeket nem különböztetjük meg a deaktivált-vs-törölt
     *     között, így biztonsági okokból AKTIVÁLJUK mindet — az org-admin újra tudja
     *     kapcsolni, ha kell)
     */
    public function cancelAccountDeletion(Request $request, AuthController $authController): JsonResponse
    {
        $user = $request->user();

        if (! $user->isScheduledForDeletion()) {
            return response()->json(['message' => __('auth.account_not_scheduled_for_deletion')], 422);
        }

        DB::transaction(function () use ($user) {
            $user->update(['scheduled_deletion_at' => null]);

            // Soft-deleted tagságok iterálása + visszaállítás, ha az org még él.
            // (Az iteratív megoldás biztosabb, mint a `whereHas + restore` query.)
            $user->memberships()
                ->onlyTrashed()
                ->with('organization')
                ->get()
                ->each(function (Membership $membership) {
                    $org = $membership->organization;
                    // Csak akkor állítjuk vissza, ha a szervezet létezik és nem megszűnt
                    if (! $org || $org->status === Organization::STATUS_TERMINATED) {
                        return;
                    }
                    $membership->restore();
                    $membership->update(['is_active' => true]);
                });
        });

        // A jelenlegi (deletion-cancel ability) tokent revokáljuk,
        // hogy a user a új, teljes tokennel folytassa.
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        }

        $this->securityNotify->accountDeletionCancelled($user, $request);

        // A user-nek egyben visszaadunk egy igazi login választ,
        // hogy ne kelljen még egyszer bejelentkeznie.
        return $authController->issueLoginSuccessResponse($user->fresh(), $request);
    }

    // ============== SEGÉD METÓDUSOK ==============

    /**
     * A megadott tagság a szervezet EGYETLEN admin role-ú aktív tagsága-e?
     */
    private function isLastAdminOfOrganization(Membership $membership): bool
    {
        if (! $membership->role || $membership->role->slug !== 'admin') {
            return false;
        }

        $otherActiveAdmins = Membership::query()
            ->where('organization_id', $membership->organization_id)
            ->where('id', '!=', $membership->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->where('is_active', true)
            ->whereNotNull('joined_at')
            ->count();

        return $otherActiveAdmins === 0;
    }

    /**
     * Értesíti a szervezet többi aktív tagját, hogy az utolsó admin távozott.
     */
    private function notifyOtherMembersAdminLeft(Membership $membership, User $departedUser): void
    {
        $others = Membership::query()
            ->with('user')
            ->where('organization_id', $membership->organization_id)
            ->where('user_id', '!=', $departedUser->id)
            ->where('is_active', true)
            ->whereNotNull('joined_at')
            ->get();

        $this->securityNotify->adminLeftOrganization(
            $others,
            $membership->organization->name,
            $departedUser->name ?? $departedUser->email,
        );
    }
}
