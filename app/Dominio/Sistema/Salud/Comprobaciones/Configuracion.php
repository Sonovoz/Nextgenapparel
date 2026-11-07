<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Salud\Resultado;

/**
 * En producción, el modo de depuración debe estar apagado (si no, los
 * visitantes verían detalles internos de los errores).
 */
class Configuracion implements Comprobacion
{
    public function clave(): string
    {
        return 'configuracion';
    }

    public function nombre(): string
    {
        return 'Configuración';
    }

    public function comprobar(): array
    {
        $entorno = app()->environment();

        if (app()->isProduction() && config('app.debug')) {
            return Resultado::fallo('El modo de depuración está activo en producción (APP_DEBUG=true).');
        }

        if (app()->isProduction() && blank(config('errores.correo_alertas'))) {
            return Resultado::aviso('No hay correo de alertas (ALERTAS_EMAIL): nadie recibirá los avisos.');
        }

        return Resultado::correcto("Entorno «{$entorno}»".(config('app.debug') ? ', depuración activa.' : '.'));
    }
}
