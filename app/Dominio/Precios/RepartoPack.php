<?php

namespace App\Dominio\Precios;

use Filament\Support\Contracts\HasLabel;

/** Reparto de tallas y color de un pack. */
enum RepartoPack: string implements HasLabel
{
    case Fijo = 'fijo';
    case Libre = 'libre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fijo => 'Fijo',
            self::Libre => 'El cliente reparte',
        };
    }
}
