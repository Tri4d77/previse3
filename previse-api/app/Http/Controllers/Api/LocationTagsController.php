<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LocationTag;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Helyszín-címke katalógus (ML2.3).
 *
 * Permissions:
 *  - locations.read         : index
 *  - locations.manage_tags  : store, update, destroy, reorder
 */
class LocationTagsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');

        $orgId = $this->resolveOrgId($request->user());
        if (! $orgId) {
            return response()->json(['data' => []]);
        }

        $tags = LocationTag::where('organization_id', $orgId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'sort_order']);

        return response()->json(['data' => $tags]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_tags');

        $orgId = $this->resolveOrgId($request->user());
        if (! $orgId) {
            return response()->json(['message' => __('locations.no_org_context')], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('location_tags', 'name')->where('organization_id', $orgId),
            ],
            'color' => ['required', 'string', Rule::in(LocationTag::ALLOWED_COLORS)],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['organization_id'] = $orgId;
        $validated['sort_order'] = $validated['sort_order'] ?? 999;

        $tag = LocationTag::create($validated);

        return response()->json(['data' => $tag], 201);
    }

    public function update(Request $request, LocationTag $tag): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_tags');
        $this->authorizeOrg($request->user(), $tag);

        $validated = $request->validate([
            'name' => [
                'sometimes', 'string', 'max:50',
                Rule::unique('location_tags', 'name')
                    ->where('organization_id', $tag->organization_id)
                    ->ignore($tag->id),
            ],
            'color' => ['sometimes', 'string', Rule::in(LocationTag::ALLOWED_COLORS)],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $tag->update($validated);

        return response()->json(['data' => $tag->fresh()]);
    }

    public function destroy(Request $request, LocationTag $tag): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_tags');
        $this->authorizeOrg($request->user(), $tag);

        // Pivot CASCADE-del törlődik, helyszín nem érintett
        $tag->delete();

        return response()->json(['message' => __('locations.tag_deleted')]);
    }

    /**
     * POST /api/v1/location-tags/reorder
     * Body: { "ids": [3, 1, 4, 2] }
     */
    public function reorder(Request $request): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_tags');

        $orgId = $this->resolveOrgId($request->user());
        if (! $orgId) {
            return response()->json(['message' => __('locations.no_org_context')], 403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        foreach ($validated['ids'] as $index => $id) {
            LocationTag::where('id', $id)
                ->where('organization_id', $orgId)
                ->update(['sort_order' => $index]);
        }

        $tags = LocationTag::where('organization_id', $orgId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'sort_order']);

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

    private function authorizeOrg(User $user, LocationTag $tag): void
    {
        $orgId = $this->resolveOrgId($user);
        if (! $orgId || $orgId !== $tag->organization_id) {
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
