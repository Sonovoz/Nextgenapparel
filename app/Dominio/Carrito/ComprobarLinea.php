<?php

namespace App\Dominio\Carrito;

use App\Dominio\Precios\Motor\MotorPrecios;
use App\Models\Diseno;
use App\Models\LineaCarrito;
use App\Models\Pack;
use App\Models\Prenda;
use App\Models\ZonaImpresion;
use DateTimeInterface;

/**
 * Lo que impide comprar una línea del carrito (paso 5.5): se comprueba al añadirla y
 * cada vez que se valora el carrito, porque el catálogo cambia mientras el carrito
 * espera (una talla que se retira, un diseño que se borra…). Devuelve claves de
 * lang/{es,en}/carrito.php; vacío si la línea se puede comprar. El reparto de los
 * packs lo comprueba el motor de precios, que es quien lo valora.
 */
final class ComprobarLinea
{
    /**
     * @param  array<int|string, mixed>  $tallas  talla_id => unidades
     * @param  list<Impresion>  $impresiones
     * @return list<string>
     */
    public function prenda(?int $prendaId, ?int $colorId, array $tallas, array $impresiones): array
    {
        $prenda = $prendaId === null ? null : Prenda::where('activa', true)->find($prendaId);
        if ($prenda === null || $colorId === null) {
            return ['prenda_no_disponible'];
        }

        $problemas = [];
        $unidades = self::unidades($tallas);
        if ($unidades === null) {
            $problemas[] = 'cantidad_no_valida';
        } elseif ($unidades === []) {
            $problemas[] = 'sin_unidades';
        } else {
            $aLaVenta = $prenda->variantes()->aLaVenta()->where('color_id', $colorId)
                ->whereIn('talla_id', array_keys($unidades))->count();
            if ($aLaVenta !== count($unidades)) {
                $problemas[] = 'talla_no_disponible';
            }
        }

        $ofrecidas = $prenda->zonas()->where('zonas_impresion.activa', true)->pluck('zonas_impresion.id')->all();

        return [...$problemas, ...$this->impresiones($impresiones, $ofrecidas, obligatorias: [])];
    }

    /**
     * @param  list<Impresion>  $impresiones  las zonas del pack y las que se añaden
     * @return list<string>
     */
    public function pack(?int $packId, array $impresiones, DateTimeInterface $ahora): array
    {
        $pack = $packId === null ? null : Pack::vigentes($ahora)->with(['lineas.prenda', 'zonas'])->find($packId);
        if ($pack === null || $pack->lineas->contains(fn ($l) => $l->prenda === null || ! $l->prenda->activa)) {
            return ['pack_no_disponible'];
        }

        $incluidas = $pack->zonas->where('activa', true)->pluck('id')->all();
        if (count($incluidas) !== $pack->zonas->count()) {
            return ['pack_no_disponible'];
        }
        // Una zona extra se puede añadir si la ofrecen todas las prendas del pack (D106).
        $extra = null;
        foreach ($pack->lineas as $lineaPack) {
            $suyas = $lineaPack->prenda->zonas()->where('zonas_impresion.activa', true)->pluck('zonas_impresion.id')->all();
            $extra = $extra === null ? $suyas : array_values(array_intersect($extra, $suyas));
        }

        return $this->impresiones($impresiones, array_values(array_unique([...$incluidas, ...($extra ?? [])])), obligatorias: $incluidas);
    }

    /** @return list<string> */
    public function linea(LineaCarrito $linea, DateTimeInterface $ahora): array
    {
        $impresiones = self::deLinea($linea);

        return $linea->tipo === TipoLinea::Pack
            ? $this->pack($linea->pack_id, $impresiones, $ahora)
            : $this->prenda($linea->prenda_id, $linea->color_id, $linea->tallas ?? [], $impresiones);
    }

    /** @return list<Impresion> */
    public static function deLinea(LineaCarrito $linea): array
    {
        return $linea->impresiones->map(fn ($i) => new Impresion(
            $i->zona_impresion_id, $i->diseno_id, $i->ancho_mm, $i->alto_mm, $i->desde_cuello_mm, $i->desde_centro_mm,
        ))->all();
    }

    /**
     * Unidades válidas por talla (enteros de 0 al máximo del motor; el 0 se quita).
     *
     * @param  array<int|string, mixed>  $tallas
     * @return array<int, int>|null null si alguna cantidad no es válida
     */
    public static function unidades(array $tallas): ?array
    {
        $limpias = [];
        foreach ($tallas as $talla => $unidades) {
            if (! is_int($unidades) || $unidades < 0 || $unidades > MotorPrecios::MAXIMO_UNIDADES || ! ctype_digit((string) $talla)) {
                return null;
            }
            if ($unidades > 0) {
                $limpias[(int) $talla] = $unidades;
            }
        }

        return $limpias;
    }

    /**
     * @param  list<Impresion>  $impresiones
     * @param  list<int>  $permitidas  zonas que se pueden imprimir
     * @param  list<int>  $obligatorias  zonas que deben llevar diseño (las del pack)
     * @return list<string>
     */
    private function impresiones(array $impresiones, array $permitidas, array $obligatorias): array
    {
        if ($impresiones === []) {
            return ['sin_impresion'];
        }
        $zonas = array_map(fn (Impresion $i) => $i->zonaId, $impresiones);
        $problemas = [];
        if (count($zonas) !== count(array_unique($zonas)) || array_diff($zonas, $permitidas) !== []) {
            $problemas[] = 'zona_no_disponible';
        }
        if (array_diff($obligatorias, $zonas) !== []) {
            $problemas[] = 'faltan_disenos_pack';
        }

        $medidas = ZonaImpresion::whereIn('id', $zonas)->get(['id', 'ancho_max_mm', 'alto_max_mm'])->keyBy('id');
        $existen = Diseno::whereIn('id', array_filter(array_map(fn (Impresion $i) => $i->disenoId, $impresiones)))->pluck('id')->flip();
        foreach ($impresiones as $impresion) {
            $zona = $medidas->get($impresion->zonaId);
            if ($impresion->disenoId === null || ! $existen->has($impresion->disenoId)) {
                $problemas[] = 'diseno_no_disponible';
            }
            if ($zona !== null && (
                $impresion->anchoMm < Impresion::MINIMO_MM || $impresion->altoMm < Impresion::MINIMO_MM
                || $impresion->anchoMm > $zona->ancho_max_mm || $impresion->altoMm > $zona->alto_max_mm
                || $impresion->desdeCuelloMm < 0 || $impresion->desdeCuelloMm > Impresion::MAXIMO_POSICION_MM
                || abs($impresion->desdeCentroMm) > Impresion::MAXIMO_POSICION_MM)) {
                $problemas[] = 'medida_no_valida';
            }
        }

        return array_values(array_unique($problemas));
    }
}
