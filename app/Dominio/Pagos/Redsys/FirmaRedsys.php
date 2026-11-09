<?php

namespace App\Dominio\Pagos\Redsys;

use App\Dominio\Pagos\AvisoNoValido;

/**
 * Firma HMAC_SHA256_V1 de Redsys, igual que su librería oficial: la clave del comercio se
 * diversifica por pedido con 3DES (CBC, IV a ceros) y se firma Ds_MerchantParameters con
 * HMAC-SHA256. Las respuestas de Redsys vienen en Base64 «URL-safe». Portada tal cual de Clave de Alma.
 */
final class FirmaRedsys
{
    public const VERSION = 'HMAC_SHA256_V1';

    public function __construct(private readonly string $secretKey) {}

    /**
     * Lo que se envía a Redsys: parámetros en Base64 y su firma.
     *
     * @param  array<string, string>  $params  Con DS_MERCHANT_ORDER.
     * @return array{Ds_SignatureVersion: string, Ds_MerchantParameters: string, Ds_Signature: string}
     */
    public function sign(array $params): array
    {
        $encoded = base64_encode((string) json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return [
            'Ds_SignatureVersion' => self::VERSION,
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => base64_encode($this->mac($encoded, $params['DS_MERCHANT_ORDER'])),
        ];
    }

    /**
     * Comprueba una respuesta de Redsys (aviso, vuelta del cliente o respuesta REST) y la
     * devuelve decodificada. Lanza AvisoNoValido si no es auténtica.
     *
     * @return array<string, string>
     */
    public function verify(string $version, string $encoded, string $signature): array
    {
        if ($version !== self::VERSION || $encoded === '' || $signature === '') {
            throw new AvisoNoValido('Respuesta de Redsys sin firma o con una versión desconocida.');
        }

        // Un «+» sin codificar en un formulario llega como espacio.
        $encoded = str_replace(' ', '+', $encoded);
        $params = json_decode((string) base64_decode(strtr($encoded, '-_', '+/'), true), true);
        if (! is_array($params)) {
            throw new AvisoNoValido('Respuesta de Redsys ilegible.');
        }
        $params = array_map(fn ($v): string => urldecode((string) $v), $params);
        $order = $params['Ds_Order'] ?? $params['DS_ORDER'] ?? '';
        if ($order === '') {
            throw new AvisoNoValido('Respuesta de Redsys sin número de pedido.');
        }

        $expected = self::urlSafe(base64_encode($this->mac($encoded, $order)));
        if (! hash_equals($expected, self::urlSafe($signature))) {
            throw new AvisoNoValido('La firma de Redsys no coincide.');
        }

        return $params;
    }

    private function mac(string $encoded, string $order): string
    {
        $key = base64_decode($this->secretKey, true);
        if ($key === false || $key === '') {
            throw new AvisoNoValido('Falta la clave secreta del TPV (REDSYS_SECRET_KEY).');
        }

        // 3DES del número de pedido con la clave del comercio, rellenado con ceros a bloques de 8.
        $length = (int) ceil(strlen($order) / 8) * 8;
        $diversified = substr((string) openssl_encrypt(str_pad($order, $length, "\0"), 'des-ede3-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, "\0\0\0\0\0\0\0\0"), 0, $length);

        return hash_hmac('sha256', $encoded, $diversified, true);
    }

    private static function urlSafe(string $base64): string
    {
        return rtrim(strtr($base64, '+/', '-_'), '=');
    }
}
