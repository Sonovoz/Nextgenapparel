<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Ajustes configurables desde el backend (regla 4). Se guardan en la base de
 * datos y se leen con caché; si la base de datos no responde, se usa el valor
 * por defecto para que la tienda siga funcionando.
 */
class Ajuste extends Model
{
    /** Valores por defecto mientras no se cambien en el backend. */
    public const POR_DEFECTO = [
        'tienda.nombre' => 'NextGen Apparel',
        'tienda.correo_contacto' => null,
        // IVA general provisional hasta la fase 3, en centésimas de % (2100 = 21 %) (D75).
        'impuestos.iva_general' => 2100,
        // Calidad de los diseños: ppp efectivo mínimos de cada nivel (ficha § 7).
        'disenos.ppp_correcto' => 300,
        'disenos.ppp_aceptable' => 200,
        'disenos.ppp_minimo' => 150,
        // Buscadores: apagado hasta la puesta en marcha; solo cuenta en producción (D102).
        'seo.indexar' => false,
    ];

    protected $table = 'ajustes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['valor' => 'json'];
    }

    public static function obtener(string $clave): mixed
    {
        try {
            $valores = Cache::rememberForever('ajustes', fn () => static::pluck('valor', 'clave')->all());
        } catch (Throwable) {
            $valores = [];
        }

        return array_key_exists($clave, $valores) ? $valores[$clave] : (self::POR_DEFECTO[$clave] ?? null);
    }

    public static function guardar(string $clave, mixed $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        Cache::forget('ajustes');
    }
}
