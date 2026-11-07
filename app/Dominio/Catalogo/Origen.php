<?php

namespace App\Dominio\Catalogo;

use Filament\Support\Contracts\HasLabel;

/** Origen de un dato del catálogo: cargado a mano o desde la API de un proveedor (D13, D30). */
enum Origen: string implements HasLabel
{
    case Manual = 'manual';
    case Api = 'api';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Api => 'API del proveedor',
        };
    }
}
