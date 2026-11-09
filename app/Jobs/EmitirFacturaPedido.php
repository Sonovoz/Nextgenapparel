<?php

namespace App\Jobs;

use App\Dominio\Facturacion\EmisorIncompleto;
use App\Dominio\Facturacion\EmitirFactura;
use App\Dominio\Pedidos\CorreoPedido;
use App\Models\Pedido;
use App\Notifications\Pedidos\CorreoDelPedido;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Factura o rectificativa de un pedido, en la cola (paso 6.5a, D175), y su correo al
 * cliente con el PDF. Sin datos fiscales no se reintenta: queda pendiente y la emite
 * `facturas:emitir-pendientes` cuando estén.
 */
class EmitirFacturaPedido implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /** @param  'factura'|'rectificativa'  $tipo */
    public function __construct(public readonly int $pedidoId, public readonly string $tipo = 'factura', public readonly ?string $motivo = null) {}

    public function handle(EmitirFactura $emitir): void
    {
        $pedido = Pedido::find($this->pedidoId);
        if ($pedido === null) {
            return;
        }
        try {
            $factura = $this->tipo === 'rectificativa' ? $emitir->rectificativa($pedido, (string) $this->motivo) : $emitir->delPedido($pedido);
        } catch (EmisorIncompleto $e) {
            $pedido->historial()->create(['estado' => $pedido->estado, 'nota' => 'Factura pendiente: '.$e->getMessage()]);

            return;
        }
        if ($factura !== null && $factura->wasRecentlyCreated) {
            CorreoPedido::enviar($pedido, new CorreoDelPedido($pedido, 'factura', factura: $factura));
        }
    }

    public function failed(Throwable $error): void
    {
        report($error);
    }
}
