<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Floor;
use App\Models\Location;
use App\Models\Membership;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Helyiségek REST endpointjai (Locations modul, ML2.1).
 *
 * Rugalmas hierarchia (lásd docs/07-modules-facility.md 1.2):
 *   - location_id: KÖTELEZŐ
 *   - floor_id: NULLABLE — szint nélküli helyiség is megengedett
 *
 * Permission: `locations.manage_rooms` az írási műveletekhez,
 * `locations.read` az olvasáshoz.
 */
class RoomsController extends Controller
{
    /**
     * GET /api/v1/locations/{location}/rooms
     *
     * Az adott helyszín ÖSSZES helyisége (szinttel és szint nélkül is).
     * Opcionális szűrő `?floor_id=X` (vagy `?floor_id=null` a szint nélküliekhez).
     */
    public function indexByLocation(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');
        $this->authorizeOrg($request->user(), $location);

        $query = $location->rooms()->with('floor:id,name,level')->orderBy('sort_order')->orderBy('name');

        if ($request->has('floor_id')) {
            $floorId = $request->input('floor_id');
            if ($floorId === 'null' || $floorId === null || $floorId === '') {
                $query->whereNull('floor_id');
            } else {
                $query->where('floor_id', (int) $floorId);
            }
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * GET /api/v1/floors/{floor}/rooms
     *
     * Egy adott szint helyiségei.
     */
    public function indexByFloor(Request $request, Floor $floor): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');
        $this->authorizeOrg($request->user(), $floor->location);

        $rooms = $floor->rooms()->orderBy('sort_order')->orderBy('name')->get();

        return response()->json(['data' => $rooms]);
    }

    /**
     * POST /api/v1/locations/{location}/rooms
     *
     * Helyiség létrehozása. floor_id opcionális — ha üres, szint nélküli helyiség.
     * Ha megadva, a floor_id-nek ugyanahhoz a locationhöz kell tartoznia.
     */
    public function store(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_rooms');
        $this->authorizeOrg($request->user(), $location);

        $validated = $request->validate([
            'floor_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:100'],
            'area_sqm' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        // Floor validáció: ha megadva, ugyanahhoz a locationhez kell tartoznia
        if (! empty($validated['floor_id'])) {
            $floor = Floor::find($validated['floor_id']);
            if (! $floor || $floor->location_id !== $location->id) {
                return response()->json([
                    'message' => __('locations.floor_invalid'),
                    'errors' => ['floor_id' => [__('locations.floor_invalid')]],
                ], 422);
            }
        }

        $room = $location->rooms()->create($validated);

        return response()->json(['data' => $room->load('floor:id,name,level')], 201);
    }

    /**
     * PUT /api/v1/rooms/{room}
     */
    public function update(Request $request, Room $room): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_rooms');
        $location = $room->location;
        $this->authorizeOrg($request->user(), $location);

        $validated = $request->validate([
            'floor_id' => ['sometimes', 'nullable', 'integer'],
            'name' => ['sometimes', 'string', 'max:255'],
            'number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'area_sqm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        if (array_key_exists('floor_id', $validated) && ! is_null($validated['floor_id'])) {
            $floor = Floor::find($validated['floor_id']);
            if (! $floor || $floor->location_id !== $location->id) {
                return response()->json([
                    'message' => __('locations.floor_invalid'),
                    'errors' => ['floor_id' => [__('locations.floor_invalid')]],
                ], 422);
            }
        }

        $room->update($validated);

        return response()->json(['data' => $room->fresh()->load('floor:id,name,level')]);
    }

    /**
     * DELETE /api/v1/rooms/{room}
     *
     * Üzleti szabály: később (Assets modul után) ellenőrizzük, hogy nincs-e
     * hozzá kötött eszköz. Most még szabadon törölhető.
     */
    public function destroy(Request $request, Room $room): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_rooms');
        $this->authorizeOrg($request->user(), $room->location);

        // TODO: Assets modul után ellenőrzés:
        // if ($room->assets()->exists()) → 422 'room_has_assets'

        $room->delete();

        return response()->json(['message' => __('locations.room_deleted')]);
    }

    /**
     * GET /api/v1/locations/{location}/room-types
     *
     * Egyedi típus-stringek listája az aktuális helyszín helyiségeiből
     * (autocomplete-hez).
     */
    public function typesAutocomplete(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');
        $this->authorizeOrg($request->user(), $location);

        $types = $location->rooms()
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->values();

        return response()->json(['data' => $types]);
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
