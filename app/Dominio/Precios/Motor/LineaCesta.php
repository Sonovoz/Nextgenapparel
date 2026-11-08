<?php

namespace App\Dominio\Precios\Motor;

/** Una prenda en un color, con sus unidades por talla y las zonas que se imprimen. */
final class LineaCesta
{
    /**
     * @param  array<int, int>  $tallas  talla_id => unidades
     * @param  list<int>  $zonas  ids de zonas de impresión
     */
    public function __construct(
        public readonly int $prendaId,
        public readonly int $colorId,
        public readonly array $tallas,
        public readonly array $zonas,
    ) {}
}
