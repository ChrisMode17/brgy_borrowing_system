<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Restricts a route to users at or below (i.e. at least as privileged as) a given access level. */
class EnsureAccessLevel
{
    public function handle(Request $request, Closure $next, int $maxLevel): Response
    {
        $user = Auth::user();

        if (!$user || $user->access_level > $maxLevel) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
