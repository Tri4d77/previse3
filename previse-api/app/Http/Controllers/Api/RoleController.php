<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    /**
     * GET /api/v1/roles
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::where('organization_id', $request->user()->organization_id)
            ->withCount('users')
            ->with('permissions')
            ->get();

        return response()->json([
            'data' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->map(fn ($p) => $p->module . '.' . $p->action)->values(),
            ]),
        ]);
    }

    /**
     * POST /api/v1/roles
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/'],
            'description' => ['sometimes', 'nullable', 'string'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'], // "module.action" formátum
        ]);

        // Slug egyediség ellenőrzés a szervezeten belül
        $exists = Role::where('organization_id', $request->user()->organization_id)
            ->where('slug', $validated['slug'])
            ->exists();

        if ($exists) {
            return response()->json([
                'errors' => ['slug' => ['Ez az azonosító már foglalt.']],
            ], 422);
        }

        $role = Role::create([
            'organization_id' => $request->user()->organization_id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        // Engedélyek hozzárendelése
        if (! empty($validated['permissions'])) {
            $permissionIds = Permission::where(function ($query) use ($validated) {
                foreach ($validated['permissions'] as $perm) {
                    [$module, $action] = explode('.', $perm, 2);
                    $query->orWhere(function ($q) use ($module, $action) {
                        $q->where('module', $module)->where('action', $action);
                    });
                }
            })->pluck('id');

            $role->permissions()->sync($permissionIds);
        }

        $role->load('permissions');

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'permissions' => $role->permissions->map(fn ($p) => $p->module . '.' . $p->action)->values(),
            ],
        ], 201);
    }

    /**
     * PUT /api/v1/roles/{role}/permissions
     * Szerepkör engedélyeinek frissítése (a jogosultsági mátrix mentése).
     */
    public function updatePermissions(Request $request, Role $role): JsonResponse
    {
        Gate::authorize('update', $role);

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        $permissionIds = Permission::where(function ($query) use ($validated) {
            foreach ($validated['permissions'] as $perm) {
                $parts = explode('.', $perm, 2);
                if (count($parts) === 2) {
                    [$module, $action] = $parts;
                    $query->orWhere(function ($q) use ($module, $action) {
                        $q->where('module', $module)->where('action', $action);
                    });
                }
            }
        })->pluck('id');

        $role->permissions()->sync($permissionIds);

        return response()->json([
            'message' => 'Jogosultságok frissítve.',
            'data' => [
                'permissions' => $role->fresh('permissions')->permissions->map(fn ($p) => $p->module . '.' . $p->action)->values(),
            ],
        ]);
    }

    /**
     * GET /api/v1/permissions
     * Összes engedély listája (a mátrix felépítéséhez).
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::all()->groupBy('module')->map(function ($perms, $module) {
            return $perms->map(fn ($p) => [
                'id' => $p->id,
                'action' => $p->action,
                'key' => $p->module . '.' . $p->action,
                'description' => $p->description,
            ]);
        });

        return response()->json(['data' => $permissions]);
    }

    /**
     * DELETE /api/v1/roles/{role}
     */
    public function destroy(Role $role): JsonResponse
    {
        Gate::authorize('delete', $role);

        $role->permissions()->detach();
        $role->delete();

        return response()->json(null, 204);
    }
}
