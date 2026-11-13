<?php

namespace App\Dominio\Disenos;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Dominio\Sistema\Salud\Latido;
use App\Models\Ajuste;
use App\Models\Diseno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Retención de los diseños (paso 4.6, minimización de datos del RGPD; D230):
 *
 * - Sin venta, de cualquiera (con cuenta o sin ella), se borran con sus archivos pasadas las
 *   horas de Ajustes → General (72 por defecto) desde que se subieron. Si están en un carrito
 *   que se ha usado dentro de ese plazo, esperan: el cliente puede estar pagando.
 * - Los que han llegado a un pedido se quedan en la biblioteca mientras algún pedido suyo
 *   conserve sus archivos (90 días desde el envío, LimpiarArchivosPedidos).
 * - Los archivos sin fila de más de un día también: quedan si un proceso muere a mitad
 *   de una subida (revisión N3, D148).
 *
 * - Un diseño usado en un pedido no se borra aquí: el pedido tiene su copia, pero el
 *   cliente lo conserva en su biblioteca (revisión N3 del 6.1, B1).
 */
final class LimpiarDisenos
{
    /**
     * @param  bool  $tambienRecientes  D231: «Borrar archivos sin venta» del backend: también los de menos de las horas
     *                                  de Ajustes, salvo los que están en un carrito usado dentro de ese plazo.
     * @return array{disenos: int, huerfanos: int}
     */
    public function __invoke(bool $tambienRecientes = false): array
    {
        $resultado = ['disenos' => 0, 'huerfanos' => 0];

        $this->disenosCaducados($tambienRecientes)->chunkById(200, function ($disenos) use (&$resultado): void {
            foreach ($disenos as $diseno) {
                $diseno->delete(); // borra también sus archivos (Diseno::booted)
                $resultado['disenos']++;
            }
        });

        $disco = Storage::disk(GuardarDiseno::DISCO);
        $conocidos = Diseno::pluck('ruta')->merge(Diseno::pluck('miniatura'))->filter()->flip();
        $haceUnDia = now()->subDay()->getTimestamp();
        foreach ($disco->allFiles() as $archivo) {
            if (! $conocidos->has($archivo) && $disco->lastModified($archivo) < $haceUnDia) {
                $disco->delete($archivo);
                $resultado['huerfanos']++;
            }
        }

        // Carpetas que se han quedado vacías (de la más profunda a la raíz). rmdir no es
        // recursivo: si una subida acaba de escribir dentro, falla y no borra nada. La carpeta
        // del mes en curso (y su año) no se toca nunca.
        $enUso = [now()->format('Y/m'), now()->format('Y')];
        foreach (collect($disco->allDirectories())->sortByDesc(fn ($d) => substr_count($d, '/')) as $carpeta) {
            if (! in_array($carpeta, $enUso, true)) {
                @rmdir($disco->path($carpeta));
            }
        }

        if ($resultado['disenos'] + $resultado['huerfanos'] > 0) {
            Actividad::apuntar('disenos', 'Limpieza de diseños', propiedades: [
                'diseños borrados' => $resultado['disenos'],
                'archivos huérfanos borrados' => $resultado['huerfanos'],
            ]);
        }

        Latido::registrar(Latido::LIMPIEZA_DISENOS);

        return $resultado;
    }

    /** @return Builder<Diseno> */
    public function disenosCaducados(bool $tambienRecientes = false)
    {
        $limite = now()->subHours(max(1, (int) Ajuste::obtener('disenos.horas_sin_venta')));

        return Diseno::where('created_at', '<', $tambienRecientes ? now() : $limite)
            // En un carrito usado dentro del plazo: puede estar a punto de pagarse.
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('impresiones_carrito')
                ->join('lineas_carrito', 'lineas_carrito.id', '=', 'impresiones_carrito.linea_carrito_id')
                ->join('carritos', 'carritos.id', '=', 'lineas_carrito.carrito_id')
                ->whereColumn('impresiones_carrito.diseno_id', 'disenos.id')->where('carritos.updated_at', '>=', $limite))
            // Vendido: mientras algún pedido suyo conserve los archivos de impresión.
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('impresiones_pedido')
                ->join('lineas_pedido', 'lineas_pedido.id', '=', 'impresiones_pedido.linea_pedido_id')
                ->join('pedidos', 'pedidos.id', '=', 'lineas_pedido.pedido_id')
                ->whereColumn('impresiones_pedido.diseno_id', 'disenos.id')->whereNull('pedidos.archivos_borrados_en'));
    }
}
