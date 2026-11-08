<?php

namespace App\Dominio\Precios;

use App\Dominio\Precios\Motor\Cesta;
use App\Dominio\Precios\Motor\LineaCesta;
use App\Dominio\Precios\Motor\MotorPrecios;
use App\Models\Prenda;

/**
 * El precio que ve el cliente mientras configura una prenda (D142), siempre
 * calculado aquí y nunca en el navegador:
 *
 * - Sin cantidades: lo que cuesta una prenda del color elegido con los
 *   diseños puestos (la talla más barata + cada zona con diseño), con IVA.
 * - Con cantidades: el motor de precios con todas las líneas (un color por
 *   línea, sus tallas y las mismas zonas), con ofertas, mínimos y avisos. El
 *   envío se calcula al cerrar el pedido, cuando se conoce el destino.
 */
class Configurador
{
    public function __construct(private readonly MotorPrecios $motor) {}

    /**
     * @param  list<int>  $zonas  zonas con diseño
     * @param  array<int, array<int, int>>  $cantidades  color_id => [talla_id => unidades]
     * @return array{disenos: int, por_unidad: ?int, varia: bool, unidades: int, total: ?int, avisos: list<string>, impedimentos: list<string>, confirmable: bool}
     */
    public function calcular(Prenda $prenda, int $colorId, array $zonas, array $cantidades = []): array
    {
        $zonas = array_values(array_unique($zonas));
        $lineas = [];
        foreach ($cantidades as $color => $tallas) {
            if (array_sum($tallas) > 0) {
                $lineas[] = new LineaCesta($prenda->id, (int) $color, $tallas, $zonas);
            }
        }

        if ($lineas === []) {
            [$porUnidad, $varia] = $this->unaPrenda($prenda, $colorId, $zonas);

            return ['disenos' => count($zonas), 'por_unidad' => $porUnidad, 'varia' => $varia, 'unidades' => 0,
                'total' => null, 'avisos' => [], 'impedimentos' => [], 'confirmable' => false];
        }

        $resultado = $this->motor->calcular(new Cesta($lineas));
        $unidades = $resultado->unidades();

        return [
            'disenos' => count($zonas),
            'por_unidad' => $unidades > 0 ? intdiv($resultado->total() * 2 + $unidades, $unidades * 2) : null, // media, redondeo a la mitad hacia arriba
            'varia' => false,
            'unidades' => $unidades,
            'total' => $resultado->lineas === [] ? null : $resultado->total(),
            'avisos' => $resultado->textos('avisos'),
            'impedimentos' => $resultado->textos('impedimentos'),
            'confirmable' => $resultado->confirmable(),
        ];
    }

    /**
     * Una prenda del color con sus diseños, con IVA, y si cambia según la talla.
     * Null si no hay diseño, el color no se vende o alguna zona no se ofrece.
     *
     * @param  list<int>  $zonas
     * @return array{0: ?int, 1: bool}
     */
    private function unaPrenda(Prenda $prenda, int $colorId, array $zonas): array
    {
        if ($zonas === []) {
            return [null, false];
        }
        $precios = $prenda->variantes()->aLaVenta()->where('color_id', $colorId)->pluck('precio_base')->map(fn ($p) => (int) $p);
        $ofrecidas = $prenda->zonas()->where('zonas_impresion.activa', true)->whereIn('zonas_impresion.id', $zonas)->get();
        if ($precios->isEmpty() || $ofrecidas->count() !== count($zonas)) {
            return [null, false];
        }
        $sinIva = $precios->min() + (int) $ofrecidas->sum(fn ($z) => $z->pivot->precio);

        return [Iva::conIva($sinIva, Impuestos::tipoIva($prenda->clase_impuesto_id)), $precios->unique()->count() > 1];
    }
}
