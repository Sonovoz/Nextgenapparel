<?php

namespace App\Dominio\Catalogo;

use Filament\Support\Contracts\HasLabel;

/** Cara de la prenda (fotos de maqueta y zonas de impresión). Los laterales, para las mangas (D257). */
enum Cara: string implements HasLabel
{
    case Delantera = 'delantera';
    case Trasera = 'trasera';
    case Derecha = 'derecha';
    case Izquierda = 'izquierda';

    public function getLabel(): string
    {
        return match ($this) {
            self::Delantera => 'Delantera',
            self::Trasera => 'Trasera',
            self::Derecha => 'Lateral derecho',
            self::Izquierda => 'Lateral izquierdo',
        };
    }
}
