<?php

namespace App\Notifications\Pedidos;

use App\Filament\Resources\Pedidos\PedidoResource;
use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al equipo (correo de contacto de Ajustes → General): un cliente pide cancelar un pedido pagado (D177). */
class CancelacionSolicitada extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Pedido $pedido) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Petición de cancelación · {$this->pedido->numero}")
            ->line("El cliente del pedido {$this->pedido->numero} pide cancelarlo.")
            ->line('Motivo: '.($this->pedido->motivo_cancelacion ?? 'no lo indica.'))
            ->line('Si todavía no está en producción, cancélalo en el backend (se emite la rectificativa) y devuelve el dinero.')
            ->action('Abrir el pedido', PedidoResource::getUrl('view', ['record' => $this->pedido], panel: 'admin'));
    }
}
