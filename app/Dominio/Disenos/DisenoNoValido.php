<?php

namespace App\Dominio\Disenos;

use RuntimeException;

/** El archivo no se acepta como diseño; `motivo` es la clave del mensaje en lang/disenos.php (error_*). */
final class DisenoNoValido extends RuntimeException
{
    /** @param array<string, int|string> $datos */
    public function __construct(public readonly string $motivo, public readonly array $datos = [])
    {
        parent::__construct("Diseño no válido: {$motivo}");
    }

    public function mensaje(): string
    {
        return __('disenos.error_'.$this->motivo, $this->datos);
    }
}
