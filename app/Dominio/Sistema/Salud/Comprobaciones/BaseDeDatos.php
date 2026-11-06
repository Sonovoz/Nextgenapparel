<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Salud\Resultado;
use Illuminate\Support\Facades\DB;

class BaseDeDatos implements Comprobacion
{
    public function clave(): string
    {
        return 'base-de-datos';
    }

    public function nombre(): string
    {
        return 'Base de datos';
    }

    public function comprobar(): array
    {
        $inicio = microtime(true);
        DB::select('select 1');
        $ms = (int) round((microtime(true) - $inicio) * 1000);

        return $ms > 1000
            ? Resultado::aviso("Responde, pero despacio ({$ms} ms).")
            : Resultado::correcto("Responde ({$ms} ms, ".DB::connection()->getDriverName().').');
    }
}
