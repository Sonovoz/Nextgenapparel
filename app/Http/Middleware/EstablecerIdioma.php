<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el idioma de la petición a partir del prefijo de la URL (/es, /en).
 *
 * Además lo deja como valor por defecto del parámetro {idioma}, para que
 * route('portada') genere la URL en el idioma actual sin repetirlo.
 */
class EstablecerIdioma
{
    public function handle(Request $request, Closure $next): Response
    {
        $idioma = (string) $request->route('idioma');

        abort_unless(array_key_exists($idioma, config('idiomas.disponibles')), 404);

        App::setLocale($idioma);
        URL::defaults(['idioma' => $idioma]);

        return $next($request);
    }
}
