<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Helyiség-típus katalógus REST endpointjai.
 *
 * Az org-szintű katalógust kezeli (CRUD). A katalógus szerkesztését
 * a Beállítások / Settings → Katalógusok admin oldalon tervezzük (jövőbeli M11.5
 * fázis); most csak a backend van kész és a Helyszín-Helyiség modal
 * dropdownja használja olvasásra.
 */
class RoomTypesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');
        $orgId = $this->resolveOrgId($request->user());
        if (! $orgId) return response()->json(['data' => []]);

        $types = RoomType::where('organization_id', $orgId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'sort_order']);

        return response()->json(['data' => $types]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_types');
        $orgId = $this->resolveOrgId($request->user());
        if (! $orgId) return response()->json(['message' => __('locations.no_org_context')], 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('room_types', 'name')->where('organization_id', $orgId)],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $type = RoomType::create([
            'organization_id' => $orgId,
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $type], 201);
    }

    public function update(Request $request, RoomType $roomType): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_types');
        $this->authorizeOrg($request->user(), $roomType->organization_id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('room_types', 'name')->where('organization_id', $roomType->organization_id)->ignore($roomType->id)],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $roomType->update($validated);

        return response()->json(['data' => $roomType->fresh()]);
    }

    public function destroy(Request $request, RoomType $roomType): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_types');
        $this->authorizeOrg($request->user(), $roomType->organization_id);

        // Megjegyzés: a Room.type mező érték-másolt string, így a meglévő
        // helyiségek nem érintettek a katalógus-tétel törlésekor.
        $roomType->delete();

        return response()->json(['message' => __('locations.type_deleted')]);
    }

    // ========== SEGÉDEK ==========

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

    private function authorizeOrg(User $user, int $orgId): void
    {
        $current = $this->resolveOrgId($user);
        if (! $current || $current !== $orgId) {
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
