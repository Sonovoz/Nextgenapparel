<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Páginas privadas (backend): fuera de los buscadores aunque alguien las enlace. */
class NoIndexar
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);
        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $respuesta;
    }
}
