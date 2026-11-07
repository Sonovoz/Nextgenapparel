<?php

namespace App\Dominio\Catalogo;

use App\Models\Color;
use App\Models\Prenda;
use App\Models\Talla;
use App\Models\Variante;
use Illuminate\Support\Str;

/**
 * SKU automático de una variación (D76): FAMILIA-PRENDA-COLOR-TALLA,
 * p. ej. CAM-ESSENTIAL-NEGRO-M. Si ya existe, se añade -2, -3…
 */
class Sku
{
    public const LONGITUD_MAXIMA = 64;

    public static function para(Prenda $prenda, Color $color, Talla $talla, ?int $ignorarVarianteId = null): string
    {
        $familia = Str::upper(Str::substr(Str::slug((string) $prenda->familia->getTranslation('slug', 'es')), 0, 3));
        $prefijoFamilia = Str::lower(Str::substr($familia, 0, 3));

        // Nombre de la prenda sin la palabra de la familia («camiseta essential» → «ESSENTIAL»).
        $palabras = collect(explode('-', Str::slug((string) $prenda->getTranslation('nombre', 'es'))))
            ->reject(fn (string $palabra) => $prefijoFamilia !== '' && str_starts_with($palabra, $prefijoFamilia))
            ->whenEmpty(fn ($c) => $c->push('P'.$prenda->id));

        $partes = [
            $familia,
            Str::upper($palabras->join('-')),
            Str::upper(Str::slug((string) $color->getTranslation('nombre', 'es'))),
            Str::upper(Str::slug($talla->nombre)),
        ];

        $base = Str::limit(implode('-', array_filter($partes)), self::LONGITUD_MAXIMA - 4, '');
        $sku = $base;
        $numero = 2;

        while (Variante::where('sku', $sku)->when($ignorarVarianteId, fn ($q) => $q->whereKeyNot($ignorarVarianteId))->exists()) {
            $sku = $base.'-'.$numero++;
        }

        return $sku;
    }
}
