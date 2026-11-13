<?php

namespace App\Dominio\Carrito;

use App\Http\Controllers\DisenoController;
use App\Models\Carrito;
use App\Models\ImpresionCarrito;
use App\Models\LineaCarrito;

/**
 * Los diseños de una línea del carrito para llevarlos a otra prenda (D213): en qué zona iban (clave y cara)
 * y los datos del diseño ya subido (no se sube otra vez). Solo líneas del carrito de quien compra.
 */
final class DisenosDeLinea
{
    /** @return list<array<string, mixed>> */
    public static function de(?Carrito $carrito, ?string $uuidLinea): array
    {
        if ($carrito === null || blank($uuidLinea)) {
            return [];
        }
        $linea = $carrito->lineas()->where('uuid', $uuidLinea)->with(['impresiones.zona', 'impresiones.diseno'])->first();

        return $linea === null ? [] : self::deLinea($linea);
    }

    /** @return list<array<string, mixed>> */
    public static function deLinea(LineaCarrito $linea): array
    {
        return $linea->impresiones
            ->filter(fn (ImpresionCarrito $i) => $i->diseno !== null)
            ->map(fn (ImpresionCarrito $i) => DisenoController::datos($i->diseno) + [
                'clave' => $i->zona?->clave,
                'cara' => $i->zona?->cara?->value,
            ])->values()->all();
    }

    /** La última línea de prenda con diseños: la que se ofrece llevar a otras prendas desde el carrito. */
    public static function ultimaConDisenos(?Carrito $carrito): ?LineaCarrito
    {
        return $carrito?->lineas()->where('tipo', TipoLinea::Prenda->value)->whereHas('impresiones', fn ($q) => $q->whereNotNull('diseno_id'))
            ->with(['impresiones.zona', 'impresiones.diseno'])->reorder()->latest('id')->first();
    }
}
