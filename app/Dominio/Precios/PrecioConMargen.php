<?php

namespace App\Dominio\Precios;

use App\Models\Ajuste;
use App\Models\Prenda;

/**
 * D253: precio de venta sin IVA de una prenda de proveedor: su coste más el margen comercial (sobre el coste),
 * redondeado al alza a 5 céntimos. El margen general está en Ajustes → Proveedores; cada prenda puede tener el suyo.
 * Todo en céntimos enteros (regla 7).
 */
final class PrecioConMargen
{
    /** Margen que se aplica a la prenda, en %: el suyo o, si no tiene, el general. */
    public static function margen(?Prenda $prenda = null): int
    {
        $propio = $prenda?->getAttribute('margen');

        return $propio !== null ? (int) $propio : (int) Ajuste::obtener('proveedores.margen');
    }

    public static function venta(int $coste, int $margen): int
    {
        if ($coste <= 0) {
            return 0;
        }
        $bruto = intdiv($coste * (100 + max(0, $margen)) + 99, 100); // al alza al céntimo

        return (int) (ceil($bruto / 5) * 5);
    }
}
