<?php

namespace App\Dominio\Produccion;

use App\Dominio\Disenos\AnalizadorPng;
use App\Models\ImpresionPedido;
use App\Models\Pedido;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickException;

/**
 * D238: el diseño de cada zona tal como se imprime. Se recorta a la parte con dibujo (lo mismo que se colocó en la
 * prenda) y su resolución se fija para que mida exactamente los cm del pedido: al abrirlo en el programa de
 * impresión sale a 25 × 12 cm sin tocar nada. No se remuestrea: los píxeles son los del cliente.
 */
final class ArchivoTamanoReal
{
    /** «25 × 12 cm», «9,5 × 9,5 cm». */
    public static function medida(ImpresionPedido $impresion): string
    {
        return self::cm($impresion->ancho_mm).' × '.self::cm($impresion->alto_mm).' cm';
    }

    /** PED-0042_L1_frontal_25x12cm.png */
    public static function nombre(Pedido $pedido, int $numeroLinea, ImpresionPedido $impresion): string
    {
        return $pedido->numero.'_L'.$numeroLinea.'_'.Str::slug($impresion->zonaEn('es')).'_'.self::cm($impresion->ancho_mm).'x'.self::cm($impresion->alto_mm).'cm.png';
    }

    /** Píxeles por centímetro con los que el archivo mide lo pedido (para la orden de trabajo). */
    public static function pppEquivalente(ImpresionPedido $impresion): ?int
    {
        return $impresion->util_ancho_px && $impresion->ancho_mm > 0 ? (int) round($impresion->util_ancho_px * 25.4 / $impresion->ancho_mm) : null;
    }

    /** El PNG a tamaño real, o null si no hay archivo (texto, o borrado tras los 90 días hábiles, D230) o no se puede leer. */
    public static function png(ImpresionPedido $impresion): ?string
    {
        $disco = Storage::disk(Pedido::DISCO);
        if ($impresion->archivo === null || $impresion->ancho_mm <= 0 || $impresion->alto_mm <= 0 || ! $disco->exists($impresion->archivo)) {
            return null;
        }

        AnalizadorPng::limitarRecursos();
        $imagen = new Imagick;
        try {
            $imagen->readImageBlob((string) $disco->get($impresion->archivo));
            $imagen->setIteratorIndex(0);
            if ($impresion->util_ancho_px && $impresion->util_alto_px
                && ($impresion->util_ancho_px !== $imagen->getImageWidth() || $impresion->util_alto_px !== $imagen->getImageHeight())) {
                $imagen->cropImage($impresion->util_ancho_px, $impresion->util_alto_px, (int) $impresion->util_x, (int) $impresion->util_y);
                $imagen->setImagePage(0, 0, 0, 0);
            }
            $imagen->setImageUnits(Imagick::RESOLUTION_PIXELSPERCENTIMETER);
            $imagen->setImageResolution($imagen->getImageWidth() * 10 / $impresion->ancho_mm, $imagen->getImageHeight() * 10 / $impresion->alto_mm);
            $imagen->setImageFormat('png');

            return $imagen->getImageBlob();
        } catch (ImagickException) {
            return null;
        } finally {
            $imagen->clear();
        }
    }

    private static function cm(int $mm): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($mm / 10, 1, '.', ''), '0'), '.'));
    }
}
