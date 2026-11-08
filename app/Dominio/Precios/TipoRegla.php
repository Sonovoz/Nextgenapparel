<?php

namespace App\Dominio\Precios;

use Filament\Support\Contracts\HasLabel;

/** Tipo de oferta. */
enum TipoRegla: string implements HasLabel
{
    case TramoCantidad = 'tramo_cantidad';
    case Combinada = 'combinada';

    public function getLabel(): string
    {
        return match ($this) {
            self::TramoCantidad => 'Tramo por cantidad',
            self::Combinada => 'Oferta combinada',
        };
    }
}
