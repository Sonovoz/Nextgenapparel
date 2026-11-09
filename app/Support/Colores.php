<?php

namespace App\Support;

/** El mismo criterio que el visor (public/js/visor.js: esOscuro) para la tinta automática. */
final class Colores
{
    public static function oscuro(?string $hex): bool
    {
        if (! preg_match('/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', (string) $hex, $m)) {
            return false;
        }

        return (0.299 * hexdec($m[1]) + 0.587 * hexdec($m[2]) + 0.114 * hexdec($m[3])) / 255 < 0.45;
    }
}
