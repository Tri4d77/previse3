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
     * Szervezetek listája (szűréshez pl. users lista oldalon).
     *
     * - Szuper-admin: minden szervezet
     * - Egyéb: saját szervezet + ügyfél-szervezetek (ha van)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $organizations = Organization::orderBy('type')->orderBy('name')->get();
        } else {
            $currentOrgId = $user->currentOrganization()?->id;
            if (! $currentOrgId) {
                return response()->json(['data' => []]);
            }

            // Saját + gyerek szervezetek
            $organizations = Organization::where('id', $currentOrgId)
                ->orWhere('parent_id', $currentOrgId)
                ->orderBy('type')
                ->orderBy('name')
                ->get();
        }

        return response()->json([
            'data' => $organizations->map(fn ($org) => $this->formatOrganization($org)),
        ]);
    }

    /**
     * GET /api/v1/admin/organizations-tree
     *
     * Szervezet fa-struktúra - CSAK szuper-admin számára.
     * A Platform a gyökér, subscriber-ek annak gyerekei, client-ek a subscriberek gyerekei.
     */
    public function tree(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            return response()->json(['message' => __('auth.forbidden')], 403);
        }

        // Minden szervezet lekérése egyszerre
        $allOrgs = Organization::orderBy('type')->orderBy('name')->get();

        // Fa építés: gyökerekkel kezdünk (parent_id = NULL, általában a Platform)
        $roots = $allOrgs->whereNull('parent_id');

        $tree = $roots->map(fn ($root) => $this->buildTreeNode($root, $allOrgs))->values();

        return response()->json([
            'data' => $tree,
        ]);
    }

    /**
     * Rekurzív fa-node építés.
     */
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

    private function formatOrganization(Organization $org): array
    {
        return [
            'id' => $org->id,
            'parent_id' => $org->parent_id,
            'type' => $org->type,
            'name' => $org->name,
            'slug' => $org->slug,
            'is_active' => $org->is_active,
        ];
    }
}
