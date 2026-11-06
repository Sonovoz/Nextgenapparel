<?php

namespace App\Dominio\Sistema\Salud;

use Carbon\CarbonImmutable;

/**
 * Marca de «sigo vivo» de un proceso de fondo (tareas programadas, colas).
 *
 * Se guarda en un archivo de storage/framework/latidos, no en la base de
 * datos ni en la caché, para poder comprobarlo aunque estas fallen.
 */
class Latido
{
    public const TAREAS = 'tareas-programadas';

    public const COLAS = 'colas';

    public static function registrar(string $proceso): void
    {
        $carpeta = self::carpeta();

        if (! is_dir($carpeta)) {
            @mkdir($carpeta, 0775, true);
        }

        file_put_contents(self::archivo($proceso), (string) now()->getTimestamp(), LOCK_EX);
    }

    public static function ultimo(string $proceso): ?CarbonImmutable
    {
        $archivo = self::archivo($proceso);

        if (! is_file($archivo)) {
            return null;
        }

        $marca = (int) trim((string) @file_get_contents($archivo));

        return $marca > 0 ? CarbonImmutable::createFromTimestamp($marca, config('app.timezone')) : null;
    }

    private static function carpeta(): string
    {
        return storage_path('framework/latidos');
    }

    private static function archivo(string $proceso): string
    {
        return self::carpeta().DIRECTORY_SEPARATOR.basename($proceso).'.txt';
    }
}
