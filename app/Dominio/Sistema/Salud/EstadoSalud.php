<?php

namespace App\Dominio\Sistema\Salud;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EstadoSalud: string implements HasColor, HasLabel
{
    case Correcto = 'correcto';
    case Aviso = 'aviso';
    case Fallo = 'fallo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Correcto => 'Correcto',
            self::Aviso => 'Aviso',
            self::Fallo => 'Fallo',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Correcto => 'success',
            self::Aviso => 'warning',
            self::Fallo => 'danger',
        };
    }
}
