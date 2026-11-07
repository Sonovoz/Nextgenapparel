<?php

namespace App\Dominio\Disenos;

use App\Models\Ajuste;
use InvalidArgumentException;

/**
 * Calidad de impresión de un diseño (ficha § 7):
 * ppp efectivo = píxeles ÷ (cm de impresión ÷ 2,54).
 *
 * Aritmética entera y umbrales configurables en Ajustes → General. El visor
 * de la tienda usa la misma fórmula y los mismos umbrales para avisar al
 * momento; la comprobación que vale para el pedido es esta (fase 4).
 */
final class Calidad
{
    /** ppp efectivo redondeado al entero más cercano. */
    public static function pppEfectivo(int $pixeles, int $milimetros): int
    {
        if ($pixeles <= 0 || $milimetros <= 0) {
            throw new InvalidArgumentException('Los píxeles y los milímetros deben ser positivos.');
        }

        // px × 25,4 / mm  →  (px × 254) / (mm × 10), redondeado.
        return intdiv($pixeles * 254 * 2 + $milimetros * 10, $milimetros * 10 * 2);
    }

    /** Tamaño máximo (mm) al que el diseño alcanza un ppp dado. */
    public static function tamanoMaximoMm(int $pixeles, int $ppp): int
    {
        if ($pixeles <= 0 || $ppp <= 0) {
            throw new InvalidArgumentException('Los píxeles y el ppp deben ser positivos.');
        }

        return intdiv($pixeles * 254, $ppp * 10);
    }

    /** @return array{correcto: int, aceptable: int, minimo: int} */
    public static function umbrales(): array
    {
        return [
            'correcto' => (int) Ajuste::obtener('disenos.ppp_correcto'),
            'aceptable' => (int) Ajuste::obtener('disenos.ppp_aceptable'),
            'minimo' => (int) Ajuste::obtener('disenos.ppp_minimo'),
        ];
    }

    /** @param array{correcto: int, aceptable: int, minimo: int}|null $umbrales */
    public static function nivel(int $ppp, ?array $umbrales = null): NivelCalidad
    {
        $u = $umbrales ?? self::umbrales();

        return match (true) {
            $ppp >= $u['correcto'] => NivelCalidad::Correcta,
            $ppp >= $u['aceptable'] => NivelCalidad::Buena,
            $ppp >= $u['minimo'] => NivelCalidad::Justa,
            default => NivelCalidad::Baja,
        };
    }

    /**
     * Calidad del lado que peor queda (ancho o alto) al tamaño de impresión.
     *
     * @return array{ppp: int, nivel: NivelCalidad}
     */
    public static function evaluar(int $anchoPx, int $altoPx, int $anchoMm, int $altoMm, ?array $umbrales = null): array
    {
        $ppp = min(self::pppEfectivo($anchoPx, $anchoMm), self::pppEfectivo($altoPx, $altoMm));

        return ['ppp' => $ppp, 'nivel' => self::nivel($ppp, $umbrales)];
    }
}
