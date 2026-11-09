<?php

namespace App\Dominio\Pagos;

final readonly class PeticionPago
{
    public function __construct(
        public int $pedidoId,
        public string $numero,
        public int $importe,
        public string $idioma,
        public string $urlVuelta,
        public ?string $urlAviso,
        public ?string $metodo = null,
    ) {}
}
