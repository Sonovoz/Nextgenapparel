<?php

namespace App\Dominio\Sistema\Errores;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoError: string implements HasColor, HasLabel
{
    case Pendiente = 'pendiente';
    case Resuelto = 'resuelto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Resuelto => 'Resuelto',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente => 'gray',
            self::Resuelto => 'success',
        };
    }
}
