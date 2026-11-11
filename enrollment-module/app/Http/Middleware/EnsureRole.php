<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: allow the request only if the signed-in user has one of
 * the listed roles, e.g. ->middleware('role:admin,registrar'). Otherwise 403.
 * Hiding buttons in the views is only a convenience; this is the real check.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()?->hasRole(...$roles)) {
            abort(403, 'Your account role does not have access to this page.');
        }

        return $next($request);
    }
}
