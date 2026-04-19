<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Organization;
use App\Services\OrganizationRoleSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationController extends Controller
{
    /**
     * GET /api/v1/organizations
     *
     * Szűrő paraméterek: search, type, status
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = $this->scopedOrganizationQuery($user);

        // Szűrők
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $organizations = $query->orderBy('type')->orderBy('name')->get();

        return response()->json([
            'data' => $organizations->map(fn ($org) => $this->formatOrganization($org)),
        ]);
    }

    /**
     * GET /api/v1/admin/organizations-tree
     *
     * Szervezet fa-struktúra.
     * - Szuper-adminnak: teljes fa (Platform → Subscribers → Clients)
     * - Subscriber-adminnak: saját szervezet + ügyfél-szervezetek
     */
    public function tree(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessOrganizations($user)) {
            return response()->json(['message' => __('auth.forbidden')], 403);
        }

        $query = $this->scopedOrganizationQuery($user);

        // Szűrők
        $searchActive = false;
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
            $searchActive = true;
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $filteredOrgs = $query->orderBy('type')->orderBy('name')->get();

        // Ha szűrés aktív, kiegészítjük a hierarchiát a szülőkkel, hogy a fa értelmezhető legyen
        if ($searchActive || $request->filled('type') || $request->filled('status')) {
            $allAncestorIds = collect();
            foreach ($filteredOrgs as $org) {
                $current = $org;
                while ($current->parent_id) {
                    $allAncestorIds->push($current->parent_id);
                    $current = Organization::find($current->parent_id);
                    if (! $current) break;
                }
            }
            $ancestors = Organization::whereIn('id', $allAncestorIds->unique())->get();
            $allOrgs = $filteredOrgs->merge($ancestors)->unique('id');
        } else {
            $allOrgs = $filteredOrgs;
        }

        // Fa építése: gyökerek azok, amelyeknek parent_id-je NULL VAGY a parent nincs a látható listában
        $visibleIds = $allOrgs->pluck('id')->all();
        $roots = $allOrgs->filter(fn ($o) => ! $o->parent_id || ! in_array($o->parent_id, $visibleIds));

        $tree = $roots->map(fn ($root) => $this->buildTreeNode($root, $allOrgs))->values();

        return response()->json(['data' => $tree]);
    }

    /**
     * GET /api/v1/organizations/{organization}
     */
    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeView($request->user(), $organization);

        return response()->json([
            'data' => $this->formatOrganization($organization, withStats: true),
        ]);
    }

    /**
     * POST /api/v1/organizations
     *
     * - Szuper-admin bármit létrehozhat (subscriber vagy client)
     * - Subscriber admin csak client szervezetet hozhat létre a saját szervezete alá
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessOrganizations($user)) {
            return response()->json(['message' => __('auth.forbidden')], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:subscriber,client'],
            'parent_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email'],
            'tax_number' => ['nullable', 'string', 'max:50'],
        ]);

        // Jogosultság-ellenőrzés típus szerint
        if ($validated['type'] === 'subscriber' && ! $user->isSuperAdmin()) {
            return response()->json(['message' => 'Csak szuper-admin hozhat létre előfizető szervezetet.'], 403);
        }

        // Szülő ellenőrzés
        if ($validated['type'] === 'subscriber') {
            $platform = Organization::where('type', 'platform')->first();
            $validated['parent_id'] = $platform?->id;
        } elseif ($validated['type'] === 'client') {
            $parentId = $validated['parent_id'] ?? null;
            if (! $parentId) {
                return response()->json([
                    'message' => 'Az előfizető szervezet kiválasztása kötelező.',
                    'errors' => ['parent_id' => ['Ügyfél-szervezet esetén meg kell adni az előfizető szervezetet.']],
                ], 422);
            }
            $parent = Organization::find($parentId);
            if (! $parent || $parent->type !== 'subscriber') {
                return response()->json([
                    'message' => 'A szülő szervezet csak előfizető lehet.',
                    'errors' => ['parent_id' => ['A szülő szervezet csak előfizető (subscriber) lehet.']],
                ], 422);
            }

            // Subscriber admin csak a saját szervezete alá tehet ügyfelet
            if (! $user->isSuperAdmin()) {
                $currentMembership = $user->currentMembership();
                if (! $currentMembership || $currentMembership->organization_id !== $parent->id || $currentMembership->role->slug !== 'admin') {
                    return response()->json(['message' => 'Csak a saját szervezeted alá tudsz ügyfél-szervezetet felvenni.'], 403);
                }
            }
        }

        $org = DB::transaction(function () use ($validated) {
            $org = Organization::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'parent_id' => $validated['parent_id'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'zip_code' => $validated['zip_code'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'tax_number' => $validated['tax_number'] ?? null,
                'status' => 'active',
                'is_active' => true,
            ]);

            OrganizationRoleSeeder::seed($org);

            return $org;
        });

        return response()->json([
            'data' => $this->formatOrganization($org),
            'message' => 'Szervezet sikeresen létrehozva.',
        ], 201);
    }

    /**
     * PUT /api/v1/organizations/{organization}
     */
    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeUpdate($request->user(), $organization);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'zip_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email'],
            'tax_number' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $organization->update($validated);

        return response()->json([
            'data' => $this->formatOrganization($organization),
            'message' => 'Szervezet adatok frissítve.',
        ]);
    }

    /**
     * POST /api/v1/organizations/{organization}/status
     *
     * Státusz módosítás (active, inactive, terminated).
     */
    public function setStatus(Request $request, Organization $organization): JsonResponse
    {
        $this->authorizeStatusChange($request->user(), $organization);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:active,inactive,terminated'],
        ]);

        // A Platform nem módosítható
        if ($organization->isPlatform()) {
            return response()->json(['message' => 'A Platform szervezet státusza nem módosítható.'], 422);
        }

        $organization->setStatus($validated['status']);

        return response()->json([
            'data' => $this->formatOrganization($organization),
            'message' => match ($validated['status']) {
                'active' => 'Szervezet aktiválva.',
                'inactive' => 'Szervezet inaktiválva.',
                'terminated' => 'Szervezet megszüntetve.',
            },
        ]);
    }

    // ========== PRIVATE HELPERS ==========

    /**
     * Lehet-e a user (bármilyen úton) hozzáférni a szervezet-kezeléshez?
     *
     * - Szuper-admin: mindig
     * - Subscriber admin: igen (saját + client-jei)
     * - Más: nem
     */
    private function canAccessOrganizations($user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $currentMembership = $user->currentMembership();
        if (! $currentMembership) {
            return false;
        }

        return $currentMembership->organization->isSubscriber()
            && $currentMembership->role->slug === 'admin';
    }

    /**
     * A user által látható szervezetek query scope-ja.
     */
    private function scopedOrganizationQuery($user)
    {
        if ($user->isSuperAdmin()) {
            return Organization::query();
        }

        $currentMembership = $user->currentMembership();
        if (! $currentMembership) {
            return Organization::whereRaw('1=0');
        }

        $orgId = $currentMembership->organization_id;

        // Subscriber admin: saját + ügyfél-szervezetek
        return Organization::where(function ($q) use ($orgId) {
            $q->where('id', $orgId)->orWhere('parent_id', $orgId);
        });
    }

    private function authorizeView($user, Organization $org): void
    {
        if ($user->isSuperAdmin()) return;

        $currentOrgId = $user->currentOrganization()?->id;
        if ($currentOrgId !== $org->id && $org->parent_id !== $currentOrgId) {
            abort(403, 'Nincs jogosultsága ehhez a szervezethez.');
        }
    }

    private function authorizeUpdate($user, Organization $org): void
    {
        if ($user->isSuperAdmin()) return;

        $currentMembership = $user->currentMembership();

        // Saját szervezet admin
        if ($currentMembership && $currentMembership->organization_id === $org->id
            && $currentMembership->role->slug === 'admin') {
            return;
        }

        // Saját szervezet alá tartozó ügyfél-szervezetnél (subscriber admin)
        if ($currentMembership && $org->parent_id === $currentMembership->organization_id
            && $currentMembership->role->slug === 'admin'
            && $currentMembership->organization->isSubscriber()) {
            return;
        }

        abort(403, 'Nincs jogosultsága ehhez a szervezethez.');
    }

    private function authorizeStatusChange($user, Organization $org): void
    {
        if ($user->isSuperAdmin()) return;

        // Subscriber admin csak az ügyfél-szervezetei státuszát módosíthatja (a sajátját nem)
        $currentMembership = $user->currentMembership();
        if ($currentMembership && $org->parent_id === $currentMembership->organization_id
            && $currentMembership->role->slug === 'admin'
            && $currentMembership->organization->isSubscriber()) {
            return;
        }

        abort(403, 'Nincs jogosultsága ehhez a szervezethez.');
    }

    private function buildTreeNode(Organization $org, $allOrgs): array
    {
        $children = $allOrgs->where('parent_id', $org->id);

        return array_merge(
            $this->formatOrganization($org),
            [
                'children' => $children->map(fn ($c) => $this->buildTreeNode($c, $allOrgs))->values(),
            ]
        );
    }

    private function formatOrganization(Organization $org, bool $withStats = false): array
    {
        $data = [
            'id' => $org->id,
            'parent_id' => $org->parent_id,
            'type' => $org->type,
            'name' => $org->name,
            'slug' => $org->slug,
            'address' => $org->address,
            'city' => $org->city,
            'zip_code' => $org->zip_code,
            'phone' => $org->phone,
            'email' => $org->email,
            'tax_number' => $org->tax_number,
            'status' => $org->status,
            'is_active' => $org->is_active,
            'terminated_at' => $org->terminated_at?->toIso8601String(),
            'created_at' => $org->created_at?->toIso8601String(),
        ];

        if ($withStats) {
            $data['stats'] = [
                'members_count' => $org->activeMemberships()->count(),
                'children_count' => $org->children()->count(),
            ];
        }

        return $data;
    }
}
