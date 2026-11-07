<?php

namespace App\Dominio\Catalogo;

use App\Models\Color;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use Illuminate\Support\Facades\DB;

/**
 * Crea las variaciones color × talla que falten en una prenda (como
 * «Crear variaciones a partir de todos los atributos» de WooCommerce).
 * No toca las que ya existen.
 */
class GenerarVariantes
{
    /**
     * @param  list<int>  $colores
     * @param  list<int>  $tallas
     * @param  array<int, int>  $pesoPorTalla  gramos por id de talla (si falta, $pesoGramos)
     * @return int Variaciones creadas
     */
    public function __invoke(Prenda $prenda, array $colores, array $tallas, int $precioBase, int $coste, int $pesoGramos, int $stock = 0, array $pesoPorTalla = []): int
    {
        if ($precioBase < 0 || $coste < 0 || $pesoGramos <= 0 || $stock < 0) {
            throw new DatoCatalogoInvalido('Precio, coste y stock no pueden ser negativos, y el peso debe ser mayor que cero.');
        }

        return DB::transaction(function () use ($prenda, $colores, $tallas, $precioBase, $coste, $pesoGramos, $stock, $pesoPorTalla): int {
            $existentes = $prenda->variantes()->get(['color_id', 'talla_id'])
                ->map(fn (Variante $v) => $v->color_id.'-'.$v->talla_id)
                ->flip();
            $creadas = 0;

            foreach (Color::whereIn('id', $colores)->orderBy('orden')->get() as $color) {
                foreach (Talla::whereIn('id', $tallas)->orderBy('orden')->get() as $talla) {
                    if ($existentes->has($color->id.'-'.$talla->id)) {
                        continue;
                    }

                    $prenda->variantes()->create([
                        'color_id' => $color->id,
                        'talla_id' => $talla->id,
                        'sku' => Sku::para($prenda, $color, $talla),
                        'precio_base' => $precioBase,
                        'coste' => $coste,
                        'peso_gramos' => $pesoPorTalla[$talla->id] ?? $pesoGramos,
                        'stock' => $stock,
                    ]);
                    $creadas++;
                }
            }

            return $creadas;
        });
    }
}
