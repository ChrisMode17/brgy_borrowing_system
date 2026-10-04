<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Ported from checkAdminAccess() in backend/admin/accounts.php: Admin role OR access_level <= 2. */
class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user || !$user->isAdmin()) {
            abort(403, 'Access denied');
        }

        return $next($request);
    }
}
