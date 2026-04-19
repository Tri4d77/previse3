<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MembershipController extends Controller
{
    /**
     * GET /api/v1/memberships
     *
     * Szervezet tagjainak listája (membership-alapon).
     *
     * A listában az aktuális szervezet (vagy szuper-admin impersonation target)
     * összes tagsága látszik - ideértve a pending (még nem elfogadott) és
     * inaktív tagságokat is.
     *
     * A szuper-admin impersonation módban nem jelenik meg saját tagságként
     * (mert nincs valódi membership rekord, csak context_organization_id).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $this->getCurrentOrgId($user);

        if (! $orgId) {
            return response()->json(['data' => [], 'meta' => $this->emptyMeta()]);
        }

        $query = Membership::with(['user', 'role', 'organization'])
            ->where('organization_id', $orgId);

        // Törölt tagságok megjelenítése
        if ($request->boolean('include_deleted')) {
            $query->withTrashed();
        }

        // Szűrők
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('role', fn ($q) => $q->where('slug', $request->role));
        }

        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->where('is_active', true)->whereNotNull('joined_at');
                    break;
                case 'inactive':
                    $query->where('is_active', false)->whereNotNull('joined_at');
                    break;
                case 'pending':
                    $query->whereNull('joined_at')->whereNotNull('invitation_token');
                    break;
            }
        }

        // Rendezés
        $sortField = $request->input('sort', 'created_at');
        $sortDir = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'last_active_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir);
        }

        $perPage = min($request->input('per_page', 25), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->items() ? array_map(
                fn ($m) => $this->formatMembership($m),
                $paginated->items()
            ) : [],
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem() ?? 0,
                'to' => $paginated->lastItem() ?? 0,
            ],
        ]);
    }

    /**
     * POST /api/v1/memberships/check-email
     *
     * Ellenőrzi, hogy egy email cím használatban van-e már.
     * Segít az invite flow-nál: ha már létezik user, nem kell jelszót beállítania,
     * csak el kell fogadnia a meghívót.
     *
     * Válasz:
     * - user_exists: true/false
     * - user: { id, name, email } ha létezik (de csak akkor, ha az aktuális user látja)
     * - already_member: true ha a user már tag az aktuális szervezetben
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $orgId = $this->getCurrentOrgId($request->user());
        if (! $orgId) {
            return response()->json(['message' => 'Nincs aktív szervezeti kontextus.'], 403);
        }

        $existingUser = User::where('email', $validated['email'])->first();

        if (! $existingUser) {
            return response()->json([
                'user_exists' => false,
            ]);
        }

        // Van-e már tagsága ennek a szervezetnek
        $existingMembership = Membership::where('user_id', $existingUser->id)
            ->where('organization_id', $orgId)
            ->withTrashed()
            ->first();

        return response()->json([
            'user_exists' => true,
            'user' => [
                'id' => $existingUser->id,
                'name' => $existingUser->name,
                'email' => $existingUser->email,
            ],
            'already_member' => $existingMembership && ! $existingMembership->trashed() && $existingMembership->joined_at,
            'has_pending_invitation' => $existingMembership && ! $existingMembership->trashed() && ! $existingMembership->joined_at && $existingMembership->invitation_token,
            'has_deleted_membership' => $existingMembership && $existingMembership->trashed(),
        ]);
    }

    /**
     * POST /api/v1/memberships
     *
     * Új tagság létrehozása (meghívás).
     *
     * Ha az email még nem létezik → új user + új membership
     * Ha az email létezik → csak új membership (létező userhez)
     * Ha már van tagsága → hiba
     */
    public function store(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $orgId = $this->getCurrentOrgId($authUser);

        if (! $orgId) {
            return response()->json(['message' => 'Nincs aktív szervezeti kontextus.'], 403);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        // Szerepkör ellenőrzés: az aktuális szervezethez tartozik
        $role = Role::where('id', $validated['role_id'])
            ->where('organization_id', $orgId)
            ->first();

        if (! $role) {
            return response()->json([
                'message' => 'A megadott szerepkör nem tartozik ehhez a szervezethez.',
                'errors' => ['role_id' => ['A megadott szerepkör nem érvényes.']],
            ], 422);
        }

        $existingUser = User::where('email', $validated['email'])->first();

        if ($existingUser) {
            // Létező user → új membership

            // Van-e már aktív vagy pending tagsága?
            $existingMembership = Membership::where('user_id', $existingUser->id)
                ->where('organization_id', $orgId)
                ->first();

            if ($existingMembership) {
                return response()->json([
                    'message' => 'Ez a felhasználó már tag a szervezetben.',
                    'errors' => ['email' => ['Ez a felhasználó már tagja a szervezetnek.']],
                ], 422);
            }

            $invitationToken = Str::random(64);

            $membership = Membership::create([
                'user_id' => $existingUser->id,
                'organization_id' => $orgId,
                'role_id' => $validated['role_id'],
                'is_active' => false,
                'invitation_token' => $invitationToken,
                'invitation_sent_at' => now(),
            ]);

            $membership->load(['user', 'role', 'organization']);

            $invitationUrl = $this->buildInvitationUrl($invitationToken);
            $this->sendInvitationMail($membership, $invitationUrl, $authUser);

            return response()->json([
                'data' => $this->formatMembership($membership),
                'message' => 'Meghívó elküldve a létező felhasználónak.',
                'is_existing_user' => true,
                'invitation_url' => $invitationUrl,
            ], 201);
        }

        // Új user
        if (empty($validated['name'])) {
            return response()->json([
                'errors' => ['name' => ['A név megadása kötelező új felhasználó esetén.']],
            ], 422);
        }

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => null, // Meghívó elfogadásakor állítja
            'phone' => $validated['phone'] ?? null,
            'is_active' => false, // Amíg el nem fogadja
            'email_verified_at' => null,
        ]);

        $invitationToken = Str::random(64);

        $membership = Membership::create([
            'user_id' => $newUser->id,
            'organization_id' => $orgId,
            'role_id' => $validated['role_id'],
            'is_active' => false,
            'invitation_token' => $invitationToken,
            'invitation_sent_at' => now(),
        ]);

        $membership->load(['user', 'role', 'organization']);

        $invitationUrl = $this->buildInvitationUrl($invitationToken);
        $this->sendInvitationMail($membership, $invitationUrl, $authUser);

        return response()->json([
            'data' => $this->formatMembership($membership),
            'message' => 'Meghívó elküldve.',
            'is_existing_user' => false,
            'invitation_url' => $invitationUrl,
        ], 201);
    }

    /**
     * POST /api/v1/memberships/{membership}/resend-invitation
     *
     * Meghívó újraküldése (új token, friss invitation_sent_at).
     */
    public function resendInvitation(Request $request, Membership $membership): JsonResponse
    {
        $this->authorizeOrgAccess($request->user(), $membership);

        // Csak pending tagságnál
        if ($membership->joined_at) {
            return response()->json([
                'message' => 'Ez a tagság már aktiválva van, nem küldhető újra meghívó.',
            ], 422);
        }

        $newToken = Str::random(64);
        $membership->update([
            'invitation_token' => $newToken,
            'invitation_sent_at' => now(),
        ]);

        $membership->load(['user', 'role', 'organization']);

        $invitationUrl = $this->buildInvitationUrl($newToken);
        $this->sendInvitationMail($membership, $invitationUrl, $request->user());

        return response()->json([
            'data' => $this->formatMembership($membership),
            'message' => 'Meghívó sikeresen újraküldve.',
            'invitation_url' => $invitationUrl,
        ]);
    }

    /**
     * PUT /api/v1/memberships/{membership}
     *
     * Tagság módosítása (szerepkör, user név/telefon).
     */
    public function update(Request $request, Membership $membership): JsonResponse
    {
        $this->authorizeOrgAccess($request->user(), $membership);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
        ]);

        // Szerepkör csak az aktuális szervezetből származhat
        if (isset($validated['role_id'])) {
            $role = Role::where('id', $validated['role_id'])
                ->where('organization_id', $membership->organization_id)
                ->first();
            if (! $role) {
                return response()->json([
                    'errors' => ['role_id' => ['A megadott szerepkör nem érvényes.']],
                ], 422);
            }
            $membership->update(['role_id' => $validated['role_id']]);
        }

        // User-szintű adatok
        $userUpdate = [];
        if (array_key_exists('name', $validated)) $userUpdate['name'] = $validated['name'];
        if (array_key_exists('phone', $validated)) $userUpdate['phone'] = $validated['phone'];
        if (! empty($userUpdate)) {
            $membership->user->update($userUpdate);
        }

        $membership->load(['user', 'role', 'organization']);

        return response()->json([
            'data' => $this->formatMembership($membership),
        ]);
    }

    /**
     * PATCH /api/v1/memberships/{membership}/toggle-active
     *
     * Tagság aktiválása / deaktiválása.
     */
    public function toggleActive(Request $request, Membership $membership): JsonResponse
    {
        $this->authorizeOrgAccess($request->user(), $membership);

        // Saját magát nem módosíthatja
        if ($membership->user_id === $request->user()->id) {
            return response()->json([
                'message' => 'Nem módosíthatod a saját tagságod állapotát.',
            ], 403);
        }

        $membership->update(['is_active' => ! $membership->is_active]);
        $membership->load(['user', 'role', 'organization']);

        return response()->json([
            'data' => $this->formatMembership($membership),
            'message' => $membership->is_active ? 'Tagság aktiválva.' : 'Tagság deaktiválva.',
        ]);
    }

    /**
     * DELETE /api/v1/memberships/{membership}
     *
     * Tagság törlése (soft delete).
     * Ha a user-nek ez volt az utolsó aktív tagsága → user.is_active = false.
     */
    public function destroy(Request $request, Membership $membership): JsonResponse
    {
        $this->authorizeOrgAccess($request->user(), $membership);

        if ($membership->user_id === $request->user()->id) {
            return response()->json([
                'message' => 'Nem törölheted a saját tagságod.',
            ], 403);
        }

        $user = $membership->user;
        $membership->update(['is_active' => false]);
        $membership->delete();

        // Ha a usernek nincs már más aktív tagsága → user.is_active = false
        $hasOtherActive = $user->activeMemberships()
            ->where('id', '!=', $membership->id)
            ->exists();

        if (! $hasOtherActive) {
            $user->update(['is_active' => false]);
            // Kijelentkeztetjük minden eszközről
            $user->tokens()->delete();
        }

        return response()->json(null, 204);
    }

    /**
     * POST /api/v1/memberships/{membership}/restore
     *
     * Törölt tagság visszaállítása + új meghívó generálás.
     */
    public function restore(Request $request, int $membershipId): JsonResponse
    {
        $membership = Membership::withTrashed()->find($membershipId);
        if (! $membership) {
            return response()->json(['message' => 'Tagság nem található.'], 404);
        }

        $this->authorizeOrgAccess($request->user(), $membership);

        $newToken = Str::random(64);

        $membership->restore();
        $membership->update([
            'is_active' => false,
            'joined_at' => null,
            'invitation_token' => $newToken,
            'invitation_sent_at' => now(),
        ]);

        // Ha a user inaktív volt (mert nem volt tagsága), akkor a meghívó elfogadáskor
        // újra aktiválódik - most még nem változtatunk.

        $membership->load(['user', 'role', 'organization']);

        $invitationUrl = $this->buildInvitationUrl($newToken);
        $this->sendInvitationMail($membership, $invitationUrl, $request->user());

        return response()->json([
            'data' => $this->formatMembership($membership),
            'message' => 'Tagság visszaállítva, új meghívó generálva.',
            'invitation_url' => $invitationUrl,
        ]);
    }

    // ========== SEGÉD METÓDUSOK ==========

    /**
     * Az aktuális szervezet id-je (current membership vagy impersonation alapján).
     */
    private function getCurrentOrgId(User $user): ?int
    {
        $token = $user->currentAccessToken();

        if (! $token) return null;

        if ($token->context_organization_id) {
            return (int) $token->context_organization_id;
        }

        if ($token->current_membership_id) {
            $membership = Membership::find($token->current_membership_id);
            return $membership?->organization_id;
        }

        return null;
    }

    /**
     * Ellenőrzi, hogy az auth user jogosult-e ezt a tagságot módosítani.
     */
    private function authorizeOrgAccess(User $authUser, Membership $membership): void
    {
        $currentOrgId = $this->getCurrentOrgId($authUser);

        if (! $currentOrgId || $currentOrgId !== $membership->organization_id) {
            if (! $authUser->isSuperAdmin()) {
                abort(403, 'Nincs jogosultsága ehhez a tagsághoz.');
            }
        }
    }

    /**
     * Meghívó URL generálása.
     */
    private function buildInvitationUrl(string $token): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/invitation/' . $token;
    }

    /**
     * Meghívó email küldése a membership címzettjének.
     *
     * A küldést try/catch-be tesszük: ha SMTP probléma van, a membership akkor is
     * létrejön (az admin látja az invitation_url-t a response-ban), de a hibát
     * logoljuk. Éles mail queue használatakor (ShouldQueue) a tényleges hibakezelés
     * a worker jobon fog történni.
     */
    private function sendInvitationMail(Membership $membership, string $invitationUrl, ?User $inviter): void
    {
        try {
            $expiresInDays = (int) config('auth.invitation_expires_days', 7);
            Mail::send(new InvitationMail(
                membership: $membership,
                invitationUrl: $invitationUrl,
                inviterName: $inviter?->name,
                expiresInDays: $expiresInDays,
            ));
        } catch (\Throwable $e) {
            Log::error('Invitation email sending failed', [
                'membership_id' => $membership->id,
                'email' => $membership->user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Membership formázás JSON-hez.
     */
    private function formatMembership(Membership $m): array
    {
        // Státusz számítás
        $status = 'active';
        if ($m->trashed()) {
            $status = 'deleted';
        } elseif (! $m->joined_at && $m->invitation_token) {
            $status = $m->isInvitationExpired() ? 'expired' : 'pending';
        } elseif (! $m->is_active) {
            $status = 'inactive';
        }

        return [
            'id' => $m->id,
            'user' => [
                'id' => $m->user->id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'phone' => $m->user->phone,
                'initials' => $m->user->initials,
                'is_active' => $m->user->is_active,
            ],
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
            'is_active' => $m->is_active,
            'status' => $status,
            'joined_at' => $m->joined_at?->toIso8601String(),
            'last_active_at' => $m->last_active_at?->toIso8601String(),
            'invitation_sent_at' => $m->invitation_sent_at?->toIso8601String(),
            'deleted_at' => $m->deleted_at?->toIso8601String(),
            'created_at' => $m->created_at->toIso8601String(),
        ];
    }

    private function emptyMeta(): array
    {
        return [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 25,
            'total' => 0,
            'from' => 0,
            'to' => 0,
        ];
    }
}
