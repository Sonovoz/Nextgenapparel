<?php

namespace App\Dominio\Facturacion;

/**
 * Registro de facturación Veri*Factu (RD 1007/2023 y Orden HAC/1177/2024), portado de
 * Clave de Alma: huella SHA-256 encadenada con el registro anterior y URL del QR de cotejo.
 * El formato se valida contra los ejemplos de la AEAT; antes de activarlo, contra su entorno de pruebas.
 */
final class Verifactu
{
    public const QR_PRODUCCION = 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR';

    public const QR_PRUEBAS = 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR';

    /** Importe como en el XML de la AEAT: punto decimal y dos decimales. */
    public static function importe(int $centimos): string
    {
        return ($centimos < 0 ? '-' : '').intdiv(abs($centimos), 100).'.'.str_pad((string) (abs($centimos) % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  string  $fecha  dd-mm-aaaa
     * @param  string  $generadoEn  fecha y hora con huso (ISO 8601)
     */
    public static function huella(string $nifEmisor, string $numero, string $fecha, string $tipo, int $cuota, int $total, ?string $anterior, string $generadoEn): string
    {
        $cadena = 'IDEmisorFactura='.trim($nifEmisor)
            .'&NumSerieFactura='.trim($numero)
            .'&FechaExpedicionFactura='.$fecha
            .'&TipoFactura='.$tipo
            .'&CuotaTotal='.self::importe($cuota)
            .'&ImporteTotal='.self::importe($total)
            .'&Huella='.($anterior ?? '')
            .'&FechaHoraHusoGenRegistro='.$generadoEn;

        return strtoupper(hash('sha256', $cadena));
    }

    public static function qr(bool $produccion, string $nifEmisor, string $numero, string $fecha, int $total): string
    {
        return ($produccion ? self::QR_PRODUCCION : self::QR_PRUEBAS).'?'.http_build_query([
            'nif' => trim($nifEmisor), 'numserie' => trim($numero), 'fecha' => $fecha, 'importe' => self::importe($total),
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
