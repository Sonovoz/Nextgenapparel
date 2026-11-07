<?php

namespace App\Dominio\Precios;

use Filament\Support\Contracts\HasLabel;

/** Tipo de regla de pedido mínimo. */
enum TipoMinimo: string implements HasLabel
{
    case UnidadesTotal = 'unidades_total';
    case UnidadesPorTalla = 'unidades_por_talla';
    case ImporteMinimo = 'importe_minimo';

    public function getLabel(): string
    {
        return match ($this) {
            self::UnidadesTotal => 'Unidades en total',
            self::UnidadesPorTalla => 'Unidades por talla',
            self::ImporteMinimo => 'Importe mínimo',
        };
    }
}
