<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless(
            $request->user()?->tienePermiso($permission),
            403,
            'No tiene permiso para realizar esta acción.'
        );

        return $next($request);
    }
}
