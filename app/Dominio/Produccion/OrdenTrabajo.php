<?php

namespace App\Dominio\Produccion;

use App\Models\ImpresionPedido;
use App\Models\LineaPedido;
use App\Models\Pedido;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use Throwable;

/**
 * Orden de trabajo de un pedido (paso 6.5b, ficha § 8 Producción): lo que el taller
 * necesita para imprimirlo, sin datos de facturación. Cada línea con su prenda, color y
 * tallas, las maquetas de cada cara y cada impresión con su archivo, medidas, posición
 * (D99) y calidad. Los nombres de archivo son los del paquete ZIP.
 */
final class OrdenTrabajo
{
    /** «PED-0042_L1_frontal.png»: el nombre del original de una impresión en el paquete. */
    public static function nombreArchivo(Pedido $pedido, int $numeroLinea, ImpresionPedido $impresion): string
    {
        return $pedido->numero.'_L'.$numeroLinea.'_'.Str::slug($impresion->zonaEn('es')).'.png';
    }

    public static function pdf(Pedido $pedido): string
    {
        $pedido->loadMissing('lineas.impresiones', 'lineas.maquetas');

        return Pdf::loadView('pdf.orden-trabajo', [
            'pedido' => $pedido,
            'maquetas' => $pedido->lineas->mapWithKeys(fn (LineaPedido $l) => [$l->id => $l->maquetas->map(fn ($m) => self::imagen($m->ruta))->filter()->values()->all()])->all(),
        ])->setPaper('a4')->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true])->output();
    }

    /** La maqueta como JPEG embebido: dompdf no lee WebP en todos los servidores ni archivos remotos. */
    private static function imagen(string $ruta): ?string
    {
        try {
            $imagen = new Imagick;
            $imagen->readImageBlob(Storage::disk(Pedido::DISCO)->get($ruta));
            $imagen->setImageBackgroundColor('white');
            $imagen = $imagen->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $imagen->thumbnailImage(500, 0);
            $imagen->setImageFormat('jpeg');
            $imagen->setImageCompressionQuality(82);

            return 'data:image/jpeg;base64,'.base64_encode($imagen->getImageBlob());
        } catch (Throwable) {
            return null; // sin maqueta, la orden sale igual
        }
    }
}
