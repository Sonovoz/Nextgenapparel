<?php

namespace App\Dominio\Incidencias;

use RuntimeException;

/** No se puede abrir o resolver la incidencia; `motivo` es una clave de lang/{es,en}/incidencias.php o un texto para el equipo. */
final class IncidenciaNoValida extends RuntimeException
{
    public function __construct(public readonly string $motivo)
    {
        parent::__construct($motivo);
    }
}
