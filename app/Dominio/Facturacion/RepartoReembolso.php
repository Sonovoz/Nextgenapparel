<?php

namespace App\Dominio\Facturacion;

use InvalidArgumentException;

/**
 * Reparte un reembolso parcial (con impuestos) entre los tipos de IVA de la factura (paso 6.6b, D178),
 * en proporción a lo que queda por rectificar en cada uno, sin errores de redondeo:
 *
 * 1. El importe se reparte entre los grupos con el método del mayor resto: la suma es exacta y
 *    ningún grupo recibe más de lo que le queda.
 * 2. En cada grupo, la base sale de quitar el IVA y el recargo al importe; el recargo se calcula
 *    sobre la base y el IVA es lo que falta, así base + IVA + recargo = importe al céntimo.
 */
final class RepartoReembolso
{
    /**
     * @param  list<array{tipo_iva: int, recargo: int, base: int, cuota: int, cuota_recargo: int}>  $pendiente  lo que queda por rectificar, en positivo
     * @return list<array{tipo_iva: int, recargo: int, base: int, cuota: int, cuota_recargo: int}> en positivo
     */
    public static function repartir(array $pendiente, int $importe): array
    {
        $totales = array_map(fn (array $g) => $g['base'] + $g['cuota'] + $g['cuota_recargo'], $pendiente);
        $total = array_sum($totales);
        if ($importe <= 0 || $importe > $total) {
            throw new InvalidArgumentException("El reembolso ({$importe}) tiene que ser mayor que 0 y no pasar de lo que queda por rectificar ({$total}).");
        }

        // 1. Mayor resto: parte entera de cada grupo y los céntimos sobrantes a los restos mayores.
        $partes = [];
        $restos = [];
        foreach ($totales as $i => $t) {
            $partes[$i] = intdiv($importe * $t, $total);
            $restos[$i] = ($importe * $t) % $total;
        }
        arsort($restos); // a igualdad de resto, el primero (arsort es estable)
        foreach (array_keys(array_slice($restos, 0, $importe - array_sum($partes), true)) as $i) {
            $partes[$i]++;
        }

        // 2. Base, recargo e IVA de cada parte.
        $reparto = [];
        foreach ($pendiente as $i => $g) {
            if ($partes[$i] === 0) {
                continue;
            }
            $divisor = 10000 + $g['tipo_iva'] + $g['recargo'];
            $base = intdiv($partes[$i] * 10000 * 2 + $divisor, $divisor * 2); // redondeo a la mitad hacia arriba
            $recargo = intdiv($base * $g['recargo'] * 2 + 10000, 20000);
            $reparto[] = ['tipo_iva' => $g['tipo_iva'], 'recargo' => $g['recargo'], 'base' => $base, 'cuota' => $partes[$i] - $base - $recargo, 'cuota_recargo' => $recargo];
        }

        return $reparto;
    }

    /**
     * Lo que queda por rectificar de una factura: su desglose menos el de las rectificativas parciales ya emitidas.
     *
     * @param  list<array{tipo_iva: int, recargo: int, base: int, cuota: int, cuota_recargo: int}>  $original
     * @param  list<list<array{tipo_iva: int, recargo: int, base: int, cuota: int, cuota_recargo: int}>>  $rectificadas  en negativo, como se guardan
     * @return list<array{tipo_iva: int, recargo: int, base: int, cuota: int, cuota_recargo: int}>
     */
    public static function pendiente(array $original, array $rectificadas): array
    {
        $grupos = [];
        foreach ($original as $g) {
            $grupos[$g['tipo_iva'].'-'.$g['recargo']] = $g;
        }
        foreach ($rectificadas as $desglose) {
            foreach ($desglose as $g) {
                $clave = $g['tipo_iva'].'-'.$g['recargo'];
                foreach (['base', 'cuota', 'cuota_recargo'] as $campo) {
                    $grupos[$clave][$campo] += $g[$campo]; // negativos: restan
                }
            }
        }

        return array_values(array_filter($grupos, fn (array $g) => $g['base'] + $g['cuota'] + $g['cuota_recargo'] !== 0));
    }
}
