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
 * Lo que ya está en el carrito no se vuelve a poner: al pedir el presupuesto el carrito sigue igual, y
 * pulsar «Pasar al carrito» duplicaba el pedido.
 */
final class PasarAlCarrito
{
    public function __construct(private readonly EditarCarrito $editar, private readonly ValorarCarrito $valorar) {}

    /** @return array{anadidas: int, omitidas: int, ya: int, total: int} */
    public function __invoke(Presupuesto $presupuesto, Carrito $carrito, Dueno $dueno): array
    {
        $anadidas = 0;
        $omitidas = 0;
        $ya = 0;
        $enCarrito = self::huellas($carrito);
        foreach ($presupuesto->origen as $l) {
            $huella = HuellaLinea::de((array) $l);
            if (($enCarrito[$huella] ?? 0) > 0) {
                $enCarrito[$huella]--;
                $ya++;

                continue;
            }
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

        return ['anadidas' => $anadidas, 'omitidas' => $omitidas, 'ya' => $ya, 'total' => ($this->valorar)($carrito->fresh() ?? $carrito)->resultado->total()];
    }

    /** ¿El carrito ya tiene todas las líneas del presupuesto? (entonces se ofrece volver al carrito, no pasarlo) */
    public static function yaEnCarrito(Presupuesto $presupuesto, ?Carrito $carrito): bool
    {
        if ($carrito === null || $presupuesto->origen === []) {
            return false;
        }
        $enCarrito = self::huellas($carrito);
        foreach ($presupuesto->origen as $l) {
            $huella = HuellaLinea::de((array) $l);
            if (($enCarrito[$huella] ?? 0) === 0) {
                return false;
            }
            $enCarrito[$huella]--;
        }

        return true;
    }

    /** @return array<string, int> huella => cuántas líneas iguales hay en el carrito */
    private static function huellas(Carrito $carrito): array
    {
        $huellas = [];
        foreach ($carrito->lineas()->with('impresiones.diseno')->get() as $linea) {
            $h = HuellaLinea::de(CrearPresupuesto::origenDe($linea));
            $huellas[$h] = ($huellas[$h] ?? 0) + 1;
        }

        return $huellas;
    }
}
