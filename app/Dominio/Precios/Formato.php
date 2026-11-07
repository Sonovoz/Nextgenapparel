<?php

namespace App\Dominio\Precios;

/** Importes para mostrar en la tienda según el idioma: «5,08 €» o «€5.08». */
class Formato
{
    public static function euros(int $centimos, ?string $idioma = null): string
    {
        $idioma ??= app()->getLocale();
        $euros = $centimos / 100;

        return $idioma === 'en'
            ? '€'.number_format($euros, 2, '.', ',')
            : number_format($euros, 2, ',', '.').' €';
    }
}
