<?php

namespace App\Dominio\Facturacion;

/** Tipos de factura de la AEAT que usa la tienda (D175). */
enum TipoFactura: string
{
    case Completa = 'F1';
    case Simplificada = 'F2';
    case Rectificativa = 'R1';           // de una completa
    case RectificativaSimplificada = 'R5';

    public function etiqueta(?string $idioma = null): string
    {
        return __('facturas.tipo_'.$this->value, [], $idioma);
    }

    public function esRectificativa(): bool
    {
        return in_array($this, [self::Rectificativa, self::RectificativaSimplificada], true);
    }

    /** La serie de Ajustes → Facturación. */
    public function clave(): string
    {
        return match ($this) {
            self::Completa => 'completa',
            self::Simplificada => 'simplificada',
            self::Rectificativa, self::RectificativaSimplificada => 'rectificativa',
        };
    }
}
