<?php

namespace App\Dominio\Precios;

use InvalidArgumentException;

/** Aritmética de importes en céntimos (enteros). Porcentajes en centésimas: 1200 = 12 %. */
final class Importe
{
    /** Porcentaje de un importe, redondeado al céntimo (medio hacia arriba). */
    public static function porcentaje(int $centimos, int $centesimas): int
    {
        if ($centimos < 0 || $centesimas < 0) {
            throw new InvalidArgumentException('Importe y porcentaje no pueden ser negativos.');
        }

        return intdiv($centimos * $centesimas + 5000, 10000);
    }

    /**
     * Reparte un importe entre varias bases en proporción a cada una, sin
     * perder ni inventar céntimos (método del mayor resto).
     *
     * @param  array<int|string, int>  $bases
     * @return array<int|string, int>
     */
    public static function repartir(int $importe, array $bases): array
    {
        $total = array_sum($bases);
        if ($total <= 0) {
            return array_map(fn () => 0, $bases);
        }
        $importe = min($importe, $total);

        $partes = [];
        $restos = [];
        foreach ($bases as $clave => $base) {
            $partes[$clave] = intdiv($importe * $base, $total);
            $restos[$clave] = ($importe * $base) % $total;
        }
        arsort($restos);
        $sobra = $importe - array_sum($partes);
        foreach (array_keys($restos) as $clave) {
            if ($sobra-- <= 0) {
                break;
            }
            $partes[$clave]++;
        }

        return $partes;
    }
}
