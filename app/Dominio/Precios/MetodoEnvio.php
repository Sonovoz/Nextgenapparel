<?php

namespace App\Dominio\Precios;

use Filament\Support\Contracts\HasLabel;

/** Cómo se calcula el envío de una zona. */
enum MetodoEnvio: string implements HasLabel
{
    case PorKilo = 'por_kilo';
    case PorCaja = 'por_caja';

    public function getLabel(): string
    {
        return match ($this) {
            self::PorKilo => 'Por peso',
            self::PorCaja => 'Por cajas',
        };
    }
}
