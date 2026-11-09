<?php

namespace App\Dominio\Carrito;

use App\Dominio\Precios\Motor\Cesta;
use App\Dominio\Precios\Motor\Destino;
use App\Dominio\Precios\Motor\LineaCesta;
use App\Dominio\Precios\Motor\MotorPrecios;
use App\Dominio\Precios\Motor\PackCesta;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\LineaCarrito;

/**
 * Valora el carrito con el motor de precios (regla 3): el carrito no guarda importes.
 * Las líneas que ya no se pueden comprar se marcan y se dejan fuera del cálculo, para
 * que el cliente vea el precio del resto y sepa qué tiene que cambiar; mientras haya
 * alguna, el pedido no se puede cerrar.
 */
final class ValorarCarrito
{
    public function __construct(
        private readonly MotorPrecios $motor,
        private readonly ComprobarLinea $comprobar,
    ) {}

    /** @param  list<string>  $cuponesUsados  los da el pedido (fase 6) */
    public function __invoke(Carrito $carrito, ?Destino $destino = null, bool $recargo = false, array $cuponesUsados = []): Valoracion
    {
        $ahora = now();
        $carrito->load(['lineas.impresiones.diseno', 'lineas.impresiones.zona']);

        $valoradas = [];
        $lineas = [];
        $packs = [];
        foreach ($carrito->lineas as $linea) {
            $problemas = $this->comprobar->linea($linea, $ahora);
            $valoradas[] = ['linea' => $linea, 'problemas' => $problemas, 'calidades' => $this->calidades($linea)];
            if ($problemas !== []) {
                continue;
            }

            $zonas = $linea->impresiones->pluck('zona_impresion_id')->all();
            if ($linea->tipo === TipoLinea::Pack) {
                $incluidas = $linea->pack->zonas()->pluck('zonas_impresion.id')->all();
                $packs[] = new PackCesta($linea->pack_id, $linea->elecciones ?? [], array_values(array_diff($zonas, $incluidas)));
            } else {
                $lineas[] = new LineaCesta($linea->prenda_id, $linea->color_id, $linea->unidadesPorTalla(), $zonas);
            }
        }

        // La tarifa de su grupo (D192), si el carrito es de un cliente de un grupo.
        $grupoId = $carrito->cliente_id === null ? null : Cliente::whereKey($carrito->cliente_id)->value('grupo_cliente_id');
        $resultado = $this->motor->calcular(new Cesta($lineas, $packs, $carrito->cupon, $recargo, $destino, $cuponesUsados, $ahora, $grupoId === null ? null : (int) $grupoId));

        return new Valoracion($valoradas, $resultado);
    }

    /** Calidad de cada diseño a su tamaño de impresión (D101): la que vale para el pedido. */
    private function calidades(LineaCarrito $linea): array
    {
        $calidades = [];
        foreach ($linea->impresiones as $impresion) {
            if ($impresion->diseno !== null) {
                $calidades[$impresion->zona_impresion_id] = $impresion->diseno->calidad($impresion->ancho_mm, $impresion->alto_mm);
            }
        }

        return $calidades;
    }
}
