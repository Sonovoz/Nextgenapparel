<?php

namespace App\Http\Controllers;

use App\Dominio\Pagos\AplicarEventoPasarela;
use App\Dominio\Pagos\AvisoNoValido;
use App\Dominio\Pagos\IniciarPago;
use App\Dominio\Pagos\PagoNoDisponible;
use App\Dominio\Pagos\Pasarela;
use App\Dominio\Pagos\PasarelaSimulada;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Pagar el pedido (fase 7, D181): el cliente elige tarjeta o Bizum y sale a la página segura de
 * la pasarela; el cobro se aplica con el aviso firmado o con la vuelta firmada del cliente.
 */
class PagoController
{
    public function pagar(Request $request, Pedido $pedido, IniciarPago $iniciar, PedidoController $pedidos): View|RedirectResponse
    {
        abort_unless($pedidos->puedeVer($request, $pedido), 404);
        $metodo = $request->validate(['metodo' => ['nullable', 'string', 'max:20']])['metodo'] ?? null;
        $idioma = app()->getLocale();
        try {
            $iniciado = $iniciar($pedido, $metodo, route("pedidos.{$idioma}.pago-vuelta", ['pedido' => $pedido->uuid]), route('pagos.aviso'));
        } catch (PagoNoDisponible $e) {
            report($e);

            return redirect(PedidoController::url('ver', $pedido))->with('aviso_pago', __('pagos.no_disponible'));
        }

        return view('pagos.redirigir', ['iniciado' => $iniciado]);
    }

    /** La vuelta del cliente desde la pasarela: con resultado firmado, se aplica (por si el aviso no llega). */
    public function vuelta(Request $request, Pedido $pedido, Pasarela $pasarela, AplicarEventoPasarela $aplicar): RedirectResponse
    {
        try {
            $evento = $pasarela->evento($request->query());
        } catch (AvisoNoValido) {
            $evento = null;
        }
        $resultado = $evento === null ? null : $aplicar($pasarela->nombre(), $evento);
        $mensaje = match (true) {
            $evento !== null && ! $evento->cobrado => ['aviso_pago', __('pagos.error_'.$evento->error)],
            in_array($resultado, ['cobrado', 'repetido'], true) || $pedido->fresh()?->estado->value === 'pagado' => ['estado_pedido', __('pagos.cobrado')],
            default => ['estado_pedido', __('pagos.comprobando')],
        };

        return redirect(PedidoController::url('ver', $pedido))->with(...$mensaje);
    }

    /** El aviso firmado de Redsys (servidor a servidor). Lo que no está firmado, 400. */
    public function aviso(Request $request, Pasarela $pasarela, AplicarEventoPasarela $aplicar): Response
    {
        try {
            $evento = $pasarela->evento($request->post());
        } catch (AvisoNoValido) {
            return response('Firma no válida', 400);
        }
        if ($evento === null) {
            return response('Sin resultado', 400);
        }
        $aplicar($pasarela->nombre(), $evento);

        return response('OK');
    }

    /** Página del pago simulado (solo fuera de producción): «Pago correcto» o «Pago rechazado». */
    public function simulada(Request $request, Pasarela $pasarela): View
    {
        abort_unless($pasarela instanceof PasarelaSimulada && $request->hasValidSignature(), 404);
        $referencia = (string) $request->query('referencia');
        $importe = (int) $request->query('importe');
        $vuelta = (string) $request->query('vuelta');
        $enlace = fn (string $resultado) => $vuelta.(str_contains($vuelta, '?') ? '&' : '?').http_build_query([
            'sim_referencia' => $referencia, 'sim_resultado' => $resultado, 'sim_importe' => $importe,
            'sim_firma' => PasarelaSimulada::firmar($referencia.'|'.$resultado.'|'.$importe),
        ]);

        return view('pagos.simulada', ['importe' => $importe, 'correcto' => $enlace('ok'), 'rechazado' => $enlace('ko')]);
    }
}
