<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Illuminate\Http\Request;

/**
 * Verificación en dos pasos obligatoria para todo el equipo del panel (paso 10.1, D186), como en Clave
 * de Alma (ADR 0028). `DOS_PASOS_OBLIGATORIOS=false` la quita en local y en los tests.
 */
class DosPasosEquipo
{
    public function __construct(private readonly EnsureMultiFactorAuthenticationIsEnabled $exigir) {}

    public function handle(Request $request, Closure $next): mixed
    {
        return config('seguridad.dos_pasos_obligatorios') ? $this->exigir->handle($request, $next) : $next($request);
    }
}
