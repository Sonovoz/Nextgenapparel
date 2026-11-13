<?php

namespace App\Dominio\Documentos;

use App\Dominio\Presupuestos\MaquetasPresupuesto;
use App\Models\Factura;
use App\Models\LineaPedido;
use App\Models\MaquetaPedido;
use App\Models\Pedido;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Throwable;

/**
 * «Tu colección» en la factura (D215): las maquetas del pedido (cada cara con sus diseños), como JPEG
 * embebido para dompdf. Solo en las facturas, no en las rectificativas. Si falta una imagen, se omite.
 */
final class FotosFactura
{
    /** @return list<array{nombre: string, detalle: string, fotos: list<array{cara: string, src: string}>}> */
    public static function de(Factura $factura): array
    {
        if ($factura->rectifica !== null || ! extension_loaded('imagick')) {
            return [];
        }
        $idioma = $factura->idioma;
        $disco = Storage::disk(Pedido::DISCO);

        $lineas = LineaPedido::where('pedido_id', $factura->pedido_id)->with('maquetas')->orderBy('id')->get()->all();

        return collect($lineas)->map(function (LineaPedido $linea) use ($disco, $idioma): array {
            $fotos = $linea->maquetas->map(function (MaquetaPedido $m) use ($disco): ?array {
                try {
                    if (! $disco->exists($m->ruta)) {
                        return null;
                    }
                    $imagen = new Imagick;
                    $imagen->readImageBlob((string) $disco->get($m->ruta));

                    return ['cara' => $m->cara, 'src' => MaquetasPresupuesto::jpeg($imagen)];
                } catch (Throwable) {
                    return null;
                }
            })->filter()->values()->all();

            return [
                'nombre' => $linea->nombreEn($idioma).(($c = $linea->colorEn($idioma)) ? ' · '.$c : ''),
                'detalle' => __('pdf.unidades', ['n' => $linea->unidades], $idioma),
                'fotos' => $fotos,
            ];
        })->filter(fn (array $l) => $l['fotos'] !== [])->values()->all();
    }
}
