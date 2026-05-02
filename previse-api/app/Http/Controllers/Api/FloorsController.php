<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\Location;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Szintek REST endpointjai (Locations modul, ML2.1).
 *
 * Az org-context check a `location.organization_id` mezőn keresztül megy:
 * minden floor egy locationhöz tartozik, és csak az aktuális szervezet
 * locationjén belüli floor-okhoz fér hozzá a user.
 *
 * Permission: `locations.manage_floors` az írási műveletekhez,
 * `locations.read` az olvasáshoz.
 */
class FloorsController extends Controller
{
    /**
     * GET /api/v1/locations/{location}/floors
     */
    public function index(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');
        $this->authorizeOrg($request->user(), $location);

        $floors = $location->floors()
            ->withCount('rooms')
            ->orderBy('sort_order')
            ->orderBy('level')
            ->get();

        return response()->json(['data' => $floors]);
    }

    /**
     * POST /api/v1/locations/{location}/floors
     */
    public function store(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_floors');
        $this->authorizeOrg($request->user(), $location);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'level' => ['nullable', 'integer', 'min:-20', 'max:200'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        // Nincs duplikáció: ugyanazon a helyszínen ne legyen ugyanaz a név
        if ($location->floors()->where('name', $validated['name'])->exists()) {
            return response()->json([
                'message' => __('locations.floor_name_exists'),
                'errors' => ['name' => [__('locations.floor_name_exists')]],
            ], 422);
        }

        $floor = $location->floors()->create([
            'name' => $validated['name'],
            'level' => $validated['level'] ?? 0,
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $floor], 201);
    }

    /**
     * PUT /api/v1/floors/{floor}
     */
    public function update(Request $request, Floor $floor): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_floors');
        $location = $floor->location;
        $this->authorizeOrg($request->user(), $location);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'level' => ['sometimes', 'integer', 'min:-20', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        if (isset($validated['name'])) {
            $exists = $location->floors()
                ->where('name', $validated['name'])
                ->where('id', '!=', $floor->id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'message' => __('locations.floor_name_exists'),
                    'errors' => ['name' => [__('locations.floor_name_exists')]],
                ], 422);
            }
        }

        $floor->update($validated);

        return response()->json(['data' => $floor->fresh()]);
    }

    /**
     * DELETE /api/v1/floors/{floor}
     *
     * Üzleti szabály: szint csak akkor törölhető, ha üres (nincs benne helyiség).
     */
    public function destroy(Request $request, Floor $floor): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_floors');
        $this->authorizeOrg($request->user(), $floor->location);

        if ($floor->rooms()->exists()) {
            return response()->json([
                'message' => __('locations.floor_has_rooms'),
                'code' => 'floor_has_rooms',
            ], 422);
        }

        $floor->delete();

        return response()->json(['message' => __('locations.floor_deleted')]);
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
