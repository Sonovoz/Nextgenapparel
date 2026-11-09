<?php

namespace App\Dominio\Clientes;

use Filament\Support\Contracts\HasLabel;

/** Tipo de cliente (ficha § 5): decide qué datos fiscales se piden y si cabe el recargo de equivalencia. */
enum TipoCliente: string implements HasLabel
{
    case Particular = 'particular';
    case Autonomo = 'autonomo';
    case Empresa = 'empresa';

    public function getLabel(): string
    {
        return __('clientes.tipo_'.$this->value);
    }

    /** El recargo de equivalencia solo es de comerciantes minoristas: autónomos y empresas. */
    public function admiteRecargo(): bool
    {
        return $this !== self::Particular;
    }
}
