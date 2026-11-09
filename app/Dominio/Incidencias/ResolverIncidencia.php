<?php

namespace App\Dominio\Incidencias;

use App\Dominio\Facturacion\EmisorIncompleto;
use App\Dominio\Facturacion\EmitirFactura;
use App\Dominio\Precios\Formato;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Incidencia;
use App\Models\User;
use App\Notifications\Pedidos\IncidenciaResuelta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use LogicException;

/**
 * El equipo resuelve una incidencia (paso 6.6b, D178), una sola vez y con una respuesta para el cliente:
 *
 * - **Reimpresión**: se vuelven a imprimir las prendas (el equipo lo organiza con la nota del pedido).
 * - **Reembolso parcial**: importe con impuestos, como mucho lo que queda por devolver del pedido; se
 *   emite su **rectificativa parcial** al momento. El dinero se devuelve en el TPV hasta Redsys (fase 7).
 * - **Rechazo**: con el motivo en la respuesta.
 */
final class ResolverIncidencia
{
    public function __construct(private readonly EmitirFactura $facturas) {}

    public function __invoke(Incidencia $incidencia, ResolucionIncidencia $resolucion, string $respuesta, ?int $importe, User $quien): Incidencia
    {
        $respuesta = trim($respuesta);
        if ($respuesta === '') {
            throw new IncidenciaNoValida('Escribe la respuesta para el cliente.');
        }
        if ($resolucion === ResolucionIncidencia::Reembolso && ($importe === null || $importe <= 0)) {
            throw new IncidenciaNoValida('Indica el importe del reembolso.');
        }

        $incidencia = DB::transaction(function () use ($incidencia, $resolucion, $respuesta, $importe, $quien): Incidencia {
            $incidencia = Incidencia::whereKey($incidencia->id)->lockForUpdate()->firstOrFail();
            if (! $incidencia->abierta()) {
                throw new IncidenciaNoValida("La incidencia {$incidencia->codigo} ya está resuelta.");
            }
            $factura = null;
            if ($resolucion === ResolucionIncidencia::Reembolso) {
                $pedido = $incidencia->pedido;
                try {
                    $factura = $this->facturas->rectificativaParcial($pedido, (int) $importe,
                        __('facturas.reembolso_incidencia', ['codigo' => $incidencia->codigo], $pedido->idioma), "Incidencia {$incidencia->codigo}");
                } catch (InvalidArgumentException) {
                    throw new IncidenciaNoValida('El reembolso no puede pasar de lo que queda por devolver del pedido ('.Formato::euros($this->facturas->pendienteDeRectificar($pedido), 'es').').');
                } catch (EmisorIncompleto|LogicException $e) {
                    throw new IncidenciaNoValida($e->getMessage());
                }
            }
            $incidencia->update([
                'estado' => 'resuelta', 'resolucion' => $resolucion, 'importe_reembolso' => $factura === null ? null : (int) $importe,
                'factura_id' => $factura?->id, 'respuesta' => mb_substr($respuesta, 0, 2000), 'user_id' => $quien->id, 'resuelta_en' => now(),
            ]);
            $incidencia->pedido->notas()->create(['tipo' => 'privada', 'user_id' => $quien->id, 'texto' => mb_substr("Incidencia {$incidencia->codigo} resuelta: {$resolucion->etiqueta('es')}"
                .($factura !== null ? ' de '.Formato::euros((int) $importe, 'es')." (rectificativa {$factura->codigo}; devuelve el dinero en el TPV hasta que llegue Redsys)" : '').'.', 0, 500)]);

            return $incidencia;
        });

        Actividad::apuntar('pedidos', 'Incidencia resuelta: '.$resolucion->etiqueta('es'), $incidencia->pedido, ['incidencia' => $incidencia->codigo, 'importe' => $incidencia->importe_reembolso], $quien);
        $pedido = $incidencia->pedido;
        if ($pedido->correoConfirmado()) {
            Notification::route('mail', $pedido->correo)->notify((new IncidenciaResuelta($incidencia))->locale($pedido->idioma));
        }

        return $incidencia;
    }
}
