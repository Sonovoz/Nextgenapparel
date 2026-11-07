<?php

namespace App\Dominio\Precios;

use App\Models\Ajuste;
use App\Models\ClaseImpuesto;

/**
 * Tipos de IVA y recargo de equivalencia de una clase de impuesto (Ajustes →
 * Impuestos). Sin clase, la clase por defecto; si aún no hay ninguna, el IVA
 * general provisional de Ajustes (D75).
 */
final class Impuestos
{
    /** @return array{0: int, 1: int}  [tipo de IVA, recargo] en centésimas de % */
    public static function tipos(?int $claseId): array
    {
        $clase = rescue(fn () => ($claseId !== null ? ClaseImpuesto::find($claseId) : null) ?? ClaseImpuesto::porDefecto(), null, report: false);

        return $clase !== null
            ? [$clase->tipo_iva, $clase->recargo]
            : [(int) Ajuste::obtener('impuestos.iva_general'), 0];
    }

    public static function tipoIva(?int $claseId): int
    {
        return self::tipos($claseId)[0];
    }
}
