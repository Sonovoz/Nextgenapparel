<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Salud\Resultado;

/**
 * Comprueba que el servidor de correo (SMTP) acepta conexiones. No envía
 * ningún correo ni comprueba la contraseña.
 */
class Correo implements Comprobacion
{
    public function clave(): string
    {
        return 'correo';
    }

    public function nombre(): string
    {
        return 'Correo (SMTP)';
    }

    public function comprobar(): array
    {
        $mailer = config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            return Resultado::correcto('Los correos se escriben en el log (no se envían). Normal en local.');
        }

        if ($mailer !== 'smtp') {
            return Resultado::correcto("Envío configurado con «{$mailer}».");
        }

        $host = (string) config('mail.mailers.smtp.host');
        $puerto = (int) config('mail.mailers.smtp.port');
        $conexion = @fsockopen($host, $puerto, $codigo, $error, 5);

        if ($conexion === false) {
            return Resultado::fallo("No se puede conectar con {$host}:{$puerto} ({$error}).");
        }

        fclose($conexion);

        $alertas = filled(config('errores.correo_alertas')) ? '' : ' Falta ALERTAS_EMAIL: no se enviarán avisos.';

        return filled($alertas)
            ? Resultado::aviso("El servidor {$host}:{$puerto} responde.".$alertas)
            : Resultado::correcto("El servidor {$host}:{$puerto} responde.");
    }
}
