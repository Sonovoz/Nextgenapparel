<?php

namespace App\Dominio\Clientes;

use App\Models\ZonaEnvio;
use Locale;
use Throwable;

/**
 * Países en los que se aceptan direcciones (paso 5.3): los de las zonas de envío
 * activas del backend (Ajustes → Envío, regla 4). Sin zonas, España.
 */
final class Paises
{
    /** @return array<string, string> código ISO → nombre en el idioma actual */
    public static function disponibles(): array
    {
        $codigos = rescue(fn () => ZonaEnvio::where('activa', true)->pluck('paises')->flatten()->filter()->unique()->values()->all(), [], report: false);
        $codigos = $codigos ?: ['ES'];

        return collect($codigos)
            ->mapWithKeys(fn (string $c) => [strtoupper($c) => self::nombre($c)])
            ->sort()->all();
    }

    public static function nombre(string $codigo): string
    {
        try {
            return Locale::getDisplayRegion('-'.strtoupper($codigo), app()->getLocale()) ?: strtoupper($codigo);
        } catch (Throwable) {
            return strtoupper($codigo);
        }
    }

    /** Formato del código postal de cada país; los demás, un formato genérico. */
    public static function patronCodigoPostal(string $pais): string
    {
        return match (strtoupper($pais)) {
            'ES' => '/^(0[1-9]|[1-4]\d|5[0-2])\d{3}$/',
            'PT' => '/^\d{4}-\d{3}$/',
            'FR', 'DE', 'IT' => '/^\d{5}$/',
            'AD' => '/^AD\d{3}$/i',
            default => '/^[A-Za-z0-9][A-Za-z0-9 \-]{1,9}$/',
        };
    }
}
