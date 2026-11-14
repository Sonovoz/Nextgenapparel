<?php

namespace App\Dominio\Produccion;

use App\Models\Pedido;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Paquete ZIP de producción de un pedido (paso 6.5b, ficha § 8): en «disenos/» cada diseño a tamaño real, recortado
 * y con la resolución que lo hace medir los cm del pedido (PED-0042_L1_frontal_25x12cm.png, D238); en «originales/»
 * el archivo tal como lo subió el cliente; las maquetas de cada cara y la orden de trabajo en PDF. Se crea al pedirlo
 * en un archivo temporal.
 */
final class PaqueteProduccion
{
    /** @return string ruta del ZIP temporal (quien lo pide lo borra tras enviarlo) */
    public static function crear(Pedido $pedido): string
    {
        $pedido->loadMissing('lineas.impresiones', 'lineas.maquetas');
        $ruta = tempnam(sys_get_temp_dir(), 'paquete-').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se ha podido crear el paquete de producción.');
        }
        $disco = Storage::disk(Pedido::DISCO);
        foreach ($pedido->lineas->values() as $i => $linea) {
            foreach ($linea->impresiones as $impresion) {
                if ($impresion->archivo !== null && $disco->exists($impresion->archivo)) {
                    $real = ArchivoTamanoReal::png($impresion);
                    if ($real !== null) {
                        $zip->addFromString('disenos/'.ArchivoTamanoReal::nombre($pedido, $i + 1, $impresion), $real);
                    }
                    $zip->addFromString('originales/'.OrdenTrabajo::nombreArchivo($pedido, $i + 1, $impresion), (string) $disco->get($impresion->archivo));
                }
            }
            foreach ($linea->maquetas as $maqueta) {
                if ($disco->exists($maqueta->ruta)) {
                    $zip->addFromString('maquetas/'.$pedido->numero.'_L'.($i + 1).'_'.$maqueta->cara.'.webp', $disco->get($maqueta->ruta));
                }
            }
        }
        $zip->addFromString($pedido->numero.'_orden-de-trabajo.pdf', OrdenTrabajo::pdf($pedido));
        $zip->close();

        return $ruta;
    }
}
