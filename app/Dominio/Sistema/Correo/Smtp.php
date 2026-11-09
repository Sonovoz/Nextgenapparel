<?php

namespace App\Dominio\Sistema\Correo;

use App\Models\Ajuste;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Correo de salida por SMTP (paso 6.4), portado de Clave de Alma (ADR 0029): los datos se
 * escriben en Ajustes → Correos (la contraseña cifrada con la APP_KEY, nunca se vuelve a
 * mostrar) y mandan sobre el .env; lo que el panel no tiene sale del .env (MAIL_*).
 */
final class Smtp
{
    public const CLAVE = 'correo.smtp';

    /** @return array<string, mixed> */
    public static function guardado(): array
    {
        return (array) Ajuste::obtener(self::CLAVE);
    }

    /** Pasa lo del panel a config('mail.*') antes de crear el correo. */
    public static function aplicar(): void
    {
        $s = self::guardado();
        if ($s === []) {
            return;
        }
        $mapa = ['servidor' => 'mail.mailers.smtp.host', 'puerto' => 'mail.mailers.smtp.port', 'usuario' => 'mail.mailers.smtp.username',
            'remitente' => 'mail.from.address', 'nombre_remitente' => 'mail.from.name'];
        foreach ($mapa as $clave => $ruta) {
            if (filled($s[$clave] ?? null)) {
                config([$ruta => $clave === 'puerto' ? (int) $s[$clave] : (string) $s[$clave]]);
            }
        }
        if (filled($s['cifrado'] ?? null)) {
            // SSL/TLS (465) → smtps; STARTTLS (587) → smtp, que pasa a cifrado solo.
            config(['mail.mailers.smtp.scheme' => $s['cifrado'] === 'ssl' ? 'smtps' : 'smtp']);
        }
        if (($contrasena = self::contrasena()) !== null) {
            config(['mail.mailers.smtp.password' => $contrasena]);
        }
    }

    /**
     * Guarda el formulario; la contraseña solo si se escribe una nueva.
     *
     * @param  array<string, mixed>  $datos
     * @return list<string> lo que ha cambiado (nombres, nunca valores)
     */
    public static function guardar(array $datos): array
    {
        $antes = self::guardado();
        $despues = $antes;
        foreach (['servidor', 'puerto', 'cifrado', 'usuario', 'remitente', 'nombre_remitente'] as $clave) {
            if (array_key_exists($clave, $datos)) {
                $despues[$clave] = trim((string) $datos[$clave]);
            }
        }
        if (filled($datos['contrasena'] ?? null)) {
            $despues['contrasena'] = Crypt::encryptString((string) $datos['contrasena']);
        }
        $cambios = [];
        foreach (['servidor', 'puerto', 'cifrado', 'usuario', 'contrasena', 'remitente', 'nombre_remitente'] as $clave) {
            $era = $clave === 'contrasena' ? self::descifrar($antes[$clave] ?? null) : ($antes[$clave] ?? null);
            $es = $clave === 'contrasena' ? self::descifrar($despues[$clave] ?? null) : ($despues[$clave] ?? null);
            if ($era !== $es) {
                $cambios[] = str_replace('_', ' ', $clave);
            }
        }
        Ajuste::guardar(self::CLAVE, $despues);
        self::aplicar();
        if (! Mail::isFake()) {
            Mail::purge('smtp'); // el correo ya creado en esta petición se rehace con los datos nuevos
        }

        return $cambios;
    }

    public static function contrasena(): ?string
    {
        return self::descifrar(self::guardado()['contrasena'] ?? null);
    }

    private static function descifrar(mixed $valor): ?string
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }
        try {
            return Crypt::decryptString($valor);
        } catch (DecryptException) {
            Log::error('No se puede descifrar la contraseña del correo guardada en el panel (¿ha cambiado la APP_KEY?).');

            return null;
        }
    }
}
