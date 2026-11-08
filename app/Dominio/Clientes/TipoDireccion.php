<?php

namespace App\Dominio\Clientes;

use Filament\Support\Contracts\HasLabel;

enum TipoDireccion: string implements HasLabel
{
    case Facturacion = 'facturacion';
    case Envio = 'envio';

    public function getLabel(): string
    {
        return __('clientes.direccion_'.$this->value);
    }
}
