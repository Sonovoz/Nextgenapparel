<?php

namespace App\Dominio\Sistema\Errores;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum NivelError: string implements HasColor, HasLabel
{
    case Critico = 'critico';
    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::Critico => 'Crítico',
            self::Error => 'Error',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Critico => 'danger',
            self::Error => 'warning',
        };
    }
}
