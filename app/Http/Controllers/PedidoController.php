<?php

namespace App\Http\Controllers;

use App\Dominio\Carrito\CarritoActual;
use App\Dominio\Disenos\Dueno;
use App\Dominio\Facturacion\PdfFactura;
use App\Dominio\Incidencias\AbrirIncidencia;
use App\Dominio\Incidencias\IncidenciaNoValida;
use App\Dominio\Incidencias\MotivoIncidencia;
use App\Dominio\Pedidos\CambioNoValido;
use App\Dominio\Pedidos\CancelarPorCliente;
use App\Dominio\Pedidos\ConfirmarCorreoPedido;
use App\Dominio\Pedidos\CorreoPedido;
use App\Dominio\Pedidos\CrearPedido;
use App\Dominio\Pedidos\PedidoNoCreado;
use App\Dominio\Pedidos\RepetirPedido;
use App\Models\Ajuste;
use App\Models\Factura;
use App\Models\FotoIncidencia;
use App\Models\MaquetaPedido;
use App\Models\Pedido;
use App\Notifications\Pedidos\ConfirmaTuCorreo;
use App\Notifications\Pedidos\CorreoDelPedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Confirmar el pedido desde el cierre y verlo (paso 6.1, D169). Un pedido lo ve quien
 * lo creó en este navegador, el titular de la ficha con su cuenta o quien abre el enlace
 * firmado de su correo. El pago llega en la fase 7.
 */
class PedidoController
{
    /** Pedidos creados o confirmados en esta sesión: los puede ver sin cuenta. */
    public const SESION = 'pedidos_propios';

    public function confirmar(Request $request, CrearPedido $crear): RedirectResponse
    {
        $carrito = CarritoActual::de($request);
        $cliente = $request->user()?->cliente;
        $datos = $request->session()->get(CierreController::SESION);
        // Sin datos del cierre, o calculados por otro (un invitado que entra en su cuenta): al cierre.
        // Doble clic: la primera petición ya creó el pedido, vació el carrito y olvidó el cierre (M3).
        if (($carrito === null || $datos === null || ! $carrito->lineas()->exists()) && ($reciente = $this->reciente($request)) !== null) {
            return redirect(self::url('ver', $reciente));
        }
        if ($carrito === null || $datos === null || ($datos['cliente_id'] ?? null) !== $cliente?->id
            || CierreController::bloqueoDe($request, $cliente) !== null) {
            return redirect(CarritoController::url('cerrar'));
        }
        $request->validate(['condiciones' => ['accepted'], 'total' => ['required', 'integer', 'min:0']], [], ['condiciones' => mb_strtolower(__('pedidos.condiciones_campo'))]);

        try {
            $pedido = $crear($carrito, $datos, $cliente, (int) $request->input('total'), app()->getLocale(), $request->ip());
        } catch (PedidoNoCreado $e) {
            if ($e->motivo === 'vacio' && ($reciente = $this->reciente($request)) !== null) {
                return redirect(self::url('ver', $reciente));
            }

            return redirect(CarritoController::url($e->motivo === 'vacio' ? 'ver' : 'cerrar').'#total')
                ->with('aviso_pedido', __('pedidos.no_creado_'.$e->motivo));
        }

        $request->session()->forget(CierreController::SESION);
        $request->session()->push(self::SESION, $pedido->uuid);
        if ($cliente === null) {
            CarritoActual::olvidarInvitado();
            self::enviarConfirmacion($pedido);
        }

        return redirect(self::url('ver', $pedido));
    }

    public function ver(Request $request, Pedido $pedido): View
    {
        abort_unless($this->puedeVer($request, $pedido), 404);
        $pedido->load('lineas.impresiones', 'lineas.maquetas', 'facturas', 'historial', 'incidencias.fotos');

        return view('pedidos.ver', ['pedido' => $pedido, 'alternos' => $this->alternos('ver', $pedido), 'puedeCancelar' => CancelarPorCliente::puede($pedido),
            'puedeIncidencia' => AbrirIncidencia::puede($pedido), 'plazoIncidencia' => AbrirIncidencia::plazoHasta($pedido)]);
    }

