<?php

namespace App\Dominio\Informes;

use App\Models\LineaPack;
use App\Models\Variante;
use Illuminate\Support\Facades\DB;

/**
 * Coste de una línea (v2, D191), en céntimos sin IVA: el coste de cada prenda lisa (variante) más el coste de
 * imprimir cada zona en esa prenda (Productos → zonas de la prenda), por unidad. Lo que falta cuenta 0.
 */
final class CosteLinea
{
    /**
     * @param  array<int, int>  $tallas  talla_id => unidades
     * @param  list<int>  $zonas
     */
    public static function prenda(int $prendaId, int $colorId, array $tallas, array $zonas): int
    {
        $costes = Variante::where('prenda_id', $prendaId)->where('color_id', $colorId)->whereIn('talla_id', array_keys($tallas))->pluck('coste', 'talla_id');
        $porPrenda = (int) DB::table('prenda_zona')->where('prenda_id', $prendaId)->whereIn('zona_impresion_id', $zonas)->sum('coste');
        $coste = 0;
        foreach ($tallas as $talla => $unidades) {
            $coste += ((int) ($costes[$talla] ?? 0) + $porPrenda) * $unidades;
        }

        return $coste;
    }

    /**
     * @param  array<int|string, array{prenda?: int, color?: int, tallas?: array<int|string, int>}>  $reparto  linea_pack_id => reparto congelado
     * @param  list<int>  $zonas
     */
    public static function pack(array $reparto, array $zonas): int
    {
        $coste = 0;
        foreach ($reparto as $lineaPackId => $r) {
            $prenda = (int) ($r['prenda'] ?? LineaPack::find($lineaPackId)?->prenda_id);
            $coste += self::prenda($prenda, (int) ($r['color'] ?? 0), array_map('intval', (array) ($r['tallas'] ?? [])), $zonas);
        }

        return $coste;
    }
}
