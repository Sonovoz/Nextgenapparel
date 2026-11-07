<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Salud\Latido;
use App\Dominio\Sistema\Salud\Resultado;

/**
 * Las tareas programadas registran un latido cada minuto.
 */
class TareasProgramadas implements Comprobacion
{
    public const MINUTOS_MAXIMOS = 3;

    public function clave(): string
    {
        return 'tareas-programadas';
    }

    public function nombre(): string
    {
        return 'Tareas programadas';
    }

    public function comprobar(): array
    {
        $ultimo = Latido::ultimo(Latido::TAREAS);

        if ($ultimo === null) {
            return Resultado::fallo(app()->isLocal()
                ? 'No se están ejecutando. En local, arráncalas con «php artisan schedule:work».'
                : 'No se han ejecutado nunca. Revisa el contenedor «scheduler».');
        }

        if ($ultimo->lt(now()->subMinutes(self::MINUTOS_MAXIMOS))) {
            return Resultado::fallo('No se ejecutan desde '.$ultimo->format('d/m/Y H:i').' ('.$ultimo->diffForHumans().').');
        }

        return Resultado::correcto('Última ejecución: '.$ultimo->format('H:i').'.');
    }
}
