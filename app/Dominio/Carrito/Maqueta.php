<?php

namespace App\Dominio\Carrito;

use App\Dominio\Catalogo\Cara;
use App\Dominio\Catalogo\TipoFoto;
use App\Models\Foto;
use App\Models\FotoZona;
use App\Models\ImpresionCarrito;
use App\Models\LineaCarrito;
use App\Models\ZonaImpresion;
use Illuminate\Support\Facades\Storage;

/**
 * Maqueta 2D de una línea del carrito (paso 5.6): la foto de maqueta de cada cara con
 * sus diseños encima, colocados a partir de los mm guardados (D99). Es la operación
 * inversa del visor: de mm a porcentajes de la foto.
 */
final class Maqueta
{
    /** @return list<array{cara: string, url: string, alt: ?string, capas: list<array<string, mixed>>}> */
    public static function de(LineaCarrito $linea): array
    {
        [$prendaId, $colorId] = self::prendaYColor($linea);
        if ($prendaId === null || $colorId === null) {
            return [];
        }
        $porCara = $linea->impresiones->groupBy(fn ($i) => $i->zona?->cara?->value);
        $fotos = Foto::with('zonas')->where('prenda_id', $prendaId)->where('color_id', $colorId)
            ->where('tipo', TipoFoto::Maqueta)->get()->keyBy(fn (Foto $f) => $f->cara?->value);

        $caras = [];
        foreach (Cara::cases() as $cara) {
            $foto = $fotos->get($cara->value);
            if ($foto === null || ! $porCara->has($cara->value)) {
                continue;
            }
            $capas = [];
            $aspecto = self::aspecto($foto);
            foreach ($porCara[$cara->value] as $impresion) {
                $rect = $foto->zonas->firstWhere('zona_impresion_id', $impresion->zona_impresion_id);
                if ($rect !== null && $impresion->zona !== null) {
                    $capas[] = [...self::posicion($impresion, $rect, $cara, $aspecto), 'impresion' => $impresion];
                }
            }
            $caras[] = ['cara' => $cara->value, 'url' => $foto->url(), 'alt' => $foto->texto_alternativo ?: null, 'capas' => $capas];
        }

        return $caras;
    }

    /**
     * Porcentajes de la foto (left, top, width, height) del diseño. De frente, la izquierda
     * de quien la lleva queda a la derecha de la foto; de espaldas, a la izquierda.
     *
     * @return array{left: float, top: float, width: float, height: float}
     */
    public static function posicion(ImpresionCarrito $i, FotoZona $rect, Cara $cara, float $aspectoFoto = 5 / 6): array
    {
        return self::rectangulo($i->zona, $rect, $cara, $i->ancho_mm, $i->alto_mm, $i->desde_cuello_mm, $i->desde_centro_mm, $aspectoFoto);
    }

    /** Ancho / alto de la foto (D244). Si no se puede leer, 5:6, la proporción de las maquetas. */
    public static function aspecto(Foto $foto): float
    {
        $medidas = @getimagesize(Storage::disk(Foto::DISCO)->path($foto->ruta));

        return $medidas !== false && $medidas[1] > 0 ? $medidas[0] / $medidas[1] : 5 / 6;
    }

    /**
     * Lo mismo a partir de las medidas en mm: lo usan el carrito y las maquetas del pedido (paso 6.2).
     *
     * D244: el diseño conserva siempre su proporción. El rectángulo dibujado sobre la foto puede no tener la misma
     * proporción que la zona en mm; si el ancho y el alto se escalaran cada uno con su eje, el diseño saldría estirado.
     * Como el visor, se escala con el lado que limita (el ancho en un diseño apaisado, el alto en uno vertical) y el
     * otro sale de la proporción del diseño, con la proporción real de la foto ($aspectoFoto = ancho / alto).
     *
     * @return array{left: float, top: float, width: float, height: float}
     */
    public static function rectangulo(ZonaImpresion $zona, FotoZona $rect, Cara $cara, int $anchoMm, int $altoMm, int $desdeCuelloMm, int $desdeCentroMm, float $aspectoFoto = 5 / 6): array
    {
        $desplazamiento = $desdeCentroMm - $zona->desde_centro_mm; // negativo: hacia su izquierda
        $enFoto = $cara === Cara::Delantera ? -$desplazamiento : $desplazamiento;
        $fw = $anchoMm / max(1, $zona->ancho_max_mm);
        $fh = $altoMm / max(1, $zona->alto_max_mm);
        $fy = ($desdeCuelloMm - $zona->desde_cuello_mm) / max(1, $zona->alto_max_mm);
        $proporcion = max(1, $anchoMm) / max(1, $altoMm);
        $aspectoFoto = $aspectoFoto > 0 ? $aspectoFoto : 5 / 6;
        // FotoZona en centésimas de porcentaje de la foto; aquí, en % de su ancho (width, left) o de su alto (height, top).
        if ($fw >= $fh) {
            $width = $fw * $rect->ancho / 100;
            $height = $width * $aspectoFoto / $proporcion;
        } else {
            $height = $fh * $rect->alto / 100;
            $width = $height / $aspectoFoto * $proporcion;
        }
        $centro = ($rect->x + $rect->ancho * (0.5 + $enFoto / max(1, $zona->ancho_max_mm))) / 100;
        $redondeo = fn (float $v) => round($v, 2);

        return [
            'left' => $redondeo($centro - $width / 2),
            'top' => $redondeo(($rect->y + $fy * $rect->alto) / 100),
            'width' => $redondeo($width),
            'height' => $redondeo($height),
        ];
    }

    /** @return array{0: ?int, 1: ?int} la prenda y el color que se ven en la maqueta */
    private static function prendaYColor(LineaCarrito $linea): array
    {
        if ($linea->tipo === TipoLinea::Prenda) {
            return [$linea->prenda_id, $linea->color_id];
        }
        $primera = $linea->pack?->lineas()->orderBy('id')->first();
        if ($primera === null) {
            return [null, null];
        }
        $color = $linea->elecciones[$primera->id]['color'] ?? ($primera->colores[0] ?? null);

        return [$primera->prenda_id, $color === null ? null : (int) $color];
    }
}
