<?php

namespace App\Dominio\Disenos;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Ajuste;
use App\Models\Diseno;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Retención de los diseños (paso 4.6, minimización de datos del RGPD):
 *
 * - Los de visitantes sin cuenta se borran, con sus archivos, pasados los días de
 *   Ajustes → General («Guardar sin pedido»). Los de clientes con cuenta se conservan
 *   (su retención se decide en la fase 5).
 * - Los archivos sin fila de más de un día también: quedan si un proceso muere a mitad
 *   de una subida (revisión N3, D148).
 *
 * IMPORTANTE para la fase 6: cuando existan los pedidos, un diseño usado en un pedido
 * no se borra aquí (se conserva con el pedido); hay que excluirlo en disenosCaducados().
 */
final class LimpiarDisenos
{
    /** @return array{disenos: int, huerfanos: int} */
    public function __invoke(): array
    {
        $resultado = ['disenos' => 0, 'huerfanos' => 0];

        $this->disenosCaducados()->chunkById(200, function ($disenos) use (&$resultado): void {
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

        if ($resultado['disenos'] + $resultado['huerfanos'] > 0) {
            Actividad::apuntar('disenos', 'Limpieza de diseños', propiedades: [
                'diseños borrados' => $resultado['disenos'],
                'archivos huérfanos borrados' => $resultado['huerfanos'],
            ]);
        }

        return $resultado;
    }

    /** @return Builder<Diseno> */
    public function disenosCaducados()
    {
        $dias = max(1, (int) Ajuste::obtener('disenos.dias_sin_pedido'));

        return Diseno::whereNull('cliente_id')->where('created_at', '<', now()->subDays($dias));
    }
}
