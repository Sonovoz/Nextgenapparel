<?php

namespace App\Dominio\Asistente;

use App\Models\Ajuste;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * D227: la clave de OpenAI se puede guardar en Ajustes → IA, cifrada con la APP_KEY (como la contraseña
 * del SMTP). Si también está en el .env, manda el .env. Nunca se vuelve a enseñar ni se anota en el registro.
 */
final class ClaveIa
{
    public const AJUSTE = 'ia.clave_cifrada';

    public static function actual(): ?string
    {
        $entorno = trim((string) config('ia.openai_key'));
        if ($entorno !== '') {
            return $entorno;
        }
        $cifrada = Ajuste::obtener(self::AJUSTE);
        if (! is_string($cifrada) || $cifrada === '') {
            return null;
        }
        try {
            return Crypt::decryptString($cifrada);
        } catch (DecryptException) {
            Log::error('No se puede descifrar la clave de IA guardada en el panel (¿ha cambiado la APP_KEY?).');

            return null;
        }
    }

    public static function configurada(): bool
    {
        return self::actual() !== null;
    }

    /** «env», «panel» o null. */
    public static function origen(): ?string
    {
        if (trim((string) config('ia.openai_key')) !== '') {
            return 'env';
        }

        return self::actual() !== null ? 'panel' : null;
    }

    public static function guardar(?string $clave): void
    {
        $clave = trim((string) $clave);
        Ajuste::guardar(self::AJUSTE, $clave === '' ? null : Crypt::encryptString($clave));
    }
}
