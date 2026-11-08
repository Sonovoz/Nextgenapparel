<?php

namespace App\Dominio\Precios;

use Filament\Support\Contracts\HasLabel;

/** A qué se aplica una regla. */
enum Ambito: string implements HasLabel
{
    case Catalogo = 'catalogo';
    case Familia = 'familia';
    case Prenda = 'prenda';

    public function getLabel(): string
    {
        return match ($this) {
            self::Catalogo => 'Todo el catálogo',
            self::Familia => 'Una familia',
            self::Prenda => 'Una prenda',
        };
    }
}
