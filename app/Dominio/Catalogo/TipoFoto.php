<?php

namespace App\Dominio\Catalogo;

use Filament\Support\Contracts\HasLabel;

/**
 * Maqueta: foto plana por color y cara, sobre la que se coloca el diseño.
 * Modelo y detalle: fotos de galería, opcionales (D30).
 */
enum TipoFoto: string implements HasLabel
{
    case Maqueta = 'maqueta';
    case Modelo = 'modelo';
    case Detalle = 'detalle';

    public function getLabel(): string
    {
        return match ($this) {
            self::Maqueta => 'Maqueta (foto plana)',
            self::Modelo => 'Con modelo',
            self::Detalle => 'Detalle',
        };
    }
}
