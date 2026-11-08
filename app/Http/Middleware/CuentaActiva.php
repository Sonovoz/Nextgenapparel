<?php

namespace App\Http\Middleware;

use App\Support\RutaCuenta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una cuenta desactivada se queda fuera al momento (revisión N3 de la fase 5, A2):
 * «activo» no se mira solo al entrar, también con la sesión abierta o con la cookie
 * de «recordar», que dura años. El backend ya lo frena con canAccessPanel.
 */
class CuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario !== null && ! $usuario->activo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401);
            }
            $idioma = array_key_exists((string) $request->segment(1), config('idiomas.disponibles')) ? (string) $request->segment(1) : config('idiomas.predeterminado');

            return redirect(RutaCuenta::url('entrar', [], $idioma))->with('error', __('cuenta.credenciales'));
        }

        return $next($request);
    }
}
