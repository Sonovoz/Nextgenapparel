<?php

namespace App\Dominio\Pagos;

final readonly class ResultadoDevolucion
{
    public function __construct(public bool $hecha, public ?string $referencia = null, public ?string $error = null) {}
}
