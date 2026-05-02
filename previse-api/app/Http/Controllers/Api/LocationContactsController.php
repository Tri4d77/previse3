<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\LocationContact;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Helyszín-kontaktok REST endpointjai (ML2.2).
 *
 * Permission: `locations.manage_contacts` az írási műveletekhez,
 * `locations.read` az olvasáshoz.
 */
class LocationContactsController extends Controller
{
    /**
     * GET /api/v1/locations/{location}/contacts
     */
    public function index(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');
        $this->authorizeOrg($request->user(), $location);

        $contacts = $location->contacts()->get();

        return response()->json(['data' => $contacts]);
    }

    /**
     * POST /api/v1/locations/{location}/contacts
     */
    public function store(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_contacts');
        $this->authorizeOrg($request->user(), $location);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role_label' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'note' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $contact = $location->contacts()->create($validated);

        return response()->json(['data' => $contact], 201);
    }

    /**
     * PUT /api/v1/location-contacts/{contact}
     */
    public function update(Request $request, LocationContact $contact): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_contacts');
        $this->authorizeOrg($request->user(), $contact->location);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $contact->update($validated);

        return response()->json(['data' => $contact->fresh()]);
    }

    /**
     * DELETE /api/v1/location-contacts/{contact}
     */
    public function destroy(Request $request, LocationContact $contact): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.manage_contacts');
        $this->authorizeOrg($request->user(), $contact->location);

        $contact->delete();

        return response()->json(['message' => __('locations.contact_deleted')]);
    }

    /**
     * GET /api/v1/locations/{location}/contact-roles
     *
     * Egyedi role_label autocomplete-hez (a helyszín kontaktjaiból).
     */
    public function rolesAutocomplete(Request $request, Location $location): JsonResponse
    {
        $this->authorizePermission($request->user(), 'locations.read');
        $this->authorizeOrg($request->user(), $location);

        $roles = $location->contacts()
            ->whereNotNull('role_label')
            ->where('role_label', '!=', '')
            ->distinct()
            ->orderBy('role_label')
            ->pluck('role_label')
            ->values();

        return response()->json(['data' => $roles]);
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
