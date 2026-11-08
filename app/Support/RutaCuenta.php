<?php

namespace App\Support;

/**
 * URL de la cuenta del cliente en su idioma (/es/cuenta/entrar, /en/account/login).
 * Cada idioma tiene sus propias rutas, con nombre «cuenta.{idioma}.{accion}».
 */
final class RutaCuenta
{
    public static function url(string $accion, array $parametros = [], ?string $idioma = null, bool $absoluta = true): string
    {
        $idioma ??= app()->getLocale();

        return route("cuenta.{$idioma}.{$accion}", $parametros, $absoluta);
    }

    /** La misma página en cada idioma (selector de idioma y hreflang). */
    public static function alternos(string $accion, array $parametros = []): array
    {
        return collect(config('idiomas.disponibles'))->keys()
            ->mapWithKeys(fn (string $i) => [$i => self::url($accion, $parametros, $i)])->all();
    }
}
