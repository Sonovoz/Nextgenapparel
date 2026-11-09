<?php

namespace App\Dominio\Pagos;

/** Resultado de un cobro, ya comprobada su firma: hecho o rechazado. */
final readonly class EventoPasarela
{
    public function __construct(
        public string $id,
        public bool $cobrado,
        public string $referencia,
        public ?int $importe = null,
        public ?string $error = null,
    ) {}
}
