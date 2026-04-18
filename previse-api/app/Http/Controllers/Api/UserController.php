<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * GET /api/v1/users
     * Felhasználók listája.
     *
     * - Szuper-admin: minden szervezet minden felhasználóját látja
     * - Előfizető: saját szervezet + ügyfél-szervezetek felhasználóit
     * - Ügyfél: csak a saját szervezet felhasználóit
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $authUser = $request->user();
        $query = User::with(['role', 'organization', 'groups']);

        if ($authUser->isSuperAdmin()) {
            // Szuper-admin: minden felhasználó, opcionálisan egy szervezetre szűrve
            if ($request->filled('organization_id')) {
                $orgId = (int) $request->organization_id;
                // Adott szervezet + alatta lévő (gyerek) szervezetek
                $childIds = \App\Models\Organization::where('parent_id', $orgId)->pluck('id');
                $query->whereIn('organization_id', $childIds->prepend($orgId));
            }
        } elseif ($authUser->organization->isSubscriber()) {
            // Előfizető: saját szervezet + ügyfél-szervezetek felhasználói
            $clientIds = $authUser->organization->clients()->pluck('id');
            $allowedIds = $clientIds->prepend($authUser->organization_id);

            // Opcionális szűrés egy ügyfél-szervezetre
            if ($request->filled('organization_id')) {
                $orgId = (int) $request->organization_id;
                if ($allowedIds->contains($orgId)) {
                    $query->where('organization_id', $orgId);
                } else {
                    // Nincs joga ehhez a szervezethez -> üres eredmény
                    $query->whereRaw('1 = 0');
                }
            } else {
                $query->whereIn('organization_id', $allowedIds);
            }
        } else {
            // Ügyfél szervezet: csak a saját felhasználói
            $query->where('organization_id', $authUser->organization_id);
        }

        // Szűrők
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('role', fn ($q) => $q->where('slug', $request->role));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('group_id')) {
            $query->whereHas('groups', fn ($q) => $q->where('groups.id', $request->group_id));
        }

        // Rendezés
        $sortField = $request->input('sort', 'created_at');
        $sortDir = $request->input('order', 'desc');
        $allowedSorts = ['name', 'email', 'created_at', 'last_login_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min($request->input('per_page', 25), 100);
        $users = $query->paginate($perPage);

        return UserResource::collection($users)->response();
    }

    /**
     * GET /api/v1/users/{user}
     * Egy felhasználó adatai.
     */
    public function show(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        $user->load(['role', 'organization', 'groups']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * POST /api/v1/users
     * Felhasználó meghívása (létrehozás).
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
            'group_ids' => ['sometimes', 'array'],
            'group_ids.*' => ['exists:groups,id'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $organization = $request->user()->organization;

        // Email domain ellenőrzés
        if (! $organization->isEmailDomainAllowed($validated['email'])) {
            return response()->json([
                'message' => __('validation.custom.email.domain_not_allowed'),
                'errors' => ['email' => [__('validation.custom.email.domain_not_allowed')]],
            ], 422);
        }

        // Szerepkör ellenőrzés: a megadott role_id a szervezethez tartozik-e
        $roleExists = $organization->roles()->where('id', $validated['role_id'])->exists();
        if (! $roleExists) {
            return response()->json([
                'message' => 'A megadott szerepkör nem tartozik ehhez a szervezethez.',
                'errors' => ['role_id' => ['A megadott szerepkör nem érvényes.']],
            ], 422);
        }

        // Felhasználó létrehozása meghívó tokennel
        $invitationToken = Str::random(64);

        $user = User::create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Str::random(32), // Ideiglenes, a meghívó elfogadásakor cseréli
            'role_id' => $validated['role_id'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => false,
            'invitation_token' => $invitationToken,
            'invitation_sent_at' => now(),
        ]);

        // Csoportok hozzárendelése
        if (! empty($validated['group_ids'])) {
            $user->groups()->sync($validated['group_ids']);
        }

        // TODO: Meghívó email küldése (Fázis 1 végén vagy Fázis 2-ben)

        $user->load(['role', 'organization', 'groups']);

        // Meghívó URL generálása (fejlesztői segéd - később SMTP-n megy ki email-ben)
        $invitationUrl = rtrim(config('app.frontend_url'), '/') . '/invitation/' . $invitationToken;

        return response()->json([
            'data' => new UserResource($user),
            'message' => __('users.invited'),
            'invitation_url' => $invitationUrl,
        ], 201);
    }

    /**
     * PUT /api/v1/users/{user}
     * Felhasználó módosítása.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        Gate::authorize('update', $user);

        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'role_id' => ['sometimes', 'exists:roles,id'],
            'group_ids' => ['sometimes', 'array'],
            'group_ids.*' => ['exists:groups,id'],
        ];

        // Csak admin módosíthat role_id-t
        if (! $request->user()->hasPermission('users.edit')) {
            unset($rules['role_id']);
        }

        $validated = $request->validate($rules);

        // Role_id szervezet-ellenőrzés
        if (isset($validated['role_id'])) {
            $roleExists = $user->organization->roles()->where('id', $validated['role_id'])->exists();
            if (! $roleExists) {
                return response()->json([
                    'errors' => ['role_id' => ['A megadott szerepkör nem érvényes.']],
                ], 422);
            }
        }

        $user->update(collect($validated)->except('group_ids')->toArray());

        if (isset($validated['group_ids'])) {
            $user->groups()->sync($validated['group_ids']);
        }

        $user->load(['role', 'organization', 'groups']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * PATCH /api/v1/users/{user}/toggle-active
     * Felhasználó aktiválása / deaktiválása.
     */
    public function toggleActive(User $user): JsonResponse
    {
        Gate::authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'data' => new UserResource($user->fresh(['role', 'organization'])),
            'message' => $user->is_active ? __('users.activated') : __('users.deactivated'),
        ]);
    }

    /**
     * DELETE /api/v1/users/{user}
     * Felhasználó törlése (soft delete).
     */
    public function destroy(User $user): JsonResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return response()->json(null, 204);
    }
}
