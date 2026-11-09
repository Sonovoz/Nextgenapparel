<?php

namespace App\Dominio\Incidencias;

/** Cómo la resuelve el equipo (ficha § 4): reimpresión, reembolso parcial o rechazo. */
enum ResolucionIncidencia: string
{
    case Reimpresion = 'reimpresion';
    case Reembolso = 'reembolso';
    case Rechazo = 'rechazo';

    public function etiqueta(?string $idioma = null): string
    {
        return __('incidencias.resolucion_'.$this->value, [], $idioma);
    }
}
