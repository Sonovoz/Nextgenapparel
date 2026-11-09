<?php

namespace App\Dominio\Produccion;

use App\Dominio\Pedidos\EstadoPedido;
use App\Models\Ajuste;
use App\Models\Pedido;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;

/**
 * Pliegos DTI (v2, D193): los diseños (PNG) de los pedidos **en producción**, cada uno tantas veces como prendas
 * lleva su línea, colocados en pliegos del ancho del film (Producción → Pliegos DTI). PDF a tamaño real, una página
 * por pliego. Los textos de «Escribe tu marca» no son un archivo: se listan aparte.
 */
final class PliegosDti
{
    /** @return array{piezas: list<array{id: string, ancho: int, alto: int, archivo: string}>, textos: list<string>} */
    public static function piezas(): array
    {
        $piezas = [];
        $textos = [];
        Pedido::where('estado', EstadoPedido::EnProduccion->value)->with('lineas.impresiones')->orderBy('id')->get()->each(function (Pedido $pedido) use (&$piezas, &$textos): void {
            foreach ($pedido->lineas->values() as $n => $linea) {
                foreach ($linea->impresiones as $impresion) {
                    $nombre = OrdenTrabajo::nombreArchivo($pedido, $n + 1, $impresion);
                    if ($impresion->archivo === null) {
                        $textos[] = $pedido->numero.' L'.($n + 1).' · '.$impresion->zonaEn('es').' · «'.$impresion->texto.'» × '.$linea->unidades;

                        continue;
                    }
                    for ($k = 1; $k <= $linea->unidades; $k++) {
                        $piezas[] = ['id' => pathinfo($nombre, PATHINFO_FILENAME).'-'.$k, 'ancho' => $impresion->ancho_mm, 'alto' => $impresion->alto_mm, 'archivo' => $impresion->archivo];
                    }
                }
            }
        });

        return ['piezas' => $piezas, 'textos' => $textos];
    }

    /** @return array{pliegos: list<array{largo: int, piezas: list<array<string, mixed>>}>, fuera: list<string>, textos: list<string>, ancho: int} */
    public static function plan(): array
    {
        ['piezas' => $piezas, 'textos' => $textos] = self::piezas();
        $ancho = (int) Ajuste::obtener('produccion.pliego_ancho_mm');
        $plan = ColocarEnPliegos::colocar(array_map(fn ($p) => ['id' => $p['id'], 'ancho' => $p['ancho'], 'alto' => $p['alto']], $piezas),
            $ancho, (int) Ajuste::obtener('produccion.pliego_largo_mm'), (int) Ajuste::obtener('produccion.pliego_separacion_mm'));
        $archivos = array_column($piezas, 'archivo', 'id');
        foreach ($plan['pliegos'] as &$pliego) {
            foreach ($pliego['piezas'] as &$p) {
                $p['archivo'] = $archivos[$p['id']];
            }
        }

        return [...$plan, 'textos' => $textos, 'ancho' => $ancho];
    }

    /** El PDF a tamaño real: una página por pliego, cada diseño en sus mm (girado si hace falta). */
    public static function pdf(): string
    {
        $plan = self::plan();
        $disco = Storage::disk(Pedido::DISCO);
        $temporal = 'tmp/pliegos-'.Str::random(8);
        $girados = [];
        foreach ($plan['pliegos'] as &$pliego) {
            foreach ($pliego['piezas'] as &$p) {
                $ruta = $p['archivo'];
                if ($p['girada']) {
                    if (! isset($girados[$ruta])) {
                        $imagen = new Imagick($disco->path($ruta));
                        $imagen->rotateImage('transparent', 90);
                        $disco->put($girados[$ruta] = $temporal.'/'.md5($ruta).'.png', $imagen->getImageBlob());
                    }
                    $ruta = $girados[$ruta];
                }
                $p['ruta'] = $disco->path($ruta);
            }
        }
        try {
            $paginas = array_map(fn ($pl) => ['ancho' => $plan['ancho'], 'largo' => $pl['largo'], 'piezas' => $pl['piezas']], $plan['pliegos']);
            $pdf = Pdf::loadView('pdf.pliegos', ['paginas' => $paginas])
                ->setOption(['isRemoteEnabled' => false, 'chroot' => $disco->path(''), 'dpi' => 300, 'isFontSubsettingEnabled' => true]);
            if ($paginas !== []) {
                $pdf->setPaper([0, 0, $plan['ancho'] * 72 / 25.4, max(array_column($paginas, 'largo')) * 72 / 25.4]);
            }

            return $pdf->output();
        } finally {
            $disco->deleteDirectory($temporal);
        }
    }
}
