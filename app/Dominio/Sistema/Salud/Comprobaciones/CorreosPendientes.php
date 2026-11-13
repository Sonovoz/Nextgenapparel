<?php

namespace App\Dominio\Sistema\Salud\Comprobaciones;

use App\Dominio\Sistema\Salud\Resultado;
use App\Models\RegistroCorreo;
use Throwable;

/**
 * D235: correos que no salen. En cola más de unos minutos: la cola no se procesa (cron del servidor).
 * Fallidos en las últimas 24 h: el SMTP los rechaza (el error está en Herramientas → Registro de correos).
 */
class CorreosPendientes implements Comprobacion
{
    public const MINUTOS_EN_COLA = 10;

    public function clave(): string
    {
        return 'correos-pendientes';
    }

    public function nombre(): string
    {
        return 'Correos enviados';
    }

    public function comprobar(): array
    {
        try {
            $enCola = RegistroCorreo::whereIn('estado', ['en_cola', 'reintentando'])->where('updated_at', '<', now()->subMinutes(self::MINUTOS_EN_COLA))->count();
            $fallidos = RegistroCorreo::where('estado', 'fallido')->where('updated_at', '>=', now()->subDay())->latest('id')->get(['error']);
        } catch (Throwable) {
            return Resultado::aviso('No se ha podido leer el registro de correos.');
        }
        if ($enCola > 0) {
            return Resultado::fallo("{$enCola} correos esperan en la cola más de ".self::MINUTOS_EN_COLA.' minutos: el cron del servidor (schedule:run) no está procesando la cola.');
        }
        if ($fallidos->isNotEmpty()) {
            return Resultado::aviso($fallidos->count().' correos han fallado en las últimas 24 h. Último error: '.mb_substr((string) $fallidos->first()->error, 0, 200));
        }

        return Resultado::correcto('Sin correos atascados ni fallidos en las últimas 24 h.');
    }
}
