<?php

namespace App\Dominio\Sistema\Instalacion;

use App\Dominio\Sistema\Acceso\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Acceso temporal de la primera instalación (D208). Mientras no hay ningún superadministrador,
 * el portal genera una clave y la guarda en `storage/app/private/acceso-temporal.txt`, en el
 * servidor (nunca en el repositorio). Con ella se crea el primer superadministrador en /instalar
 * y, en cuanto existe, la clave y su archivo se borran. No hace falta entrar al contenedor.
 */
final class AccesoTemporal
{
    public const ARCHIVO = 'acceso-temporal.txt';

    /** No hay ningún superadministrador: la instalación está sin terminar. */
    public static function pendiente(): bool
    {
        return ! User::role(Rol::Superadministrador->value)->exists();
    }

    /** Ruta completa del archivo en el servidor, para decir dónde buscarlo. */
    public static function ruta(): string
    {
        return Storage::disk('local')->path(self::ARCHIVO);
    }

    /** Crea la clave y su archivo si aún no existen. */
    public static function preparar(): void
    {
        if (self::clave() !== null) {
            return;
        }

        $clave = Str::random(32);
        Storage::disk('local')->put(self::ARCHIVO, implode(PHP_EOL, [
            'Acceso temporal de Nextgenapparel (primera instalación)',
            '',
            'Abre '.url('/instalar').' y escribe esta clave:',
            '',
            'CLAVE: '.$clave,
            '',
            'Allí creas tu usuario del backend. Al crearlo, esta clave y este archivo se borran solos.',
            '',
        ]));
    }

    public static function comprobar(string $clave): bool
    {
        $guardada = self::clave();

        return $guardada !== null && $clave !== '' && hash_equals($guardada, trim($clave));
    }

    /** Borra la clave: ya hay superadministrador. */
    public static function cerrar(): void
    {
        Storage::disk('local')->delete(self::ARCHIVO);
    }

    private static function clave(): ?string
    {
        $contenido = Storage::disk('local')->get(self::ARCHIVO);
        if ($contenido === null || preg_match('/^CLAVE: (\S{32})$/m', $contenido, $coincide) !== 1) {
            return null;
        }

        return $coincide[1];
    }
}
