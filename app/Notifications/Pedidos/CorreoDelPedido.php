<?php

namespace App\Notifications\Pedidos;

use App\Dominio\Facturacion\PdfFactura;
use App\Dominio\Pedidos\CorreosPedido;
use App\Dominio\Pedidos\EstadoPedido;
use App\Dominio\Precios\Formato;
use App\Http\Controllers\PedidoController;
use App\Models\Ajuste;
use App\Models\Factura;
use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Los correos del pedido al cliente (paso 6.4), en su idioma y con el enlace privado a su
 * pedido: un cambio de estado (plantilla de Ajustes → Correos), una nota para el cliente o
 * el enlace que pide de nuevo. En la cola, con reintentos y tras confirmar la transacción.
 */
class CorreoDelPedido extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    /** @param  'estado'|'nota'|'enlace'|'factura'  $tipo */
    public function __construct(public readonly Pedido $pedido, public readonly string $tipo, public readonly ?EstadoPedido $estado = null, public readonly ?string $nota = null, public readonly ?Factura $factura = null)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pedido = $this->pedido->fresh() ?? $this->pedido;
        $idioma = $pedido->idioma;
        if ($this->tipo === 'factura' && $this->factura !== null) {
            return $this->correoFactura($pedido, $this->factura);
        }
        $clave = $this->tipo === 'estado' ? $this->estado : $this->tipo;
        $asunto = CorreosPedido::rellenar(CorreosPedido::plantilla($clave, 'asunto', $idioma), $pedido);
        $texto = CorreosPedido::rellenar(CorreosPedido::plantilla($clave, 'texto', $idioma), $pedido);
        $tienda = Ajuste::obtener('tienda.nombre');

        $correo = (new MailMessage)->subject($asunto.' · '.$tienda)
            ->greeting(trans('cuenta.correo_hola', ['nombre' => (string) ($pedido->direccion_envio['nombre'] ?? $pedido->nombre_fiscal)], $idioma))
            ->markdown('correos.pedido', ['resumen' => self::resumen($pedido, $idioma)]);
        foreach (preg_split('/\n\s*\n/', $texto) as $parrafo) {
            $correo->line(trim($parrafo));
        }
        if ($this->tipo === 'nota' && filled($this->nota)) {
            $correo->line('«'.trim($this->nota).'»');
        }

        return $correo->action(trans('correos.ver_pedido', [], $idioma), PedidoController::enlacePrivado($pedido))
            ->salutation(trans('cuenta.correo_despedida', ['tienda' => $tienda], $idioma));
    }

    /**
     * Lo que lleva el pedido y su total, para el correo (D204). Los importes salen del pedido (ya calculados).
     *
     * @return array{lineas: list<array{nombre: string, detalle: string, unidades: int}>, envio: ?string, total: string}
     */
    public static function resumen(Pedido $pedido, string $idioma): array
    {
        $pedido->loadMissing('lineas');

        return [
            'lineas' => $pedido->lineas->map(fn ($l) => [
                'nombre' => (string) ($l->nombre[$idioma] ?? $l->nombre['es'] ?? reset($l->nombre)),
                'detalle' => (string) ($l->color_nombre[$idioma] ?? $l->color_nombre['es'] ?? ''),
                'unidades' => (int) $l->unidades,
            ])->values()->all(),
            'envio' => $pedido->envio > 0 ? Formato::euros($pedido->envio, $idioma) : null,
            'total' => Formato::euros($pedido->total, $idioma),
        ];
    }

    /** La factura o la rectificativa, con su PDF adjunto (D175). */
    private function correoFactura(Pedido $pedido, Factura $factura): MailMessage
    {
        $idioma = $pedido->idioma;
        $tienda = Ajuste::obtener('tienda.nombre');
        $datos = ['tipo' => mb_strtolower($factura->tipo->etiqueta($idioma)), 'codigo' => $factura->codigo, 'numero' => $pedido->numero,
            'nombre' => (string) ($pedido->direccion_envio['nombre'] ?? $pedido->nombre_fiscal)];

        return (new MailMessage)
            ->subject(trans('facturas.correo_asunto', [...$datos, 'tipo' => $factura->tipo->etiqueta($idioma)], $idioma).' · '.$tienda)
            ->line(trans('facturas.correo_texto', $datos, $idioma))
            ->action(trans('correos.ver_pedido', [], $idioma), PedidoController::enlacePrivado($pedido))
            ->attachData(PdfFactura::generar($factura), $factura->nombreArchivo(), ['mime' => 'application/pdf'])
            ->salutation(trans('cuenta.correo_despedida', ['tienda' => $tienda], $idioma));
    }
}
