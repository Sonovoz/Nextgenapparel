<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Errores\EstadoError;
use App\Dominio\Sistema\Errores\NivelError;
use App\Dominio\Sistema\Salud\Resultado;
use App\Models\RegistroError;

class ErroresCriticos implements Comprobacion
{
    public function clave(): string
    {
        return 'errores-criticos';
    }

    public function nombre(): string
    {
        return 'Errores críticos pendientes';
    }

    public function comprobar(): array
    {
        $pendientes = RegistroError::where('estado', EstadoError::Pendiente)
            ->where('nivel', NivelError::Critico)
            ->count();

        return $pendientes > 0
            ? Resultado::aviso("{$pendientes} ".($pendientes === 1 ? 'error crítico pendiente' : 'errores críticos pendientes').' en el log de errores.')
            : Resultado::correcto('Ninguno.');
    }
}
