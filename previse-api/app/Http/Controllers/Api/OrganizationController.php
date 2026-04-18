<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * GET /api/v1/organizations
     *
     * Szervezetek listája (szűréshez, pl. users lista oldalon).
     *
     * - Szuper-admin: minden szervezet
     * - Előfizető: saját szervezet + ügyfél-szervezetek
     * - Ügyfél: csak a saját szervezete
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $organizations = Organization::orderBy('type')->orderBy('name')->get();
        } elseif ($user->organization->isSubscriber()) {
            $organizations = Organization::where('id', $user->organization_id)
                ->orWhere('parent_id', $user->organization_id)
                ->orderBy('type')
                ->orderBy('name')
                ->get();
        } else {
            $organizations = Organization::where('id', $user->organization_id)->get();
        }

        return response()->json([
            'data' => $organizations->map(fn ($org) => [
                'id' => $org->id,
                'parent_id' => $org->parent_id,
                'type' => $org->type,
                'name' => $org->name,
                'slug' => $org->slug,
                'is_active' => $org->is_active,
            ]),
        ]);
    }
}
