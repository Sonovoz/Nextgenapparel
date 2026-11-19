<?php

namespace App\Dominio\Catalogo;

use App\Models\Foto;
use App\Models\FotoZona;

/**
 * Al subir una maqueta, coloca las zonas de impresión de esa cara en una
 * posición de partida razonable (luego se ajustan a mano). Valores en
 * centésimas de porcentaje sobre la foto: x, y, ancho, alto.
 *
 * Medidas tomadas sobre fotos de 1000 × 1200 con el encuadre uniforme de la
 * ficha (torso de unos 475 px ≈ 54 cm en talla L, es decir ≈ 8,8 px/cm), con
 * las colocaciones habituales en serigrafía y DTF/DTI (D99):
 * - Frontal 28 × 38 cm, centrado, a 7,5 cm bajo el cuello delantero.
 * - Pecho 9 × 9 cm, en el pecho izquierdo de quien la lleva (derecha de la
 *   foto), centrado a 10 cm del centro y a 7,5 cm bajo el cuello.
 * - Espalda 30 × 40 cm, centrado, a 10 cm bajo el cuello trasero.
 */
class ZonasPorDefecto
{
    /** @var array<string, array{0: int, 1: int, 2: int, 3: int}> */
    public const POSICIONES = [
        'frontal' => [3770, 2260, 2460, 2790],
        // D256: las zonas con las que se vende.
        'pecho' => [3860, 2260, 2280, 2200],
        'bolsillo' => [5480, 2260, 880, 730],
        'espalda' => [3680, 2070, 2640, 2560],
        'espalda_xl' => [3460, 2070, 3080, 3080],
        'cuello' => [4690, 1450, 620, 370],
        // D257: en la foto lateral, la manga arriba y centrada.
        'manga_derecha' => [4560, 2300, 880, 730],
        'manga_izquierda' => [4560, 2300, 880, 730],
    ];

    /** Posiciones de partida de la primera versión (demasiado bajas y grandes). */
    public const ANTERIORES = [
        'frontal' => [3000, 2600, 4000, 4200],
        'bolsillo' => [5400, 2400, 1400, 1300], // antes «pecho» (D256)
        'espalda' => [2800, 2200, 4400, 5000],
    ];

    /** Zona desconocida: centrada. */
    public const POR_DEFECTO = [3000, 2500, 4000, 4500];

    public function __invoke(Foto $foto): int
    {
        if ($foto->tipo !== TipoFoto::Maqueta || $foto->cara === null) {
            return 0;
        }

        // D211: si otro color de la prenda ya tiene esta cara colocada, se parte de esas posiciones.
        CopiarZonas::desdeOtroColor($foto);

        $creadas = 0;

        foreach ($foto->prenda->zonas()->where('cara', $foto->cara->value)->get() as $zona) {
            if ($foto->zonas()->where('zona_impresion_id', $zona->id)->exists()) {
                continue;
            }

            [$x, $y, $ancho, $alto] = self::POSICIONES[$zona->clave] ?? self::POR_DEFECTO;
            FotoZona::create(['foto_id' => $foto->id, 'zona_impresion_id' => $zona->id, 'x' => $x, 'y' => $y, 'ancho' => $ancho, 'alto' => $alto]);
            $creadas++;
        }

        return $creadas;
    }

    /**
     * Mueve a la posición de partida actual las zonas que siguen exactamente
     * en la posición de partida anterior. Las que el equipo ya ajustó a mano
     * no se tocan.
     */
    public static function recolocarAnteriores(): int
    {
        $movidas = 0;

        foreach (FotoZona::with('zona')->get() as $fotoZona) {
            $clave = $fotoZona->zona?->clave;
            if (! isset(self::ANTERIORES[$clave])) {
                continue;
            }

            if ([$fotoZona->x, $fotoZona->y, $fotoZona->ancho, $fotoZona->alto] === self::ANTERIORES[$clave]) {
                [$x, $y, $ancho, $alto] = self::POSICIONES[$clave];
                $fotoZona->update(['x' => $x, 'y' => $y, 'ancho' => $ancho, 'alto' => $alto]);
                $movidas++;
            }
        }

        return $movidas;
    }
}
