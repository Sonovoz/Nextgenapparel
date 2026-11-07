<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Salud\Latido;
use App\Dominio\Sistema\Salud\Resultado;
use Illuminate\Support\Facades\DB;

/**
 * Cada minuto se envía a la cola un trabajo de latido; si la cola lo
 * procesa, se actualiza. También avisa si hay trabajos fallidos.
 */
class Colas implements Comprobacion
{
    public const MINUTOS_MAXIMOS = 5;

    public function clave(): string
    {
        return 'colas';
    }

    public function nombre(): string
    {
        return 'Colas (trabajos en segundo plano)';
    }

    public function comprobar(): array
    {
        if (config('queue.default') === 'sync') {
            return Resultado::correcto('Los trabajos se ejecutan al momento (sin cola).');
        }

        $ultimo = Latido::ultimo(Latido::COLAS);

        if ($ultimo === null || $ultimo->lt(now()->subMinutes(self::MINUTOS_MAXIMOS))) {
            $desde = $ultimo ? ' desde '.$ultimo->format('d/m/Y H:i') : '';

            return Resultado::fallo('La cola no procesa trabajos'.$desde.'. '.(app()->isLocal()
                ? 'En local se procesa sola mientras esté abierto «php artisan schedule:work».'
                : 'Revisa el contenedor «cola».'));
        }

        $fallidos = rescue(fn () => DB::table(config('queue.failed.table', 'failed_jobs'))->count(), 0, report: false);

        if ($fallidos > 0) {
            return Resultado::aviso("Funciona, pero hay {$fallidos} ".($fallidos === 1 ? 'trabajo fallido' : 'trabajos fallidos').'.');
        }

        return Resultado::correcto('Procesa trabajos. Último: '.$ultimo->format('H:i').'.');
    }
}
