<?php

namespace App\Dominio\Clientes;

use App\Models\ZonaEnvio;

/**
 * Reglas de una dirección, las mismas en el área del cliente y en el backend
 * (revisión N3 del 5.4, M3): código postal con el formato del país y, si es de
 * envío, dentro de una zona de envío activa (misma regla que el motor de precios).
 */
final class ReglasDireccion
{
    public const TELEFONO = '/^[+0-9 ()\-]{6,30}$/';

    /** Mensaje de error del código postal, o null si vale. */
    public static function errorCodigoPostal(string $pais, string $codigoPostal, string $tipo): ?string
    {
        $codigoPostal = strtoupper(trim($codigoPostal));
        if (! preg_match(Paises::patronCodigoPostal($pais), $codigoPostal)) {
            return __('cuenta.cp_no_valido', ['pais' => Paises::nombre($pais)]);
        }
        if ($tipo === TipoDireccion::Envio->value && ZonaEnvio::where('activa', true)->exists() && ZonaEnvio::paraDestino($pais, $codigoPostal) === null) {
            return __('cuenta.cp_sin_envio');
        }

        return null;
    }
}
