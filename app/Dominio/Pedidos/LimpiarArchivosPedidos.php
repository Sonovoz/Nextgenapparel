<?php

namespace App\Dominio\Pedidos;

use App\Dominio\Sistema\Actividad\Actividad;
use App\Dominio\Sistema\Salud\Latido;
use App\Models\Ajuste;
use App\Models\ImpresionPedido;
use App\Models\Pedido;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * D230: los archivos de impresión (PNG originales) de un pedido se guardan en custodia los días **hábiles** de
 * Ajustes → General (90 por defecto, de lunes a viernes como el plazo de entrega) desde su envío; si no llega a
 * enviarse (cancelado o reembolsado), desde la fecha del pedido. Después se borran sin posibilidad de
 * recuperación. El pedido, sus líneas, maquetas, miniaturas y la factura se conservan siempre.
 */
final class LimpiarArchivosPedidos
{
    /** Estados con los que el pedido ya no se va a producir. */
    private const CERRADOS = [EstadoPedido::Enviado, EstadoPedido::Entregado, EstadoPedido::Cancelado, EstadoPedido::Reembolsado];

    /** @return array{pedidos: int, archivos: int} */
    public function __invoke(): array
    {
        $resultado = ['pedidos' => 0, 'archivos' => 0];
        $disco = Storage::disk(Pedido::DISCO);

        $this->candidatos()->with('lineas.impresiones')->chunkById(100, function ($pedidos) use (&$resultado, $disco): void {
            foreach ($pedidos as $pedido) {
                if (self::fechaBorrado($pedido)->isFuture()) {
                    continue; // aún no se han cumplido los días hábiles
                }
                foreach ($pedido->lineas as $linea) {
                    foreach ($linea->impresiones as $impresion) {
                        if ($impresion->archivo !== null) {
                            $disco->delete($impresion->archivo);
                            $resultado['archivos']++;
                        }
                    }
                }
                ImpresionPedido::whereIn('linea_pedido_id', $pedido->lineas->pluck('id'))->update(['archivo' => null]);
                $pedido->forceFill(['archivos_borrados_en' => now()])->saveQuietly();
                $resultado['pedidos']++;
            }
        });

        Latido::registrar(Latido::LIMPIEZA_PEDIDOS);
        if ($resultado['pedidos'] > 0) {
            Actividad::apuntar('pedidos', 'Archivos de impresión de pedidos antiguos borrados', propiedades: [
                'pedidos' => $resultado['pedidos'], 'archivos' => $resultado['archivos'],
            ]);
        }

        return $resultado;
    }

    /**
     * Día en que se borran los archivos de un pedido: los días hábiles de Ajustes desde su primer envío
     * (o desde el pedido si no se envió).
     */
    public static function fechaBorrado(Pedido $pedido): CarbonImmutable
    {
        $envio = $pedido->historial()->whereIn('estado', [EstadoPedido::Enviado->value, EstadoPedido::Entregado->value])->min('created_at');
        $desde = CarbonImmutable::parse($envio ?? $pedido->created_at)->startOfDay();

        return PlazoEntrega::sumarLaborables($desde, self::dias());
    }

    public static function dias(): int
    {
        return max(1, (int) Ajuste::obtener('pedidos.dias_archivos'));
    }

    /**
     * Pedidos cerrados con los archivos aún guardados cuyo plazo puede haberse cumplido antes de $hasta: 90 días
     * hábiles son siempre más de 90 naturales, así que la base de datos descarta los que seguro que no; el día
     * exacto lo decide fechaBorrado().
     *
     * @return Builder<Pedido>
     */
    public function candidatos(?\DateTimeInterface $hasta = null): Builder
    {
        $limite = ($hasta === null ? CarbonImmutable::now() : CarbonImmutable::instance($hasta))->subDays(self::dias());
        $cerrados = array_map(fn (EstadoPedido $e) => $e->value, self::CERRADOS);

        return Pedido::query()->whereNull('archivos_borrados_en')->whereIn('estado', $cerrados)->where('created_at', '<', $limite);
    }

    /**
     * Los que se borran antes de $hasta (para el aviso del backend y el ZIP de antes de borrar).
     *
     * @return Collection<int, Pedido>
     */
    public function seBorranAntesDe(\DateTimeInterface $hasta): Collection
    {
        return $this->candidatos($hasta)->get()->filter(fn (Pedido $p) => self::fechaBorrado($p)->lessThan($hasta))->values();
    }
}
