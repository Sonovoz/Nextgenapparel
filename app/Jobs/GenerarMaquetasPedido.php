<?php

namespace App\Jobs;

use App\Dominio\Pedidos\GenerarMaquetas;
use App\Models\Pedido;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Genera las maquetas de un pedido en la cola (paso 6.2, D98), con reintentos. Si fallan
 * todos, el pedido sigue adelante y el fallo llega a la alerta de errores (regla 6).
 */
class GenerarMaquetasPedido implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public int $timeout = 120;

    public function __construct(public readonly int $pedidoId) {}

    public function handle(GenerarMaquetas $generar): void
    {
        $pedido = Pedido::find($this->pedidoId);
        if ($pedido !== null) {
            $generar($pedido);
        }
    }

    public function failed(Throwable $error): void
    {
        report($error);
    }
}
