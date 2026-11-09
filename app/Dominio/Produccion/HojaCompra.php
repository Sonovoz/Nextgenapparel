<?php

namespace App\Dominio\Produccion;

use App\Dominio\Pedidos\EstadoPedido;
use App\Models\LineaPack;
use App\Models\Pedido;
use App\Models\Talla;
use App\Models\Variante;

/**
 * Hoja de compra (paso 6.5b, ficha § 8): las prendas lisas que hacen falta para los pedidos
 * pagados que aún no están en producción, agrupadas por proveedor y variante y descontando
 * el stock. Las de un pack salen de lo que repartió el cliente.
 */
final class HojaCompra
{
    /** Pagados y todavía sin producir: lo que hay que tener antes de imprimir. */
    public const ESTADOS = [EstadoPedido::Pagado, EstadoPedido::DisenoEnRevision, EstadoPedido::RequiereCambios];

    /**
     * @return list<array{proveedor: string, prenda: string, referencia: ?string, color: string, talla: string, sku: ?string, necesarias: int, stock: int, comprar: int, pedidos: list<string>}>
     */
    public static function filas(): array
    {
        $necesarias = []; // [prenda_id][color_id][talla_id] => unidades
        $pedidosDe = [];
        $sumar = function (int $prenda, int $color, int $talla, int $unidades, string $numero) use (&$necesarias, &$pedidosDe) {
            $necesarias[$prenda][$color][$talla] = ($necesarias[$prenda][$color][$talla] ?? 0) + $unidades;
            $pedidosDe["{$prenda}-{$color}-{$talla}"][$numero] = true;
        };
        $pedidos = Pedido::whereIn('estado', array_map(fn ($e) => $e->value, self::ESTADOS))->with('lineas')->orderBy('id')->get();
        foreach ($pedidos as $pedido) {
            foreach ($pedido->lineas as $linea) {
                if ($linea->tipo === 'prenda' && $linea->prenda_id !== null && $linea->color_id !== null) {
                    foreach ($linea->tallas ?? [] as $t) {
                        $sumar($linea->prenda_id, $linea->color_id, (int) $t['talla_id'], (int) $t['unidades'], (string) $pedido->numero);
                    }
                } elseif ($linea->tipo === 'pack') {
                    foreach ((array) $linea->elecciones as $lineaPackId => $eleccion) {
                        $prenda = isset($eleccion['prenda']) ? (int) $eleccion['prenda'] : LineaPack::find($lineaPackId)?->prenda_id;
                        foreach ((array) ($eleccion['tallas'] ?? []) as $talla => $unidades) {
                            if ($prenda !== null && isset($eleccion['color']) && (int) $unidades > 0) {
                                $sumar($prenda, (int) $eleccion['color'], (int) $talla, (int) $unidades, (string) $pedido->numero);
                            }
                        }
                    }
                }
            }
        }

        $filas = [];
        foreach ($necesarias as $prenda => $colores) {
            foreach ($colores as $color => $tallas) {
                foreach ($tallas as $talla => $unidades) {
                    $variante = Variante::with('prenda.proveedor', 'color')->where(['prenda_id' => $prenda, 'color_id' => $color, 'talla_id' => $talla])->first();
                    $stock = max(0, (int) $variante?->stock);
                    $tallaModelo = Talla::find($talla);
                    $filas[] = [
                        'proveedor' => (string) ($variante?->prenda?->proveedor->nombre ?? 'Sin proveedor'),
                        'prenda' => (string) ($variante?->prenda?->getTranslation('nombre', 'es') ?? 'Prenda '.$prenda),
                        'referencia' => $variante?->prenda?->referencia_proveedor,
                        'color' => (string) ($variante?->color?->getTranslation('nombre', 'es') ?? 'Color '.$color),
                        'talla' => (string) ($tallaModelo->nombre ?? $talla),
                        'orden_talla' => [(int) $tallaModelo?->orden, $talla],
                        'sku' => $variante?->sku,
                        'necesarias' => $unidades,
                        'stock' => $stock,
                        'comprar' => max(0, $unidades - $stock),
                        'pedidos' => array_keys($pedidosDe["{$prenda}-{$color}-{$talla}"]),
                    ];
                }
            }
        }
        usort($filas, fn ($a, $b) => [$a['proveedor'], $a['prenda'], $a['color'], $a['orden_talla']] <=> [$b['proveedor'], $b['prenda'], $b['color'], $b['orden_talla']]);

        return array_map(function (array $fila) {
            unset($fila['orden_talla']);

            return $fila;
        }, $filas);
    }

    public static function csv(): string
    {
        $salida = fopen('php://temp', 'r+');
        fputcsv($salida, ['Proveedor', 'Prenda', 'Referencia', 'Color', 'Talla', 'SKU', 'Necesarias', 'Stock', 'Comprar', 'Pedidos'], ';');
        foreach (self::filas() as $f) {
            fputcsv($salida, [$f['proveedor'], $f['prenda'], $f['referencia'], $f['color'], $f['talla'], $f['sku'], $f['necesarias'], $f['stock'], $f['comprar'], implode(' ', $f['pedidos'])], ';');
        }
        rewind($salida);

        return "\xEF\xBB\xBF".stream_get_contents($salida); // BOM: Excel abre bien las tildes
    }
}
