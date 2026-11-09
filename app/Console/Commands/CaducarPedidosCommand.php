<?php

namespace App\Console\Commands;

use App\Dominio\Pedidos\CambiarEstado;
use App\Dominio\Pedidos\CambioNoValido;
use App\Dominio\Pedidos\EstadoPedido;
use App\Models\Ajuste;
use App\Models\Pedido;
use Illuminate\Console\Command;

/** Cancela los pedidos sin pagar pasados los días de Ajustes → General (D173): devuelven el cupón. */
class CaducarPedidosCommand extends Command
{
    protected $signature = 'pedidos:caducar';

    protected $description = 'Cancelar los pedidos que siguen sin pagar pasados los días de Ajustes';

    public function handle(CambiarEstado $cambiar): int
    {
        $dias = max(1, (int) Ajuste::obtener('pedidos.dias_sin_pago'));
        $n = 0;
        // Por id (no por posición: los cancelados salen de la consulta) y solo si bajo el bloqueo sigue sin pagar (B1).
        Pedido::where('estado', EstadoPedido::PendientePago->value)->where('created_at', '<', now()->subDays($dias))
            ->lazyById()->each(function (Pedido $pedido) use ($cambiar, $dias, &$n) {
                try {
                    $cambiar($pedido, EstadoPedido::Cancelado, nota: "Cancelado solo: sin pagar en {$dias} días", desde: EstadoPedido::PendientePago);
                    $n++;
                } catch (CambioNoValido) {
                    // Se pagó o se canceló mientras tanto: se deja como está.
                }
            });
        $this->info("Pedidos cancelados sin pagar: {$n}");

        return self::SUCCESS;
    }
}
