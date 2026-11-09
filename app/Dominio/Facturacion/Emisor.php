<?php

namespace App\Dominio\Facturacion;

use App\Models\Ajuste;

/**
 * Los datos fiscales de la tienda, de Ajustes → Facturación (nunca en el código). Mientras
 * falte alguno no se factura: el pedido queda con la factura pendiente (D175).
 */
final class Emisor
{
    public const OBLIGATORIOS = ['razon_social' => 'razón social', 'nif' => 'NIF', 'direccion' => 'dirección', 'codigo_postal' => 'código postal', 'localidad' => 'localidad'];

    /** @return array<string, ?string> */
    public static function datos(): array
    {
        $guardado = (array) Ajuste::obtener('facturacion.emisor');

        return collect(['razon_social', 'nif', 'direccion', 'codigo_postal', 'localidad', 'provincia', 'pais', 'correo'])
            ->mapWithKeys(fn ($c) => [$c => blank($guardado[$c] ?? null) ? ($c === 'pais' ? 'ES' : null) : trim((string) $guardado[$c])])->all();
    }

    /** @return list<string> lo que falta, en palabras */
    public static function falta(): array
    {
        $datos = self::datos();

        return array_values(array_filter(self::OBLIGATORIOS, fn ($etiqueta, $campo) => blank($datos[$campo]), ARRAY_FILTER_USE_BOTH));
    }

    public static function serie(TipoFactura $tipo): string
    {
        $series = (array) Ajuste::obtener('facturacion.series');

        return (string) ($series[$tipo->clave()] ?? ['completa' => 'F', 'simplificada' => 'T', 'rectificativa' => 'R'][$tipo->clave()]);
    }
}
