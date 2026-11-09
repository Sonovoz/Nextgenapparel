<?php

namespace App\Dominio\Pagos;

final readonly class ResultadoDevolucion
{
    /** `dudosa`: no se sabe si se hizo (Redsys no contestó); hay que mirarlo en el TPV antes de repetirla. */
    public function __construct(public bool $hecha, public ?string $referencia = null, public ?string $error = null, public bool $dudosa = false) {}
}
