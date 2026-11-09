<?php

namespace App\Dominio\Pagos;

use Illuminate\Support\Facades\URL;

/**
 * Pago simulado para probar sin TPV (D181): una página nuestra con «Pago correcto» y «Pago
 * rechazado», que vuelve firmada por Laravel. Nunca en producción (lo impide el proveedor).
 */
class PasarelaSimulada implements Pasarela
{
    public function nombre(): string
    {
        return 'simulada';
    }

    public function metodos(): array
    {
        return ['tarjeta' => 'Tarjeta', 'bizum' => 'Bizum'];
    }

    public function pruebas(): bool
    {
        return true;
    }

    public function iniciar(PeticionPago $peticion): PagoIniciado
    {
        $referencia = 'sim'.$peticion->pedidoId.'-'.bin2hex(random_bytes(4));

        return new PagoIniciado($referencia, URL::temporarySignedRoute('pagos.simulada', now()->addHour(),
            ['referencia' => $referencia, 'importe' => $peticion->importe, 'vuelta' => $peticion->urlVuelta]));
    }

    /** La vuelta trae `sim_referencia`, `sim_resultado` e `sim_importe` y una firma de Laravel (`sim_firma`). */
    public function evento(array $campos): ?EventoPasarela
    {
        if (! isset($campos['sim_referencia'])) {
            return null;
        }
        $datos = (string) $campos['sim_referencia'].'|'.(string) ($campos['sim_resultado'] ?? '').'|'.(string) ($campos['sim_importe'] ?? '');
        if (! hash_equals(self::firmar($datos), (string) ($campos['sim_firma'] ?? ''))) {
            throw new AvisoNoValido('Vuelta del pago simulado sin firma válida.');
        }
        $cobrado = $campos['sim_resultado'] === 'ok';

        return new EventoPasarela('simulada:'.$campos['sim_referencia'], $cobrado, (string) $campos['sim_referencia'], (int) $campos['sim_importe'], $cobrado ? null : 'rechazado');
    }

    public function devolver(string $referencia, int $importe, string $clave): ResultadoDevolucion
    {
        return new ResultadoDevolucion(true, 'sim-dev-'.substr(hash('sha256', $clave), 0, 12));
    }

    public static function firmar(string $datos): string
    {
        return hash_hmac('sha256', $datos, (string) config('app.key'));
    }
}
