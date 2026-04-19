<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * GET /api/v1/roles
     *
     * Az aktuális szervezet szerepkörei.
     * (Használja az invite modal legördülő, és később a szerepkör-kezelő oldal.)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $orgId = $this->getCurrentOrgId($user);

        if (! $orgId) {
            return response()->json(['data' => []]);
        }

        $roles = Role::where('organization_id', $orgId)
            ->withCount(['memberships' => fn ($q) => $q->whereNotNull('joined_at')])
            ->get();

        return response()->json([
            'data' => $roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'description' => $r->description,
                'is_system' => $r->is_system,
                'users_count' => $r->memberships_count,
            ]),
        ]);
    }

    private function getCurrentOrgId($user): ?int
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
}
