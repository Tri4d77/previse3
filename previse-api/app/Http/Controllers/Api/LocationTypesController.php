<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LocationType;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Helyszín-típusok katalógusa (org-specifikus).
 *
 * Permissions:
 *  - locations.read         : index
 *  - locations.manage_types : store, update, destroy, reorder
 */
class LocationTypesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $this->resolveOrgId($request->user());
        if (! $orgId) {
            return response()->json(['data' => []]);
        }

        $types = LocationType::where('organization_id', $orgId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order']);

        return response()->json(['data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->resolveOrgId($request->user());
        if (! $orgId) {
            return response()->json(['message' => __('locations.no_org_context')], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('location_types', 'name')->where('organization_id', $orgId)],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['organization_id'] = $orgId;
        $validated['sort_order'] = $validated['sort_order'] ?? 999;

        $type = LocationType::create($validated);

        return response()->json(['data' => $type], 201);
    }

    public function update(Request $request, LocationType $locationType): JsonResponse
    {
        $this->authorizeOrg($request->user(), $locationType);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('location_types', 'name')->where('organization_id', $locationType->organization_id)->ignore($locationType->id)],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $locationType->update($validated);
        return response()->json(['data' => $locationType]);
    }

    public function destroy(Request $request, LocationType $locationType): JsonResponse
    {
        $this->authorizeOrg($request->user(), $locationType);

        // Ha van hozzá kötött helyszín, nem engedjük a törlést
        if ($locationType->locations()->exists()) {
            return response()->json([
                'message' => __('locations.type_in_use'),
            ], 422);
        }

        $locationType->delete();
        return response()->json(['message' => __('locations.type_deleted')]);
    }

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

    private function authorizeOrg(User $user, LocationType $type): void
    {
        $orgId = $this->resolveOrgId($user);
        if (! $orgId || $orgId !== $type->organization_id) {
            if (! $user->isSuperAdmin()) {
                abort(403, __('locations.forbidden'));
            }
        }
    }
}
