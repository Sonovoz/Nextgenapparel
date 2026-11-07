<?php

namespace App\Dominio\Disenos;

/** Resultado de la calidad de un diseño a su tamaño de impresión (ficha § 7). */
enum NivelCalidad: string
{
    case Correcta = 'correcta';    // ≥ umbral «correcto»
    case Buena = 'buena';          // aviso leve
    case Justa = 'justa';          // aviso con consejos
    case Baja = 'baja';            // aviso fuerte: mejor no continuar

    public function etiqueta(?string $idioma = null): string
    {
        return __('disenos.nivel_'.$this->value, locale: $idioma);
    }

    public function mensaje(?string $idioma = null): string
    {
        return __('disenos.mensaje_'.$this->value, locale: $idioma);
    }
}
