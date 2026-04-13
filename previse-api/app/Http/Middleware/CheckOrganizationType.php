<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Szervezet típus ellenőrző middleware.
 *
 * Használat:
 *   ->middleware('org.type:platform')          // Csak platform (szuper-admin)
 *   ->middleware('org.type:subscriber')        // Csak előfizetők
 *   ->middleware('org.type:subscriber,client') // Előfizetők VAGY ügyfelek
 */
class CheckOrganizationType
{
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => __('auth.unauthenticated')], 401);
        }

        // Szuper-admin mindenhova bemehet
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (! in_array($user->organization->type, $types)) {
            return response()->json(['message' => __('auth.forbidden')], 403);
        }

        return $next($request);
    }
}
