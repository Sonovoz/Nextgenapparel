<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Salud\EstadoSalud;

interface Comprobacion
{
    /** Identificador estable (se usa en el archivo de estado). */
    public function clave(): string;

    /** Nombre que se muestra en el panel y en los correos. */
    public function nombre(): string;

    /**
     * @return array{0: EstadoSalud, 1: string} Estado y mensaje en español.
     */
    public function comprobar(): array;
}
