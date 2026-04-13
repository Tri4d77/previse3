<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jogosultság-ellenőrző middleware.
 *
 * Használat a route-okban:
 *   ->middleware('permission:tickets.read')
 *   ->middleware('permission:tickets.create,tickets.update')  // VAGY logika (bármelyik elég)
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => __('auth.unauthenticated'),
            ], 401);
        }

        // Szuper-admin mindent megtehet
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Ellenőrzés: van-e BÁRMELYIK megadott engedélye (VAGY logika)
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => __('auth.forbidden'),
        ], 403);
    }
}
