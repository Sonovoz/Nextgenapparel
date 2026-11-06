<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Salud\Resultado;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Illuminate\Support\Str;

class Cache implements Comprobacion
{
    public function clave(): string
    {
        return 'cache';
    }

    public function nombre(): string
    {
        return 'Caché';
    }

    public function comprobar(): array
    {
        $clave = 'salud:'.Str::random(8);
        CacheFacade::put($clave, 'ok', 60);
        $leido = CacheFacade::get($clave);
        CacheFacade::forget($clave);

        return $leido === 'ok'
            ? Resultado::correcto('Guarda y lee datos ('.config('cache.default').').')
            : Resultado::fallo('No devuelve lo que se guarda.');
    }
}
