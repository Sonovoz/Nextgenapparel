<?php

namespace App\Dominio\Catalogo\Proveedores;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * D253: cliente HTTP de la API v3 de TopTex. Cada llamada lleva la API key (cabecera x-api-key) y un token de sesión
 * (x-toptex-authorization) que se pide con usuario y contraseña en /v3/authenticate, dura una hora y se guarda en
 * caché hasta cinco minutos antes de caducar. Si TopTex lo rechaza antes, se pide otro una vez.
 */
class ClienteTopTex
{
    private const CACHE = 'toptex.token';

    public static function configurado(): bool
    {
        return filled(config('toptex.api_key')) && filled(config('toptex.usuario')) && filled(config('toptex.clave'));
    }

    /** Comprueba que las credenciales valen. Devuelve el usuario con el que entra. */
    public function probar(): string
    {
        Cache::forget(self::CACHE);

        return (string) ($this->autenticar()['username'] ?? config('toptex.usuario'));
    }

    /**
     * GET a la API (ruta como «/v3/products»). Devuelve el JSON decodificado.
     *
     * @param  array<string, scalar>  $consulta
     * @return array<mixed>
     */
    public function get(string $ruta, array $consulta = []): array
    {
        $respuesta = $this->llamar($ruta, $consulta);
        if ($respuesta->status() === 401 || $respuesta->status() === 403) {
            Cache::forget(self::CACHE); // token caducado o revocado: se pide otro una vez
            $respuesta = $this->llamar($ruta, $consulta);
        }
        if ($respuesta->status() === 404) {
            return [];
        }
        if (! $respuesta->successful()) {
            throw new ProveedorNoDisponible('TopTex ha respondido con un error ('.$respuesta->status().') en '.$ruta.'.');
        }
        $datos = $respuesta->json();

        return is_array($datos) ? $datos : [];
    }

    /**
     * Precio de compra de cada SKU de una referencia: el del tramo de 1 unidad, en céntimos sin IVA.
     *
     * @return array<string, int> sku => céntimos
     */
    public function costes(string $referencia): array
    {
        $costes = [];
        foreach ($this->lista($this->get('/v3/products/price', ['catalog_reference' => $referencia])) as $fila) {
            if (! is_array($fila) || ! isset($fila['sku'])) {
                continue;
            }
            $tramos = collect((array) ($fila['prices'] ?? []))->filter(fn ($t) => is_array($t) && isset($t['price']))
                ->sortBy(fn ($t) => (int) ($t['quantity'] ?? 0));
            $primero = $tramos->first();
            if ($primero !== null) {
                $costes[(string) $fila['sku']] = (int) round((float) $primero['price'] * 100);
            }
        }

        return $costes;
    }

    /** @return list<mixed> la respuesta como lista (TopTex devuelve un objeto si hay uno solo) */
    public function lista(array $datos): array
    {
        if ($datos === []) {
            return [];
        }
        foreach (['items', 'data', 'products', 'results'] as $clave) {
            if (isset($datos[$clave]) && is_array($datos[$clave])) {
                return array_values($datos[$clave]);
            }
        }

        return array_is_list($datos) ? $datos : [$datos];
    }

    /** @param  array<string, scalar>  $consulta */
    private function llamar(string $ruta, array $consulta): Response
    {
        $token = $this->token();
        try {
            return Http::acceptJson()->timeout((int) config('toptex.timeout', 30))
                ->withHeaders(['x-api-key' => (string) config('toptex.api_key'), 'x-toptex-authorization' => $token])
                ->get(rtrim((string) config('toptex.url'), '/').$ruta, $consulta);
        } catch (ConnectionException $e) {
            throw new ProveedorNoDisponible('TopTex no responde: '.$e->getMessage());
        }
    }

    private function token(): string
    {
        $guardado = Cache::get(self::CACHE);
        if (is_string($guardado) && $guardado !== '') {
            return $guardado;
        }
        $datos = $this->autenticar();
        $token = (string) $datos['token'];
        $caduca = isset($datos['expiry_time']) ? rescue(fn () => CarbonImmutable::parse((string) $datos['expiry_time']), null, report: false) : null;
        $segundos = $caduca !== null ? max(60, $caduca->getTimestamp() - now()->getTimestamp() - 300) : 3300;
        Cache::put(self::CACHE, $token, $segundos);

        return $token;
    }

    /** @return array<string, mixed> */
    private function autenticar(): array
    {
        if (! self::configurado()) {
            throw new ProveedorNoDisponible('Faltan las credenciales de TopTex en el .env (TOPTEX_API_KEY, TOPTEX_USUARIO y TOPTEX_CLAVE).');
        }
        try {
            $respuesta = Http::acceptJson()->timeout((int) config('toptex.timeout', 30))
                ->withHeaders(['x-api-key' => (string) config('toptex.api_key')])
                ->post(rtrim((string) config('toptex.url'), '/').'/v3/authenticate', [
                    'username' => (string) config('toptex.usuario'),
                    'password' => (string) config('toptex.clave'),
                ]);
        } catch (ConnectionException $e) {
            throw new ProveedorNoDisponible('TopTex no responde: '.$e->getMessage());
        }
        if (! $respuesta->successful() || ! is_string($respuesta->json('token')) || $respuesta->json('token') === '') {
            throw new ProveedorNoDisponible(match (true) {
                in_array($respuesta->status(), [401, 403], true) => 'TopTex rechaza la API key, el usuario o la contraseña (o falta suscribirse a la API en su portal).',
                default => 'TopTex no ha dado el token ('.$respuesta->status().').',
            });
        }

        return (array) $respuesta->json();
    }
}
