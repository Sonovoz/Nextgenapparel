<?php

namespace App\Dominio\Catalogo;

use App\Models\Foto;
use App\Models\FotoZona;

/**
 * Al subir una maqueta, coloca las zonas de impresión de esa cara en una
 * posición de partida razonable (luego se ajustan a mano). Valores en
 * centésimas de porcentaje sobre la foto: x, y, ancho, alto.
 */
class ZonasPorDefecto
{
    /** @var array<string, array{0: int, 1: int, 2: int, 3: int}> */
    public const POSICIONES = [
        'frontal' => [3000, 2600, 4000, 4200],
        'pecho' => [5400, 2400, 1400, 1300],
        'espalda' => [2800, 2200, 4400, 5000],
    ];

    /** Zona desconocida: centrada. */
    public const POR_DEFECTO = [3000, 2500, 4000, 4500];

    public function __invoke(Foto $foto): int
    {
        if ($foto->tipo !== TipoFoto::Maqueta || $foto->cara === null) {
            return 0;
        }

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
}
