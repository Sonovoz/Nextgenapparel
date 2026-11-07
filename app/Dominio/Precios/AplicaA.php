<?php

namespace App\Dominio\Precios;

use Filament\Support\Contracts\HasLabel;

/** Sobre qué parte del precio se descuenta. */
enum AplicaA: string implements HasLabel
{
    case Prendas = 'prendas';
    case Impresion = 'impresion';
    case Todo = 'todo';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prendas => 'Solo prendas',
            self::Impresion => 'Solo impresión',
            self::Todo => 'Prendas e impresión',
        };
    }
}
