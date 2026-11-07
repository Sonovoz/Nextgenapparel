<?php

namespace App\Dominio\Catalogo;

use Filament\Support\Contracts\HasLabel;

/** Cara de la prenda (fotos de maqueta y zonas de impresión). */
enum Cara: string implements HasLabel
{
    case Delantera = 'delantera';
    case Trasera = 'trasera';

    public function getLabel(): string
    {
        return match ($this) {
            self::Delantera => 'Delantera',
            self::Trasera => 'Trasera',
        };
    }
}
