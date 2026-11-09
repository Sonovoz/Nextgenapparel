<?php

namespace App\Notifications\Pedidos;

use App\Dominio\Pedidos\CorreosPedido;
use App\Dominio\Pedidos\EstadoPedido;
use App\Http\Controllers\PedidoController;
use App\Models\Ajuste;
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

    /** @param  'estado'|'nota'|'enlace'  $tipo */
    public function __construct(public readonly Pedido $pedido, public readonly string $tipo, public readonly ?EstadoPedido $estado = null, public readonly ?string $nota = null)
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
        $clave = $this->tipo === 'estado' ? $this->estado : $this->tipo;
        $asunto = CorreosPedido::rellenar(CorreosPedido::plantilla($clave, 'asunto', $idioma), $pedido);
        $texto = CorreosPedido::rellenar(CorreosPedido::plantilla($clave, 'texto', $idioma), $pedido);
        $tienda = Ajuste::obtener('tienda.nombre');

        $correo = (new MailMessage)->subject($asunto.' · '.$tienda);
        foreach (preg_split('/\n\s*\n/', $texto) as $parrafo) {
            $correo->line(trim($parrafo));
        }
        if ($this->tipo === 'nota' && filled($this->nota)) {
            $correo->line('«'.trim($this->nota).'»');
        }

        return $correo->action(trans('correos.ver_pedido', [], $idioma), PedidoController::enlacePrivado($pedido))
            ->salutation(trans('cuenta.correo_despedida', ['tienda' => $tienda], $idioma));
    }
}
