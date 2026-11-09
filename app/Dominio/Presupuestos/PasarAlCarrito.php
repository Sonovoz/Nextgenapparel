<?php

namespace App\Dominio\Presupuestos;

use App\Dominio\Carrito\EditarCarrito;
use App\Dominio\Carrito\LineaNoValida;
use App\Dominio\Carrito\ValorarCarrito;
use App\Dominio\Disenos\Dueno;
use App\Models\Carrito;
use App\Models\Presupuesto;

/**
 * Un presupuesto vigente vuelve al carrito (D187), con las comprobaciones de siempre y el precio que dé
 * el motor hoy; si el total cambia, se dice. Lo que ya no se vende (o un diseño que ya no está) se salta.
 */
final class PasarAlCarrito
{
    public function __construct(private readonly EditarCarrito $editar, private readonly ValorarCarrito $valorar) {}

    /** @return array{anadidas: int, omitidas: int, total: int} */
    public function __invoke(Presupuesto $presupuesto, Carrito $carrito, Dueno $dueno): array
    {
        $anadidas = 0;
        $omitidas = 0;
        foreach ($presupuesto->origen as $l) {
            try {
                $l['tipo'] === 'pack'
                    ? $this->editar->anadirPack($carrito, $dueno, (int) $l['pack_id'], (array) $l['elecciones'], (array) $l['impresiones'])
                    : $this->editar->anadirPrenda($carrito, $dueno, (int) $l['prenda_id'], (int) $l['color_id'], (array) $l['tallas'], (array) $l['impresiones']);
                $anadidas++;
            } catch (LineaNoValida) {
                $omitidas++;
            }
        }
        if ($anadidas > 0 && $presupuesto->convertido_en === null) {
            $presupuesto->update(['convertido_en' => now()]);
        }

        return ['anadidas' => $anadidas, 'omitidas' => $omitidas, 'total' => ($this->valorar)($carrito->fresh() ?? $carrito)->resultado->total()];
    }
}
