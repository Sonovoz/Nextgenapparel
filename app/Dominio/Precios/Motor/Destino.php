<?php

namespace App\Dominio\Precios\Motor;

final class Destino
{
    public function __construct(
        public readonly string $pais,
        public readonly string $codigoPostal = '',
    ) {}
}
