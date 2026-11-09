<?php

namespace App\Dominio\Pedidos;

/**
 * Un diseño puesto en una zona, para componer una maqueta (D98, D200): su tamaño y posición en mm
 * (D99) y el logo (los bytes del original y el recorte útil, D114) o el texto con su letra y tinta.
 * Lo usan el pedido (impresiones del pedido) y el presupuesto (impresiones del carrito).
 */
final readonly class CapaMaqueta
{
    /** @param  array{0: int, 1: int, 2: int, 3: int}|null  $util  x, y, ancho y alto útiles en px */
    public function __construct(
        public int $zonaId,
        public int $anchoMm,
        public int $altoMm,
        public int $desdeCuelloMm,
        public int $desdeCentroMm,
        public ?string $logo = null,
        public ?array $util = null,
        public ?string $texto = null,
        public ?int $fuente = null,
        public ?string $tinta = null,
    ) {}
}
