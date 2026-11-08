<?php

namespace App\Dominio\Precios;

use Filament\Support\Contracts\HasLabel;

/** Cómo descuenta una oferta o un cupón. */
enum Efecto: string implements HasLabel
{
    case Porcentaje = 'porcentaje';
    case Importe = 'importe';

    public function getLabel(): string
    {
        return match ($this) {
            self::Porcentaje => 'Porcentaje',
            self::Importe => 'Importe fijo',
        };
    }
}
