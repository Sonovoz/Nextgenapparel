<?php

namespace App\Dominio\Produccion;

use App\Models\Pedido;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Paquete ZIP de producción de un pedido (paso 6.5b, ficha § 8): los originales de los
 * diseños con su nombre de taller (PED-0042_L1_frontal.png), las maquetas de cada cara y la
 * orden de trabajo en PDF. Se crea al pedirlo en un archivo temporal.
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
                    $zip->addFromString('disenos/'.OrdenTrabajo::nombreArchivo($pedido, $i + 1, $impresion), $disco->get($impresion->archivo));
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
