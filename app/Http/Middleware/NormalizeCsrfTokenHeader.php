<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeCsrfTokenHeader
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If client sent Django-style X-CSRFToken header, map it to Laravel's X-CSRF-TOKEN
        if ($request->hasHeader('X-CSRFToken') && ! $request->hasHeader('X-CSRF-TOKEN')) {
            $request->headers->set('X-CSRF-TOKEN', $request->header('X-CSRFToken'));
        }

        return $next($request);
    }
}
