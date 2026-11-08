<?php

namespace App\Notifications\Pedidos;

use App\Dominio\Precios\Formato;
use App\Http\Controllers\PedidoController;
use App\Models\Ajuste;
use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al invitado: confirma tu correo para seguir con el pedido (D166, D169). Plantilla editable en el paso 6.4. */
class ConfirmaTuCorreo extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly Pedido $pedido)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tienda = Ajuste::obtener('tienda.nombre');
        $dias = PedidoController::diasEnlace();

        return (new MailMessage)
            ->subject(__('pedidos.correo_confirmar_asunto', ['numero' => $this->pedido->numero, 'tienda' => $tienda]))
            ->greeting(__('cuenta.correo_hola', ['nombre' => $this->pedido->direccion_envio['nombre'] ?? '']))
            ->line(__('pedidos.correo_confirmar_texto', ['numero' => $this->pedido->numero, 'total' => Formato::euros($this->pedido->total)]))
            ->action(__('pedidos.correo_confirmar_boton'), PedidoController::enlaceConfirmar($this->pedido))
            ->line(trans_choice('pedidos.correo_confirmar_plazo', $dias, ['n' => $dias]))
            ->line(__('pedidos.correo_confirmar_no_fuiste'))
            ->salutation(__('cuenta.correo_despedida', ['tienda' => $tienda]));
    }
}