    /** Cancelar desde el visor (paso 6.6, D177): al momento si no está pagado; si lo está, la petición al equipo. */
    public function cancelar(Request $request, Pedido $pedido, CancelarPorCliente $cancelar): RedirectResponse
    {
        abort_unless($this->puedeVer($request, $pedido), 404);
        $datos = $request->validate(['motivo' => ['nullable', 'string', 'max:500']]);
        try {
            $resultado = $cancelar($pedido, $datos['motivo'] ?? null);
        } catch (CambioNoValido) {
            return redirect(self::url('ver', $pedido))->with('estado_pedido', __('pedidos.cancelar_no_puede'));
        }

        return redirect(self::url('ver', $pedido))->with('estado_pedido', __('pedidos.cancelar_'.$resultado));
    }

    /** Abrir una incidencia con fotos (paso 6.6b, D178). */
    public function abrirIncidencia(Request $request, Pedido $pedido, AbrirIncidencia $abrir): RedirectResponse
    {
        abort_unless($this->puedeVer($request, $pedido), 404);
        $datos = $request->validate([
            'motivo' => ['required', Rule::enum(MotivoIncidencia::class)],
            'descripcion' => ['required', 'string', 'max:2000'],
            'fotos' => ['nullable', 'array', 'max:'.AbrirIncidencia::MAX_FOTOS],
            'fotos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [], ['motivo' => __('incidencias.motivo'), 'descripcion' => __('incidencias.descripcion'), 'fotos' => __('incidencias.titulo')]);
        try {
            $incidencia = $abrir($pedido, MotivoIncidencia::from($datos['motivo']), $datos['descripcion'], array_values($request->file('fotos', [])));
        } catch (IncidenciaNoValida $e) {
            return redirect(self::url('ver', $pedido).'#incidencias')->withInput()
                ->withErrors(['incidencia' => __('incidencias.'.$e->motivo, ['n' => AbrirIncidencia::MAX_FOTOS])]);
        }

        return redirect(self::url('ver', $pedido).'#incidencias')->with('estado_pedido', __('incidencias.abierta_ok', ['codigo' => $incidencia->codigo]));
    }

    /** Una foto de una incidencia: del disco privado, solo para quien puede ver el pedido. */
    public function fotoIncidencia(Request $request, Pedido $pedido, FotoIncidencia $foto): Response
    {
        abort_unless($this->puedeVer($request, $pedido) && $foto->incidencia->pedido_id === $pedido->id, 404);

        return Storage::disk(Pedido::DISCO)->response($foto->ruta, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** «Repetir pedido» (paso 6.6): las mismas líneas al carrito de quien lo pide, con el precio de hoy. */
    public function repetir(Request $request, Pedido $pedido, RepetirPedido $repetir): RedirectResponse
    {
        abort_unless($this->puedeVer($request, $pedido), 404);
        $carrito = CarritoActual::de($request, crear: true);
        $dueno = Dueno::de($request);
        abort_if($carrito === null || $dueno === null, 404);
        $resultado = $repetir($pedido, $carrito, $dueno);

        $mensaje = $resultado['anadidas'] > 0 ? trans_choice('pedidos.repetir_anadidas', $resultado['anadidas'], ['n' => $resultado['anadidas']]) : __('pedidos.repetir_ninguna');
        if ($resultado['omitidas'] !== []) {
            $mensaje .= ' '.__('pedidos.repetir_omitidas', ['lineas' => implode(', ', $resultado['omitidas'])]);
        }

        return redirect(CarritoController::url('ver'))->with('estado', $mensaje);
    }

    /** La maqueta de una cara (paso 6.2): del disco privado, solo para quien puede ver el pedido. */
    public function maqueta(Request $request, Pedido $pedido, MaquetaPedido $maqueta): Response
    {
        abort_unless($this->puedeVer($request, $pedido) && $maqueta->linea?->pedido_id === $pedido->id, 404);

        return Storage::disk(Pedido::DISCO)->response($maqueta->ruta, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** El PDF de una factura del pedido (paso 6.5a): solo para quien puede ver el pedido. */
    public function factura(Request $request, Pedido $pedido, Factura $factura): Response
    {
        abort_unless($this->puedeVer($request, $pedido) && $factura->pedido_id === $pedido->id, 404);

        return response(PdfFactura::generar($factura), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$factura->nombreArchivo().'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** El enlace del correo: confirma el correo del invitado y le enseña su pedido. */
    public function confirmarCorreo(Request $request, Pedido $pedido, ConfirmarCorreoPedido $confirmar): View|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            // Caducado o manipulado: puede pedir otro, que solo llega al correo del pedido.
            return view('pedidos.enlace-caducado', ['pedido' => $pedido, 'alternos' => $this->alternos('ver', $pedido)]);
        }
        $confirmar($pedido);
        if (! in_array($pedido->uuid, $request->session()->get(self::SESION, []), true)) {
            $request->session()->push(self::SESION, $pedido->uuid);
        }

        return redirect(self::url('ver', $pedido))->with('estado_pedido', __('pedidos.correo_confirmado'));
    }

    /** El enlace privado de los correos (paso 6.4): abre el pedido en cualquier navegador sin cuenta. */
    public function acceso(Request $request, Pedido $pedido): View|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return view('pedidos.enlace-caducado', ['pedido' => $pedido, 'alternos' => $this->alternos('ver', $pedido)]);
        }
        if (! in_array($pedido->uuid, $request->session()->get(self::SESION, []), true)) {
            $request->session()->push(self::SESION, $pedido->uuid);
        }

        return redirect(self::url('ver', $pedido));
    }

    /** Otro enlace al correo del pedido (para confirmarlo o, si ya lo está, para verlo). Mismo mensaje pase lo que pase. */
    public function reenviar(Request $request, Pedido $pedido): RedirectResponse
    {
        if (! $pedido->correoConfirmado()) {
            self::enviarConfirmacion($pedido);
        } else {
            CorreoPedido::enviar($pedido, new CorreoDelPedido($pedido, 'enlace'));
        }

        return ($this->puedeVer($request, $pedido) ? redirect(self::url('ver', $pedido)) : back())
            ->with('estado_pedido', __('pedidos.enlace_enviado'));
    }

    /** El pedido que este navegador acaba de crear (últimos 5 minutos), si lo hay. */
    private function reciente(Request $request): ?Pedido
    {
        return Pedido::whereIn('uuid', $request->session()->get(self::SESION, []))
            ->where('created_at', '>=', now()->subMinutes(5))->latest('id')->first();
    }

    private function puedeVer(Request $request, Pedido $pedido): bool
    {
        $cliente = $request->user()?->cliente;
        if ($cliente !== null && ! $cliente->activo) {
            return false; // ficha desactivada: como en Mi cuenta, sin acceso a sus pedidos (B5)
        }

        return in_array($pedido->uuid, $request->session()->get(self::SESION, []), true)
            || ($cliente !== null && $pedido->cliente_id !== null && $pedido->cliente_id === $cliente->id);
    }

    public static function enviarConfirmacion(Pedido $pedido): void
    {
        Notification::route('mail', $pedido->correo)->notify((new ConfirmaTuCorreo($pedido))->locale($pedido->idioma));
    }

    public static function enlaceConfirmar(Pedido $pedido): string
    {
        return URL::temporarySignedRoute("pedidos.{$pedido->idioma}.confirmar-correo", now()->addDays(self::diasEnlace()), ['pedido' => $pedido->uuid]);
    }

    /** Enlace privado y firmado al pedido, para los correos (90 días). */
    public static function enlacePrivado(Pedido $pedido): string
    {
        return URL::temporarySignedRoute("pedidos.{$pedido->idioma}.acceso", now()->addDays(90), ['pedido' => $pedido->uuid]);
    }

    public static function diasEnlace(): int
    {
        return max(1, (int) Ajuste::obtener('pedidos.dias_confirmar_correo'));
    }

    public static function url(string $accion, Pedido $pedido, ?string $idioma = null): string
    {
        $idioma ??= app()->getLocale();

        return route("pedidos.{$idioma}.{$accion}", ['pedido' => $pedido->uuid]);
    }

    /** @return array<string, string> */
    private function alternos(string $accion, Pedido $pedido): array
    {
        return collect(config('idiomas.disponibles'))->keys()->mapWithKeys(fn ($i) => [$i => self::url($accion, $pedido, $i)])->all();
    }
}
