<?php

namespace App\Dominio\Carrito;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Carrito;

/**
 * Borra los carritos que nadie usa desde hace más de los días de Ajustes → General
 * (paso 5.5). Sus diseños de visitantes sin cuenta los borra después la limpieza de
 * diseños, cuando ya no están en ningún carrito.
 */
final class LimpiarCarritos
{
    public function __invoke(): int
    {
        $borrados = 0;
        Carrito::where('updated_at', '<', now()->subDays(CarritoActual::dias()))
            ->chunkById(200, function ($carritos) use (&$borrados): void {
                foreach ($carritos as $carrito) {
                    $carrito->delete(); // líneas e impresiones en cascada
                    $borrados++;
                }
            });

        if ($borrados > 0) {
            Actividad::apuntar('carrito', 'Limpieza de carritos', propiedades: ['carritos borrados' => $borrados]);
        }

        return $borrados;
    }
}
