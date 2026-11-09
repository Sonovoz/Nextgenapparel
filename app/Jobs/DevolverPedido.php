<?php

namespace App\Jobs;

use App\Dominio\Pagos\DevolverDinero;
use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Al cancelar un pedido cobrado (paso 7.2, D182): devuelve por la pasarela lo que quede, en la cola y con
 * reintentos. Si no lo consigue, queda una nota para devolverlo a mano.
 */
class DevolverPedido implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 600];

    public function __construct(public readonly int $pedidoId) {}

    public function handle(DevolverDinero $devolver): void
    {
        $pedido = Pedido::find($this->pedidoId);
        if ($pedido === null || ($importe = DevolverDinero::pendiente($pedido)) === 0) {
            return; // nunca se cobró por la pasarela o ya está devuelto
        }
        $resultado = $devolver($pedido, $importe, "cancelacion-{$pedido->id}", 'Cancelación del pedido '.$pedido->numero);
        if (! $resultado->hecha) {
            throw new RuntimeException("No se ha podido devolver el pedido {$pedido->numero}: {$resultado->error}");
        }
        $pedido->notas()->create(['tipo' => 'privada', 'texto' => 'Dinero devuelto por la pasarela: '.number_format($importe / 100, 2, ',', '.').' €.']);
    }

    public function failed(Throwable $error): void
    {
        Pedido::find($this->pedidoId)?->notas()->create(['tipo' => 'privada', 'texto' => mb_substr('La devolución automática ha fallado: devuelve el dinero a mano en el TPV. '.$error->getMessage(), 0, 500)]);
        report($error);
    }
}
