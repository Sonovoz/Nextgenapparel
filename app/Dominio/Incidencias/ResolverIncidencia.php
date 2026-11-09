<?php

namespace App\Dominio\Incidencias;

use App\Dominio\Facturacion\EmisorIncompleto;
use App\Dominio\Facturacion\EmitirFactura;
use App\Dominio\Pagos\DevolverDinero;
use App\Dominio\Precios\Formato;
use App\Dominio\Sistema\Actividad\Actividad;
use App\Models\Incidencia;
use App\Models\Pedido;
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
 *   emite su **rectificativa parcial** y el dinero se devuelve por la pasarela (D182).
 * - **Rechazo**: con el motivo en la respuesta.
 */
final class ResolverIncidencia
{
    public function __construct(private readonly EmitirFactura $facturas, private readonly DevolverDinero $devolver) {}

    public function __invoke(Incidencia $incidencia, ResolucionIncidencia $resolucion, string $respuesta, ?int $importe, User $quien): Incidencia
    {
        $respuesta = trim($respuesta);
        if ($respuesta === '') {
            throw new IncidenciaNoValida('Escribe la respuesta para el cliente.');
        }
        if ($resolucion === ResolucionIncidencia::Reembolso && ($importe === null || $importe <= 0)) {
            throw new IncidenciaNoValida('Indica el importe del reembolso.');
        }

        if ($resolucion === ResolucionIncidencia::Reembolso) {
            $this->devolverAntes($incidencia, (int) $importe);
        }

        $incidencia = DB::transaction(function () use ($incidencia, $resolucion, $respuesta, $importe, $quien): Incidencia {
            // Primero el pedido (como los demás cambios de dinero) y luego la incidencia, antes de cualquier otra
            // lectura: así lo ya rectificado se lee con los datos confirmados (B2).
            Pedido::whereKey($incidencia->pedido_id)->lockForUpdate()->firstOrFail();
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
                .($factura !== null ? ' de '.Formato::euros((int) $importe, 'es')." (rectificativa {$factura->codigo})" : '').'.', 0, 500)]);

            return $incidencia;
        });

        Actividad::apuntar('pedidos', 'Incidencia resuelta: '.$resolucion->etiqueta('es'), $incidencia->pedido, ['incidencia' => $incidencia->codigo, 'importe' => $incidencia->importe_reembolso], $quien);
        $pedido = $incidencia->pedido;
        if ($pedido->correoConfirmado()) {
            Notification::route('mail', $pedido->correo)->notify((new IncidenciaResuelta($incidencia))->locale($pedido->idioma));
        }

        return $incidencia;
    }

    /**
     * El dinero por la pasarela (D182), **antes y fuera** de la transacción que emite la rectificativa
     * (auditoría final, M2): la llamada a Redsys no bloquea las facturas y, si algo falla después, el
     * reintento no devuelve otra vez (la clave `incidencia-{id}` ya está hecha). Si no se devuelve, no se resuelve.
     */
    private function devolverAntes(Incidencia $incidencia, int $importe): void
    {
        $pedido = $incidencia->pedido;
        if (! $incidencia->abierta()) {
            throw new IncidenciaNoValida("La incidencia {$incidencia->codigo} ya está resuelta.");
        }
        if ($importe > $this->facturas->pendienteDeRectificar($pedido)) {
            throw new IncidenciaNoValida('El reembolso no puede pasar de lo que queda por devolver del pedido ('.Formato::euros($this->facturas->pendienteDeRectificar($pedido), 'es').').');
        }
        if (DevolverDinero::pendiente($pedido) === 0) {
            return; // no se cobró por la pasarela (o ya se devolvió): solo la rectificativa
        }
        $devuelto = ($this->devolver)($pedido, $importe, "incidencia-{$incidencia->id}", "Incidencia {$incidencia->codigo}");
        if (! $devuelto->hecha) {
            throw new IncidenciaNoValida('No se ha podido devolver el dinero: '.$devuelto->error);
        }
    }
}
