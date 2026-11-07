<?php

namespace App\Dominio\Precios;

use App\Models\Ajuste;
use InvalidArgumentException;

/**
 * IVA sobre importes en céntimos. Los tipos van en centésimas de porcentaje
 * (2100 = 21 %) para no usar decimales. Redondeo al céntimo, medio hacia arriba.
 */
class Iva
{
    public static function general(): int
    {
        return (int) Ajuste::obtener('impuestos.iva_general');
    }

    public static function conIva(int $centimosSinIva, ?int $tipo = null): int
    {
        $tipo ??= self::general();

        if ($centimosSinIva < 0 || $tipo < 0) {
            throw new InvalidArgumentException('Importe y tipo de IVA no pueden ser negativos.');
        }

        // Aritmética entera: (importe × (10000 + tipo) + 5000) ÷ 10000.
        return intdiv($centimosSinIva * (10000 + $tipo) + 5000, 10000);
    }
}
