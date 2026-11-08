<?php

namespace App\Dominio\Carrito;

use App\Models\ZonaImpresion;

/**
 * Dónde va un diseño si el cliente no lo coloca a mano (packs, paso 5.7): como el visor
 * por defecto, al mayor tamaño que cabe en la zona, centrado y arriba del todo (D99).
 */
final class ColocacionInicial
{
    /** @param  float  $proporcion  ancho ÷ alto del diseño */
    public static function en(ZonaImpresion $zona, float $proporcion): array
    {
        $proporcion = max(0.05, $proporcion);
        if ($proporcion > $zona->ancho_max_mm / max(1, $zona->alto_max_mm)) {
            $ancho = $zona->ancho_max_mm;
            $alto = max(Impresion::MINIMO_MM, (int) floor($ancho / $proporcion));
        } else {
            $alto = $zona->alto_max_mm;
            $ancho = max(Impresion::MINIMO_MM, (int) floor($alto * $proporcion));
        }

        return ['ancho_mm' => $ancho, 'alto_mm' => $alto, 'desde_cuello_mm' => $zona->desde_cuello_mm, 'desde_centro_mm' => $zona->desde_centro_mm];
    }

    /** Proporción aproximada de un texto en una línea (la que dibuja el visor). */
    public static function deTexto(string $texto): float
    {
        return max(1.0, mb_strlen(trim($texto)) * 0.62);
    }
}
