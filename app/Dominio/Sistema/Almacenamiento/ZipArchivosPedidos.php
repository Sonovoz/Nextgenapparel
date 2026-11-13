<?php

namespace App\Dominio\Sistema\Almacenamiento;

use App\Dominio\Pedidos\LimpiarArchivosPedidos;
use App\Models\Pedido;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * D231: antes de que se borren los originales de los pedidos (90 días hábiles), el equipo puede descargarlos en un
 * ZIP para guardarlos fuera del servidor: una carpeta por pedido con sus PNG.
 */
final class ZipArchivosPedidos
{
    public const DIAS_AVISO = 14;

    /** @return Collection<int, Pedido> pedidos cuyos originales se borran en los próximos días */
    public static function proximos(int $dias = self::DIAS_AVISO): Collection
    {
        return app(LimpiarArchivosPedidos::class)->seBorranAntesDe(now()->addDays($dias));
    }

    /**
     * Crea el ZIP en un archivo temporal y devuelve su ruta (quien lo descarga lo borra después).
     *
     * @param  Collection<int, Pedido>  $pedidos
     */
    public static function crear(Collection $pedidos): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'pedidos-').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se ha podido crear el ZIP.');
        }
        $disco = Storage::disk(Pedido::DISCO);
        $archivos = 0;
        foreach ($pedidos as $pedido) {
            $pedido->loadMissing('lineas.impresiones');
            foreach ($pedido->lineas as $l => $linea) {
                foreach ($linea->impresiones as $impresion) {
                    if ($impresion->archivo === null || ! $disco->exists($impresion->archivo)) {
                        continue;
                    }
                    $nombre = sprintf('%s/L%d_%s_%s.png', $pedido->numero ?? $pedido->uuid, $l + 1,
                        preg_replace('/[^\pL\pN]+/u', '-', (string) ($impresion->zona_nombre['es'] ?? 'zona')),
                        preg_replace('/[^\pL\pN.-]+/u', '-', pathinfo((string) $impresion->nombre_original, PATHINFO_FILENAME) ?: 'diseno'));
                    $zip->addFile($disco->path($impresion->archivo), $nombre);
                    $archivos++;
                }
            }
        }
        if ($archivos === 0) {
            $zip->addFromString('LEEME.txt', 'No hay archivos de impresión que guardar.');
        }
        $zip->close();

        return $ruta;
    }
}
