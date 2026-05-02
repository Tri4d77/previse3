<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Helyszín-felelősök REST endpointjai (ML2.2).
 *
 * A saját szervezet (subscriber/client) tagjai (membership-jei) közül lehet
 * több felelőst kijelölni egy helyszínhez.
 *
 * Permission: `locations.manage_responsibles` az írási műveletekhez,
 * `locations.read` az olvasáshoz.
 */
class LocationResponsiblesController extends Controller
{
    /**
     * GET /api/v1/locations/{location}/responsibles
     *
     * A helyszín felelősei (membership + user adatokkal együtt).
     */
    public function index(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');
        $this->authorizeOrg($request->user(), $location);

        $items = $location->responsibles()
            ->with(['user:id,name,email,phone,avatar_path', 'role:id,name,slug'])
            ->get()
            ->map(fn (Membership $m) => $this->format($m));

        return response()->json(['data' => $items]);
    }

    /**
     * POST /api/v1/locations/{location}/responsibles
     *
     * Felelős(ök) hozzárendelése. Body: { "membership_ids": [1, 2, 3] }
     * Csak az aktuális szervezet membership-jei adhatóak hozzá.
     */
    public function store(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_responsibles');
        $this->authorizeOrg($request->user(), $location);

        $validated = $request->validate([
            'membership_ids' => ['required', 'array', 'min:1'],
            'membership_ids.*' => ['integer'],
        ]);

        // Csak az adott helyszín szervezetéhez tartozó (aktív, joined) membership-eket fogadjuk el
        $validIds = Membership::whereIn('id', $validated['membership_ids'])
            ->where('organization_id', $location->organization_id)
            ->where('is_active', true)
            ->whereNotNull('joined_at')
            ->pluck('id')
            ->all();

        if (empty($validIds)) {
            return response()->json([
                'message' => __('locations.responsible_invalid'),
                'errors' => ['membership_ids' => [__('locations.responsible_invalid')]],
            ], 422);
        }

        // Csak újakat csatolunk (a már meglévő pivot rekordok érintetlen marad)
        $location->responsibles()->syncWithoutDetaching(
            collect($validIds)->mapWithKeys(fn ($id) => [$id => ['assigned_at' => now()]])->toArray()
        );

        $items = $location->responsibles()
            ->with(['user:id,name,email,phone,avatar_path', 'role:id,name,slug'])
            ->get()
            ->map(fn (Membership $m) => $this->format($m));

        return response()->json(['data' => $items], 201);
    }

    /**
     * DELETE /api/v1/locations/{location}/responsibles/{membership}
     *
     * Felelős eltávolítása.
     */
    public function destroy(Request $request, Location $location, Membership $membership): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_responsibles');
        $this->authorizeOrg($request->user(), $location);

        if ($membership->organization_id !== $location->organization_id) {
            return response()->json(['message' => __('locations.responsible_invalid')], 422);
        }

        $location->responsibles()->detach($membership->id);

        return response()->json(['message' => __('locations.responsible_removed')]);
    }

    /**
     * GET /api/v1/locations/{location}/responsibles/available
     *
     * A helyszín szervezetén belüli, ÉS még nem felelősként kijelölt aktív
     * membership-ek (a "felelős hozzáadása" dropdownhoz).
     */
    public function available(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_responsibles');
        $this->authorizeOrg($request->user(), $location);

        $existingIds = $location->responsibles()->pluck('memberships.id')->all();

        $items = Membership::with(['user:id,name,email,phone,avatar_path', 'role:id,name,slug'])
            ->where('organization_id', $location->organization_id)
            ->where('is_active', true)
            ->whereNotNull('joined_at')
            ->whereNotIn('memberships.id', $existingIds)
            ->get()
            ->map(fn (Membership $m) => $this->format($m));

        return response()->json(['data' => $items]);
    }

    // ========== SEGÉDEK ==========

    private function format(Membership $m): array
    {
        return [
            'id' => $m->id,
            'user' => $m->user ? [
                'id' => $m->user->id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'phone' => $m->user->phone,
                'avatar_url' => $m->user->avatar_path
                    ? asset('storage/' . $m->user->avatar_path)
                    : null,
            ] : null,
            'role' => $m->role ? [
                'id' => $m->role->id,
                'name' => $m->role->name,
                'slug' => $m->role->slug,
            ] : null,
            'assigned_at' => $m->pivot->assigned_at ?? null,
        ];
    }

    private function resolveOrgId(User $user): ?int
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

    private function authorizeOrg(User $user, Location $location): void
    {
        $orgId = $this->resolveOrgId($user);
        if (! $orgId || $orgId !== $location->organization_id) {
            if (! $user->isSuperAdmin()) {
                abort(403, __('locations.forbidden'));
            }
        }
    }

    private function authorizePermission(User $user, string $permission): void
    {
        if ($user->isSuperAdmin()) return;
        if (! $user->hasPermission($permission)) {
            abort(403, __('locations.forbidden'));
        }
    }
}
