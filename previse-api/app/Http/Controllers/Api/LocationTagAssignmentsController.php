<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationTag;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Helyszín ↔ címke hozzárendelés (ML2.3).
 *
 * Permissions:
 *  - locations.read   : index
 *  - locations.update : sync
 */
class LocationTagAssignmentsController extends Controller
{
    /**
     * GET /api/v1/locations/{location}/tags
     */
    public function index(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');
        $this->authorizeOrg($request->user(), $location);

        $tags = $location->tags()->get(['location_tags.id', 'location_tags.name', 'location_tags.color', 'location_tags.sort_order']);

        return response()->json(['data' => $tags]);
    }

    /**
     * PUT /api/v1/locations/{location}/tags
     * Body: { "tag_ids": [1, 5, 7] }
     *
     * Teljes csere (sync). Csak az aktuális szervezet címkéi engedélyezettek.
     */
    public function sync(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.update');
        $this->authorizeOrg($request->user(), $location);

        $validated = $request->validate([
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => ['integer'],
        ]);

        // Csak az adott helyszín szervezetéhez tartozó címkéket fogadjuk el
        $validIds = LocationTag::whereIn('id', $validated['tag_ids'])
            ->where('organization_id', $location->organization_id)
            ->pluck('id')
            ->all();

        $location->tags()->sync($validIds);

        $tags = $location->tags()->get(['location_tags.id', 'location_tags.name', 'location_tags.color', 'location_tags.sort_order']);

        return response()->json(['data' => $tags]);
    }

    // ========== SEGÉDEK ==========

    private function resolveOrgId(User $user): ?int
    {
        $token = $user->currentAccessToken();
        if (! $token) return null;
        if ($token->context_organization_id) return (int) $token->context_organization_id;
        if ($token->current_membership_id) {
            $m = Membership::find($token->current_membership_id);
            return $m?->organization_id;
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
