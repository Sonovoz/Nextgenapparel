<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * El backend funciona siempre en español (D33), sea cual sea el idioma
 * del navegador o el predeterminado del frontend.
 */
class IdiomaBackend
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale('es');

        return $next($request);
    }
}
