<?php

namespace App\Dominio\Pedidos;

use App\Models\Cupon;
use App\Models\Pedido;
use App\Models\User;
use App\Notifications\Pedidos\CorreoDelPedido;
use Illuminate\Support\Facades\DB;

/**
 * Cambia el estado de un pedido (paso 6.3), uno a uno o en bloque, siempre por aquí:
 *
 * - Solo los cambios de EstadoPedido::siguientes(); al pasar a «Enviado» hacen falta el
 *   transportista y el número de seguimiento; no se da por pagado un pedido de invitado
 *   con el correo sin confirmar.
 * - Al cancelar se devuelve el uso del cupón (decisión del cliente, D173).
 * - Cada cambio queda en el historial con quién lo hizo, su nota y si se avisó al cliente
 *   (el correo del estado, si está activo en Ajustes → Correos, paso 6.4).
 */
final class CambiarEstado
{
    /** @param  array{transportista?: ?string, numero_seguimiento?: ?string}  $envio */
    public function __invoke(Pedido $pedido, EstadoPedido $nuevo, ?User $quien = null, ?string $nota = null, array $envio = []): Pedido
    {
        return DB::transaction(function () use ($pedido, $nuevo, $quien, $nota, $envio) {
            $pedido = Pedido::whereKey($pedido->getKey())->lockForUpdate()->firstOrFail();
            $actual = $pedido->estado;
            if (! in_array($nuevo, $actual->siguientes(), true)) {
                throw new CambioNoValido("El pedido {$pedido->numero} está «{$actual->etiqueta()}» y no puede pasar a «{$nuevo->etiqueta()}».");
            }
            if ($nuevo === EstadoPedido::Pagado && ! $pedido->correoConfirmado()) {
                throw new CambioNoValido("El cliente del pedido {$pedido->numero} aún no ha confirmado su correo.");
            }
            $cambios = ['estado' => $nuevo];
            if ($nuevo === EstadoPedido::Enviado) {
                $transportista = trim((string) ($envio['transportista'] ?? ''));
                $seguimiento = trim((string) ($envio['numero_seguimiento'] ?? ''));
                if ($transportista === '' || $seguimiento === '') {
                    throw new CambioNoValido("Para enviar el pedido {$pedido->numero} faltan el transportista y el número de seguimiento.");
                }
                $cambios += ['transportista' => $transportista, 'numero_seguimiento' => $seguimiento];
            }

            if ($nuevo === EstadoPedido::Cancelado && $pedido->cupon !== null && $actual->cuentaComoUso()) {
                Cupon::where('codigo', $pedido->cupon)->where('usos', '>', 0)->decrement('usos');
            }
            $pedido->update($cambios);
            // El correo del estado, si está activo en Ajustes → Correos (sale al confirmar la transacción).
            $avisado = CorreosPedido::activo($nuevo) && CorreoPedido::enviar($pedido, new CorreoDelPedido($pedido, 'estado', $nuevo));
            $pedido->historial()->create(['estado' => $nuevo, 'user_id' => $quien?->id, 'nota' => blank($nota) ? null : trim($nota), 'notificado_cliente' => $avisado]);

            return $pedido;
        });
    }
}
