<?php

namespace App\Filament\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda en un campo traducible (JSON {"es": …, "en": …}) del backend (revisión N3 del 5.4, M4):
 * dentro del texto de cada idioma (no en el JSON entero: «es» casaría con todo) y sin distinguir
 * mayúsculas, también en MariaDB, donde las columnas JSON comparan en binario.
 */
final class BusquedaTraducible
{
    public static function en(string $columna): Closure
    {
        return function (Builder $query, string $search) use ($columna): Builder {
            $termino = '%'.mb_strtolower(trim($search)).'%';
            $driver = $query->getConnection()->getDriverName();

            return $query->where(function (Builder $q) use ($columna, $termino, $driver): void {
                foreach (array_keys(config('idiomas.disponibles')) as $idioma) {
                    $texto = $driver === 'sqlite'
                        ? "lower(json_extract({$columna}, '$.{$idioma}'))"
                        : "lower(json_unquote(json_extract({$columna}, '$.{$idioma}')))";
                    $q->orWhereRaw("{$texto} like ?", [$termino]);
                }
            });
        };
    }
}
