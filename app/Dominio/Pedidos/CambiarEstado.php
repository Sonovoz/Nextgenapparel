<?php

namespace App\Dominio\Pedidos;

use App\Jobs\DevolverPedido;
use App\Jobs\EmitirFacturaPedido;
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
    /**
     * @param  array{transportista?: ?string, numero_seguimiento?: ?string}  $envio
     * @param  ?EstadoPedido  $desde  si se indica, solo cambia si bajo el bloqueo sigue en ese estado (B1: un proceso
     *                                que decidió con una lectura anterior no pisa un cambio hecho mientras tanto)
     */
    public function __invoke(Pedido $pedido, EstadoPedido $nuevo, ?User $quien = null, ?string $nota = null, array $envio = [], ?EstadoPedido $desde = null): Pedido
    {
        return DB::transaction(function () use ($pedido, $nuevo, $quien, $nota, $envio, $desde) {
            $pedido = Pedido::whereKey($pedido->getKey())->lockForUpdate()->firstOrFail();
            $actual = $pedido->estado;
            if ($desde !== null && $actual !== $desde) {
                throw new CambioNoValido("El pedido {$pedido->numero} ya no está «{$desde->etiqueta()}».");
            }
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
            // Factura al darlo por pagado; rectificativa si se cancela uno ya pagado (D175). En la cola, tras confirmar.
            if ($nuevo === EstadoPedido::Pagado) {
                EmitirFacturaPedido::dispatch($pedido->id)->afterCommit();
            } elseif ($nuevo === EstadoPedido::Cancelado && in_array($actual, [EstadoPedido::Pagado, EstadoPedido::DisenoEnRevision, EstadoPedido::RequiereCambios], true)) {
                // Motivo fijo y en el idioma del cliente: la nota interna se queda en el historial (M2).
                EmitirFacturaPedido::dispatch($pedido->id, 'rectificativa', __('facturas.motivo_cancelacion', ['numero' => $pedido->numero], $pedido->idioma))->afterCommit();
                DevolverPedido::dispatch($pedido->id)->afterCommit(); // el dinero, por la pasarela (D182)
            }
            // El correo del estado, si está activo en Ajustes → Correos (sale al confirmar la transacción).
            $avisado = CorreosPedido::activo($nuevo) && CorreoPedido::enviar($pedido, new CorreoDelPedido($pedido, 'estado', $nuevo));
            $pedido->historial()->create(['estado' => $nuevo, 'user_id' => $quien?->id, 'nota' => blank($nota) ? null : trim($nota), 'notificado_cliente' => $avisado]);

            return $pedido;
        });
    }
}
