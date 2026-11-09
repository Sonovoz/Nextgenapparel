<?php

namespace App\Dominio\Pedidos;

use App\Models\Ajuste;
use App\Models\Pedido;
use App\Notifications\Pedidos\CancelacionSolicitada;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * El cliente cancela su pedido desde el visor (paso 6.6, ficha § 8, D177).
 *
 * - Sin pagar: se cancela al momento (devuelve el uso del cupón, D173).
 * - Pagado y aún sin producir: queda la **petición** para el equipo, que cancela desde el backend
 *   (rectificativa automática, D175) y devuelve el dinero; el reembolso por Redsys llega en la fase 7.
 * - En producción o después: ya no se puede.
 */
final class CancelarPorCliente
{
    /** Se cancelan solos, sin dinero que devolver. */
    public const AL_MOMENTO = [EstadoPedido::PendientePago];

    /** Pagados y sin producir: los cancela el equipo. */
    public const CON_PETICION = [EstadoPedido::Pagado, EstadoPedido::DisenoEnRevision, EstadoPedido::RequiereCambios];

    public function __construct(private readonly CambiarEstado $cambiarEstado) {}

    /** Si el visor ofrece el botón: puede cancelarse y no hay ya una petición. */
    public static function puede(Pedido $pedido): bool
    {
        return in_array($pedido->estado, self::AL_MOMENTO, true)
            || (in_array($pedido->estado, self::CON_PETICION, true) && $pedido->cancelacion_solicitada_en === null);
    }

    /** @return 'cancelado'|'cancelado_devolucion'|'solicitado' */
    public function __invoke(Pedido $pedido, ?string $motivo): string
    {
        $motivo = trim((string) $motivo) === '' ? null : mb_substr(trim((string) $motivo), 0, 500);

        $resultado = DB::transaction(function () use ($pedido, $motivo): string {
            $pedido = Pedido::whereKey($pedido->getKey())->lockForUpdate()->firstOrFail();
            if (! self::puede($pedido)) {
                throw new CambioNoValido('cancelar_no_puede');
            }
            // Sin pagar, o pagado con la cancelación automática de Ajustes → General (el dinero vuelve solo, D182).
            if (in_array($pedido->estado, self::AL_MOMENTO, true) || (bool) Ajuste::obtener('pagos.cancelacion_automatica')) {
                $pagado = ! in_array($pedido->estado, self::AL_MOMENTO, true);
                ($this->cambiarEstado)($pedido, EstadoPedido::Cancelado, null, 'Cancelado por el cliente.'.($motivo ? " Motivo: {$motivo}" : ''));

                return $pagado ? 'cancelado_devolucion' : 'cancelado';
            }
            $pedido->update(['cancelacion_solicitada_en' => now(), 'motivo_cancelacion' => $motivo]);
            $pedido->notas()->create(['tipo' => 'privada', 'texto' => mb_substr('El cliente pide cancelar el pedido.'.($motivo ? " Motivo: {$motivo}" : '')
                .' Si aún no está en producción, cancélalo aquí: se emite la rectificativa y el dinero se devuelve solo.', 0, 500)]);

            return 'solicitado';
        });

        if ($resultado === 'solicitado' && filled($correo = Ajuste::obtener('tienda.correo_contacto'))) {
            Notification::route('mail', $correo)->notify(new CancelacionSolicitada($pedido->fresh() ?? $pedido));
        }

        return $resultado;
    }
}
