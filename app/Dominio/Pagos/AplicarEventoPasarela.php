<?php

namespace App\Dominio\Pagos;

use App\Dominio\Pedidos\CambiarEstado;
use App\Dominio\Pedidos\EstadoPedido;
use App\Dominio\Precios\Formato;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Aplica el resultado de un cobro una sola vez (D181), venga del aviso de Redsys o de la vuelta del
 * cliente. La pasarela manda, nunca el navegador. Un cobro que no cuadra (otro importe, un pedido ya
 * pagado o cancelado) no cambia el pedido: queda una nota para que el equipo lo devuelva.
 *
 * @return 'cobrado'|'rechazado'|'revisar'|'repetido'|'desconocido'
 */
final class AplicarEventoPasarela
{
    public function __construct(private readonly CambiarEstado $cambiarEstado) {}

    public function __invoke(string $pasarela, EventoPasarela $evento): string
    {
        $pedidoId = Pago::where('pasarela', $pasarela)->where('referencia', $evento->referencia)->value('pedido_id');
        if ($pedidoId === null) {
            return 'desconocido';
        }

        try {
            return DB::transaction(function () use ($pasarela, $evento, $pedidoId): string {
                DB::table('avisos_pasarela')->insert(['pasarela' => $pasarela, 'evento' => mb_substr($evento->id, 0, 120)]);
                $pedido = Pedido::whereKey($pedidoId)->lockForUpdate()->firstOrFail(); // el pedido primero, como el resto del dinero
                $pago = Pago::where('pasarela', $pasarela)->where('referencia', $evento->referencia)->lockForUpdate()->firstOrFail();

                if (! $evento->cobrado) {
                    if ($pago->estado !== 'cobrado') {
                        $pago->update(['estado' => 'rechazado', 'error' => $evento->error]);
                    }

                    return 'rechazado';
                }
                $pago->update(['estado' => 'cobrado', 'cobrado_en' => now(), 'error' => null]);
                $cobrado = $evento->importe ?? $pago->importe;
                if ($cobrado !== $pedido->total || $pedido->estado !== EstadoPedido::PendientePago) {
                    $pedido->notas()->create(['tipo' => 'privada', 'texto' => mb_substr("Cobro {$pago->referencia} de ".Formato::euros($cobrado, 'es')
                        ." con el pedido «{$pedido->estado->etiqueta()}» y un total de ".Formato::euros($pedido->total, 'es').': revísalo y devuelve lo que sobre.', 0, 500)]);

                    return 'revisar';
                }
                ($this->cambiarEstado)($pedido, EstadoPedido::Pagado, null,
                    'Cobrado con '.ucfirst($pasarela).($pago->metodo ? " ({$pago->metodo})" : '').' · '.$pago->referencia.($pago->pruebas ? ' · PRUEBA' : ''), desde: EstadoPedido::PendientePago);

                return 'cobrado';
            });
        } catch (UniqueConstraintViolationException) {
            return 'repetido'; // aviso ya aplicado
        }
    }
}
