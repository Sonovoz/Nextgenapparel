<?php

namespace App\Dominio\Catalogo;

use App\Models\Prenda;
use App\Models\ZonaImpresion;

/**
 * D223: la impresión incluida en un pack es una lista de zonas; cada prenda del pack se imprime
 * solo en las que ella ofrece (activas). Un polo sin «Pecho» lleva el resto y no es un error.
 * Solo falla si una prenda no ofrece ninguna: no llevaría nada impreso.
 */
final class ZonasPack
{
    /**
     * @param  list<int>  $zonasPack
     * @return list<int> las zonas del pack que se imprimen en esa prenda
     */
    public static function dePrenda(array $zonasPack, int $prendaId): array
    {
        $ofrecidas = Prenda::find($prendaId)?->zonas()->where('zonas_impresion.activa', true)->pluck('zonas_impresion.id')->all() ?? [];

        return array_values(array_intersect(array_map('intval', $zonasPack), array_map('intval', $ofrecidas)));
    }

    /**
     * Nombres de las zonas del pack que lleva esa prenda, en el orden del pack.
     *
     * @param  list<int>  $zonasPack
     */
    public static function nombres(array $zonasPack, int $prendaId): string
    {
        $si = self::dePrenda($zonasPack, $prendaId);

        return ZonaImpresion::whereIn('id', $si)->get()->sortBy(fn (ZonaImpresion $z) => array_search($z->id, $si, true))
            ->map(fn (ZonaImpresion $z) => (string) $z->nombre)->implode(', ');
    }

    /**
     * Para el backend y la página del pack: por prenda, en qué zonas se imprime y cuáles no lleva.
     *
     * @param  list<int>  $zonasPack
     * @param  list<int>  $prendas
     * @return list<array{prenda: string, si: list<string>, no: list<string>}>
     */
    public static function resumen(array $zonasPack, array $prendas): array
    {
        $nombres = ZonaImpresion::whereIn('id', $zonasPack)->get()->mapWithKeys(fn (ZonaImpresion $z) => [$z->id => (string) $z->nombre]);
        $resumen = [];
        foreach (array_unique($prendas) as $prendaId) {
            $prenda = Prenda::find($prendaId);
            if ($prenda === null) {
                continue;
            }
            $si = self::dePrenda($zonasPack, $prenda->id);
            $resumen[] = [
                'prenda' => (string) $prenda->nombre,
                'si' => array_map(fn (int $id) => $nombres[$id] ?? '?', $si),
                'no' => array_values(array_map(fn (int $id) => $nombres[$id] ?? '?', array_diff(array_map('intval', $zonasPack), $si))),
            ];
        }

        return $resumen;
    }
}
