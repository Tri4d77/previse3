<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;

/**
 * Helyszínek (épületek) — alap CRUD (ML1).
 *
 * Multi-tenant: minden művelet az aktuális org-kontextusban fut
 * (current_membership_id vagy super-admin context_organization_id).
 *
 * Permissions:
 *  - locations.read       : index, show
 *  - locations.create     : store
 *  - locations.update     : update, setStatus, uploadImage, deleteImage
 *  - locations.delete     : destroy
 */
class LocationsController extends Controller
{
    /**
     * GET /api/v1/locations
     *
     * Szűrők: search (name/code/city), type_id, is_active (1/0/2 vagy 'all'),
     * include_archived (bool), sort, order, per_page.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');

        $orgId = $this->resolveOrgId($request->user());
        if (! $orgId) {
            return response()->json(['data' => [], 'meta' => $this->emptyMeta()]);
        }

        $query = Location::with('type')->where('organization_id', $orgId);

        // is_active szűrés: alap aktív, de szűrhető
        $statusFilter = $request->input('is_active', 'active');
        if ($statusFilter !== 'all') {
            if ($statusFilter === 'active') {
                $query->where('is_active', Location::STATE_ACTIVE);
            } else {
                $query->where('is_active', (int) $statusFilter);
            }
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('code', 'like', "%{$s}%")
                    ->orWhere('city', 'like', "%{$s}%");
            });
        }

        if ($request->filled('type_id')) {
            $query->where('type_id', $request->input('type_id'));
        }

        // Soft-deleted-ek
        if ($request->boolean('include_deleted')) {
            $query->withTrashed();
        }

        // Rendezés
        $sortField = $request->input('sort', 'name');
        $sortOrder = $request->input('order', 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSort = ['name', 'code', 'city', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSort, true)) {
            $query->orderBy($sortField, $sortOrder);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => array_map(fn ($l) => $this->format($l), $paginated->items()),
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

    public function show(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');
        $this->authorizeOrg($request->user(), $location);
        $location->load('type');
        return response()->json(['data' => $this->format($location)]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.create');
        $orgId = $this->resolveOrgId($request->user());
        if (! $orgId) {
            return response()->json(['message' => __('locations.no_org_context')], 403);
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('locations', 'code')->where('organization_id', $orgId)],
            'name' => ['required', 'string', 'max:255'],
            'type_id' => ['nullable', 'integer', Rule::exists('location_types', 'id')->where('organization_id', $orgId)],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'integer', Rule::in(Location::STATES)],
        ]);

        // Code generálás, ha nincs megadva
        if (empty($validated['code'])) {
            $validated['code'] = Location::generateNextCode($orgId);
        }

        $validated['organization_id'] = $orgId;
        $validated['is_active'] = $validated['is_active'] ?? Location::STATE_ACTIVE;

        $location = Location::create($validated);
        $location->load('type');

        return response()->json(['data' => $this->format($location)], 201);
    }

    public function update(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.update');
        $this->authorizeOrg($request->user(), $location);

        $orgId = $location->organization_id;

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('locations', 'code')->where('organization_id', $orgId)->ignore($location->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'type_id' => ['sometimes', 'nullable', 'integer', Rule::exists('location_types', 'id')->where('organization_id', $orgId)],
            'address' => ['sometimes', 'nullable', 'string'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'zip_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $location->update($validated);
        $location->load('type');

        return response()->json(['data' => $this->format($location)]);
    }

    public function setStatus(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.update');
        $this->authorizeOrg($request->user(), $location);

        $validated = $request->validate([
            'is_active' => ['required', 'integer', Rule::in(Location::STATES)],
        ]);

        $location->update(['is_active' => $validated['is_active']]);
        $location->load('type');

        return response()->json(['data' => $this->format($location)]);
    }

    public function destroy(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.delete');
        $this->authorizeOrg($request->user(), $location);

        // ML2-től üzleti szabály: nem törölhető, ha aktív ticket/feladat/eszköz tartozik hozzá.
        // Most csak soft-delete-et csinálunk; az ellenőrzés majd később jön.

        $location->delete();
        return response()->json(['message' => __('locations.deleted')]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.delete');

        $location = Location::withTrashed()->find($id);
        if (! $location) {
            return response()->json(['message' => __('locations.not_found')], 404);
        }
        $this->authorizeOrg($request->user(), $location);

        $location->restore();
        $location->load('type');
        return response()->json(['data' => $this->format($location)]);
    }

    /**
     * POST /api/v1/locations/{location}/image
     *
     * Helyszín fő-fotó feltöltés (max 5 MB JPEG/PNG).
     * Tárolás: storage/app/public/locations/{org_id}/{location_id}/image-{timestamp}.jpg
     * Thumbnail: image-thumb-{timestamp}.jpg (400x300, fitted)
     */
    public function uploadImage(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.update');
        $this->authorizeOrg($request->user(), $location);

        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:5120'], // 5 MB
        ]);

        // Régi kép törlése
        if ($location->image_path) {
            $this->deleteImageFiles($location);
        }

        $orgId = $location->organization_id;
        $locId = $location->id;
        $ts = time();
        $dir = "locations/{$orgId}/{$locId}";
        $mainPath = "{$dir}/image-{$ts}.jpg";
        $thumbPath = "{$dir}/image-thumb-{$ts}.jpg";

        // Intervention Image v4 API (decodePath fájl-útvonalból olvas)
        $manager = new ImageManager(new GdDriver());

        $uploaded = $request->file('image');

        // Fő-fotó: max 1600px szélesség, arányosan
        $img = $manager->decodePath($uploaded->getRealPath());
        $img->scaleDown(width: 1600);
        Storage::disk('public')->put($mainPath, (string) $img->encode(new JpegEncoder(quality: 85)));

        // Thumbnail: 400x300 cover-fit
        $thumb = $manager->decodePath($uploaded->getRealPath());
        $thumb->cover(400, 300);
        Storage::disk('public')->put($thumbPath, (string) $thumb->encode(new JpegEncoder(quality: 80)));

        $location->update(['image_path' => $mainPath]);
        $location->load('type');

        return response()->json(['data' => $this->format($location)]);
    }

    public function deleteImage(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.update');
        $this->authorizeOrg($request->user(), $location);
        $this->deleteImageFiles($location);
        $location->update(['image_path' => null]);
        $location->load('type');
        return response()->json(['data' => $this->format($location)]);
    }

    // ========== SEGÉDEK ==========

    /**
     * Az aktuális request szervezet-kontextusa (current_membership vagy super-admin impersonation).
     */
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

    /**
     * Ellenőrzi, hogy az auth user jogosult-e a megadott helyszínhez.
     */
    private function authorizeOrg(User $user, Location $location): void
    {
        $orgId = $this->resolveOrgId($user);
        if (! $orgId || $orgId !== $location->organization_id) {
            if (! $user->isSuperAdmin()) {
                abort(403, __('locations.forbidden'));
            }
        }
    }

    /**
     * Permission ellenőrzés (super-admin minden permission-t megkap).
     */
    private function authorizePermission(User $user, string $permission): void
    {
        if ($user->isSuperAdmin()) return;
        if (! $user->hasPermission($permission)) {
            abort(403, __('locations.forbidden'));
        }
    }

    private function deleteImageFiles(Location $location): void
    {
        if (! $location->image_path) return;

        // Fő kép
        Storage::disk('public')->delete($location->image_path);

        // Thumbnail (azonos timestamp, "-thumb-" különbség)
        $thumbPath = preg_replace('/image-(\d+)\.jpg$/', 'image-thumb-$1.jpg', $location->image_path);
        if ($thumbPath !== $location->image_path) {
            Storage::disk('public')->delete($thumbPath);
        }
    }

    private function format(Location $l): array
    {
        return [
            'id' => $l->id,
            'organization_id' => $l->organization_id,
            'code' => $l->code,
            'name' => $l->name,
            'type' => $l->type ? ['id' => $l->type->id, 'name' => $l->type->name] : null,
            'address' => $l->address,
            'city' => $l->city,
            'zip_code' => $l->zip_code,
            'latitude' => $l->latitude !== null ? (float) $l->latitude : null,
            'longitude' => $l->longitude !== null ? (float) $l->longitude : null,
            'description' => $l->description,
            'image_url' => $l->image_url,
            'thumb_url' => $l->image_path
                ? Storage::url(preg_replace('/image-(\d+)\.jpg$/', 'image-thumb-$1.jpg', $l->image_path))
                : null,
            'is_active' => $l->is_active,
            'is_deleted' => $l->trashed(),
            'created_at' => $l->created_at?->toIso8601String(),
            'updated_at' => $l->updated_at?->toIso8601String(),
        ];
    }

    private function emptyMeta(): array
    {
        return [
            'current_page' => 1, 'last_page' => 1, 'per_page' => 25,
            'total' => 0, 'from' => 0, 'to' => 0,
        ];
    }
}
