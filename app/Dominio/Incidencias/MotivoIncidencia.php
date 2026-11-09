<?php

namespace App\Dominio\Incidencias;

/** Por qué abre el cliente la incidencia (paso 6.6b). Los textos, en lang/{es,en}/incidencias.php. */
enum MotivoIncidencia: string
{
    case Impresion = 'impresion';
    case Prenda = 'prenda';
    case TallaColor = 'talla_color';
    case Falta = 'falta';
    case Otro = 'otro';

    public function etiqueta(?string $idioma = null): string
    {
        return __('incidencias.motivo_'.$this->value, [], $idioma);
    }

    /** Una prenda que no ha llegado no se puede fotografiar; el resto necesita al menos una foto. */
    public function pideFotos(): bool
    {
        return $this !== self::Falta;
    }
}
