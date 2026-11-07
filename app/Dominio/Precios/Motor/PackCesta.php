<?php

namespace App\Dominio\Precios\Motor;

/** Un pack. Si el reparto es libre, el cliente elige color y tallas de cada prenda del pack. */
final class PackCesta
{
    /**
     * @param  array<int, array{color?: int, tallas?: array<int, int>}>  $elecciones  linea_pack_id => elección
     * @param  list<int>  $zonasExtra  zonas que se añaden y se cobran aparte
     */
    public function __construct(
        public readonly int $packId,
        public readonly array $elecciones = [],
        public readonly array $zonasExtra = [],
    ) {}
}
