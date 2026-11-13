<?php

namespace App\Dominio\Precios\Motor;

use App\Dominio\Precios\MetodoEnvio;
use App\Models\Caja;
use App\Models\ZonaEnvio;

/**
 * Paso 7 del motor de precios (envío), aparte para poder probarlo desde el backend (D225):
 * con un destino, un peso, unas unidades y el importe de la mercancía, da el envío o el motivo
 * exacto por el que no se puede enviar. El motor lo usa tal cual; al cliente solo le dice
 * «Todavía no enviamos a ese destino».
 */
final class CalculoEnvio
{
    public const SIN_ZONA = 'sin_zona';

    public const SIN_TARIFAS = 'sin_tarifas';

    public const PESO_FUERA = 'peso_fuera';

    public const SIN_CAJA = 'sin_caja';

    public const SIN_PRECIO_CAJA = 'sin_precio_caja';

    /**
     * @return array{envio: ?array{zona: string, metodo: string, importe: int, plazo_dias: int, peso_gramos: int, bultos: ?int}, motivo: ?string, zona: ?ZonaEnvio, gratis: ?bool, faltan_gratis: ?int}
     */
    public static function para(Destino $destino, int $peso, int $unidades, int $mercancia): array
    {
        $zona = ZonaEnvio::paraDestino($destino->pais, $destino->codigoPostal)?->load('tarifas');
        $fallo = fn (string $motivo) => ['envio' => null, 'motivo' => $motivo, 'zona' => $zona, 'gratis' => null, 'faltan_gratis' => null];
        if ($zona === null) {
            return $fallo(self::SIN_ZONA);
        }

        $bultos = null;
        if ($zona->metodo === MetodoEnvio::PorKilo) {
            $tarifa = $zona->tarifas->first(fn ($t) => $t->hasta_gramos >= $peso);
            $ultima = $zona->tarifas->last();
            if ($tarifa !== null) {
                $importe = $tarifa->precio;
            } elseif ($ultima !== null && $zona->precio_kg_extra !== null) {
                $importe = $ultima->precio + (int) ceil(($peso - $ultima->hasta_gramos) / 1000) * $zona->precio_kg_extra;
            } else {
                return $fallo($ultima === null ? self::SIN_TARIFAS : self::PESO_FUERA);
            }
        } else {
            $caja = Caja::where('activa', true)->orderByDesc('capacidad_prendas')->first();
            if ($caja === null) {
                return $fallo(self::SIN_CAJA);
            }
            if ($zona->precio_caja === null) {
                return $fallo(self::SIN_PRECIO_CAJA);
            }
            $bultos = max((int) ceil($unidades / $caja->capacidad_prendas), (int) ceil($peso / $caja->peso_max_gramos), 1);
            $importe = $bultos * $zona->precio_caja;
        }

        $gratis = null;
        $faltan = null;
        if ($zona->gratis_desde !== null && $mercancia >= $zona->gratis_desde) {
            $importe = 0;
            $gratis = true;
        } elseif ($zona->gratis_desde !== null) {
            $gratis = false;
            $faltan = $zona->gratis_desde - $mercancia;
        }

        return [
            'envio' => ['zona' => $zona->nombre, 'metodo' => $zona->metodo->value, 'importe' => $importe, 'plazo_dias' => $zona->plazo_dias, 'peso_gramos' => $peso, 'bultos' => $bultos],
            'motivo' => null, 'zona' => $zona, 'gratis' => $gratis, 'faltan_gratis' => $faltan,
        ];
    }

    /**
     * Lo que le falta a una zona para poder cobrar envíos, para el backend.
     *
     * @return list<string>
     */
    public static function problemas(ZonaEnvio $zona): array
    {
        $problemas = [];
        if (($zona->paises ?? []) === []) {
            $problemas[] = 'No tiene países.';
        }
        if ($zona->metodo === MetodoEnvio::PorKilo) {
            $ultima = $zona->tarifas()->orderByDesc('hasta_gramos')->first();
            if ($ultima === null) {
                $problemas[] = 'Faltan las tarifas por peso.';
            } elseif ($zona->precio_kg_extra === null) {
                $problemas[] = 'Los pedidos de más de '.self::kilos($ultima->hasta_gramos).' no se pueden enviar: añade un tramo mayor o el precio «por cada kilo más».';
            }
        } else {
            if (! Caja::where('activa', true)->exists()) {
                $problemas[] = 'No hay ninguna caja activa (Ajustes → Cajas).';
            }
            if ($zona->precio_caja === null) {
                $problemas[] = 'Falta el precio por caja.';
            }
        }

        return $problemas;
    }

    public static function texto(string $motivo, ?ZonaEnvio $zona = null): string
    {
        return match ($motivo) {
            self::SIN_ZONA => 'Ninguna zona activa cubre ese país y código postal.',
            self::SIN_TARIFAS => "La zona «{$zona?->nombre}» no tiene tarifas por peso.",
            self::PESO_FUERA => "El pedido pesa más que el último tramo de «{$zona?->nombre}» y no hay precio por cada kilo más.",
            self::SIN_CAJA => 'No hay ninguna caja activa (Ajustes → Cajas).',
            self::SIN_PRECIO_CAJA => "La zona «{$zona?->nombre}» no tiene precio por caja.",
            default => $motivo,
        };
    }

    private static function kilos(int $gramos): string
    {
        return rtrim(rtrim(number_format($gramos / 1000, 2, ',', '.'), '0'), ',').' kg';
    }
}
